<?php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create storage directory if it doesn't exist
$diagramsDir = "diagrams";
if (!file_exists($diagramsDir)) {
    mkdir($diagramsDir, 0755, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get the raw POST data
    $json = file_get_contents("php://input");
    
    // Log incoming data for debugging
    file_put_contents($diagramsDir . "/debug.log", date("[Y-m-d H:i:s] ") . "Received data\n", FILE_APPEND);
    
    if (!$json) {
        http_response_code(400);
        echo json_encode(["error" => "No data received"]);
        exit;
    }
    
    try {
        // Decode the JSON data
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON: " . json_last_error_msg());
        }
        
        // Create a timestamped filename
        $timestamp = date("Ymd_His");
        $filename = $diagramsDir . "/diagram_" . $timestamp . ".json";
        
        // Save the diagram data
        $result = file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            throw new Exception("Failed to write file. Check permissions.");
        }
        
        // Also save as the latest diagram for easy access
        file_put_contents($diagramsDir . "/latest_diagram.json", json_encode($data, JSON_PRETTY_PRINT));
        
        // Generate SQL from the diagram data
        generateSQL($data, $diagramsDir . "/diagram_" . $timestamp . ".sql");
        
        // Success response
        header('Content-Type: application/json');
        echo json_encode([
            "success" => true, 
            "message" => "Diagram saved successfully", 
            "filename" => $filename,
            "sql_generated" => true
        ]);
        
    } catch (Exception $e) {
        // Error response
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(["error" => $e->getMessage()]);
        file_put_contents($diagramsDir . "/error_log.txt", date("[Y-m-d H:i:s] ") . $e->getMessage() . "\n", FILE_APPEND);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

/**
 * Generate SQL from diagram data
 */
function generateSQL($data, $filename) {
    $sql = "-- SQL Schema generated from ER Diagram\n";
    $sql .= "-- Generated on: " . date("Y-m-d H:i:s") . "\n\n";
    
    // Create database
    $sql .= "CREATE DATABASE IF NOT EXISTS `er_diagram`;\n";
    $sql .= "USE `er_diagram`;\n\n";
    
    // Process entities (tables)
    $tables = [];
    $relationships = [];
    
    if (isset($data['entities']) && is_array($data['entities'])) {
        foreach ($data['entities'] as $entity) {
            $entityId = $entity['id'];
            $entityName = !empty($entity['name']) ? $entity['name'] : 'entity_' . substr($entityId, 6);
            $entityType = $entity['type'] ?? 'unknown';
            
            // Create tables for rectangle entities
            if ($entityType === 'rectangle') {
                $tableName = sanitizeTableName($entityName);
                $tables[$entityId] = $tableName;
                
                $sql .= "-- Table: {$tableName}\n";
                $sql .= "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";
                $sql .= "    `id` INT AUTO_INCREMENT PRIMARY KEY,\n";
                $sql .= "    `name` VARCHAR(255) NOT NULL,\n";
                $sql .= "    `description` TEXT,\n";
                $sql .= "    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
                $sql .= "    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
                $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
            }
        }
    }
    
    // Process connections (relationships)
    if (isset($data['connections']) && is_array($data['connections'])) {
        foreach ($data['connections'] as $conn) {
            $sourceId = $conn['source'];
            $targetId = $conn['target'];
            
            // Only create relationships between known tables
            if (isset($tables[$sourceId]) && isset($tables[$targetId])) {
                $sourceTable = $tables[$sourceId];
                $targetTable = $tables[$targetId];
                
                // Create foreign key field
                $fkField = strtolower($targetTable) . '_id';
                
                $relationships[] = [
                    'source' => $sourceTable,
                    'target' => $targetTable,
                    'fk_field' => $fkField
                ];
            }
        }
    }
    
    // Add foreign key constraints
    if (!empty($relationships)) {
        $sql .= "-- Relationships\n\n";
        
        foreach ($relationships as $rel) {
            $sql .= "ALTER TABLE `{$rel['source']}`\n";
            $sql .= "    ADD COLUMN IF NOT EXISTS `{$rel['fk_field']}` INT,\n";
            $sql .= "    ADD CONSTRAINT `fk_{$rel['source']}_{$rel['target']}`\n";
            $sql .= "    FOREIGN KEY (`{$rel['fk_field']}`) REFERENCES `{$rel['target']}`(`id`);\n\n";
        }
    }
    
    // Save SQL to file
    file_put_contents($filename, $sql);
    return true;
}

/**
 * Sanitize table name for SQL
 */
function sanitizeTableName($name) {
    // Remove special characters and spaces
    $name = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($name));
    
    // Ensure name starts with a letter
    if (!preg_match('/^[a-zA-Z]/', $name)) {
        $name = 'tbl_' . $name;
    }
    
    // Ensure the name is not empty
    if (empty($name)) {
        $name = 'table_' . time();
    }
    
    // Limit length to 64 characters (MySQL limit)
    return substr($name, 0, 64);
}
?>