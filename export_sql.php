<?php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Path to diagrams directory
$diagramsDir = "diagrams";

// First check if we have a latest_diagram.json file
$latestFile = $diagramsDir . "/latest_diagram.json";

// If not, find the most recent diagram file
if (!file_exists($latestFile)) {
    $files = glob($diagramsDir . "/diagram_*.json");
    
    if (empty($files)) {
        header("Content-Type: text/plain");
        echo "No diagram files found. Please create and save a diagram first.";
        exit;
    }
    
    // Sort files by modification time (newest first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $latestFile = $files[0];
}

// Check if the file exists
if (!file_exists($latestFile)) {
    header("Content-Type: text/plain");
    echo "No diagram found. Please create and save a diagram first.";
    exit;
}

// Load the diagram data
$diagramData = json_decode(file_get_contents($latestFile), true);

// Check if the data is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    header("Content-Type: text/plain");
    echo "Error loading diagram data: " . json_last_error_msg();
    exit;
}

// Generate SQL from the diagram data
$sql = generateSQL($diagramData);

// Output as a downloadable file
header("Content-Disposition: attachment; filename=er_diagram.sql");
header("Content-Type: text/plain");
echo $sql;

/**
 * Generate SQL from diagram data
 */
function generateSQL($data) {
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
            
            // Create tables for rectangle entities (tables)
            if ($entityType === 'rectangle') {
                $tableName = sanitizeTableName($entityName);
                $tables[$entityId] = $tableName;
                
                $sql .= "-- Table: {$tableName}\n";
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= "CREATE TABLE `{$tableName}` (\n";
                $sql .= "    `id` INT AUTO_INCREMENT PRIMARY KEY,\n";
                $sql .= "    `name` VARCHAR(255) NOT NULL,\n";
                $sql .= "    `description` TEXT,\n";
                $sql .= "    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
                $sql .= "    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
                $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
            }
            // Create tables for ellipse entities (attributes)
            elseif ($entityType === 'ellipse') {
                // We'll use these for column definitions later
                continue;
            }
            // Create join tables for diamond entities (relationships)
            elseif ($entityType === 'diamond') {
                // We'll use these for relationship definitions later
                continue;
            }
        }
    }
    
    // Process connections (relationships)
    if (isset($data['connections']) && is_array($data['connections'])) {
        foreach ($data['connections'] as $conn) {
            $sourceId = $conn['source'];
            $targetId = $conn['target'];
            
            // Find the entities
            $sourceEntity = findEntityById($data['entities'], $sourceId);
            $targetEntity = findEntityById($data['entities'], $targetId);
            
            if (!$sourceEntity || !$targetEntity) {
                continue;
            }
            
            // Handle different relationship types based on entity types
            $sourceType = $sourceEntity['type'] ?? 'unknown';
            $targetType = $targetEntity['type'] ?? 'unknown';
            
            // Table-to-Table relationship (rectangle to rectangle)
            if ($sourceType === 'rectangle' && $targetType === 'rectangle') {
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
            // Table-to-Attribute relationship (rectangle to ellipse)
            elseif ($sourceType === 'rectangle' && $targetType === 'ellipse') {
                if (isset($tables[$sourceId])) {
                    $tableName = $tables[$sourceId];
                    $attributeName = !empty($targetEntity['name']) ? sanitizeColumnName($targetEntity['name']) : 'attribute_' . time();
                    
                    $sql .= "-- Add attribute to {$tableName}\n";
                    $sql .= "ALTER TABLE `{$tableName}`\n";
                    $sql .= "    ADD COLUMN `{$attributeName}` VARCHAR(255);\n\n";
                }
            }
        }
    }
    
    // Add foreign key constraints
    if (!empty($relationships)) {
        $sql .= "-- Relationships\n\n";
        
        foreach ($relationships as $rel) {
            $sql .= "ALTER TABLE `{$rel['source']}`\n";
            $sql .= "    ADD COLUMN `{$rel['fk_field']}` INT,\n";
            $sql .= "    ADD CONSTRAINT `fk_{$rel['source']}_{$rel['target']}`\n";
            $sql .= "    FOREIGN KEY (`{$rel['fk_field']}`) REFERENCES `{$rel['target']}`(`id`);\n\n";
        }
    }
    
    // Add sample data
    $sql .= "-- Sample data\n";
    foreach ($tables as $tableName) {
        $sql .= "INSERT INTO `{$tableName}` (`name`, `description`) VALUES\n";
        $sql .= "    ('Sample {$tableName} 1', 'Description for sample {$tableName} 1'),\n";
        $sql .= "    ('Sample {$tableName} 2', 'Description for sample {$tableName} 2');\n\n";
    }
    
    return $sql;
}

/**
 * Find an entity by ID
 */
function findEntityById($entities, $id) {
    foreach ($entities as $entity) {
        if ($entity['id'] === $id) {
            return $entity;
        }
    }
    return null;
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

/**
 * Sanitize column name for SQL
 */
function sanitizeColumnName($name) {
    // Remove special characters and spaces
    $name = preg_replace('/[^a-zA-Z0-9_]/', '_', trim($name));
    
    // Ensure name starts with a letter
    if (!preg_match('/^[a-zA-Z]/', $name)) {
        $name = 'col_' . $name;
    }
    
    // Ensure the name is not empty
    if (empty($name)) {
        $name = 'column_' . time();
    }
    
    // Limit length to 64 characters (MySQL limit)
    return substr($name, 0, 64);
}
?>