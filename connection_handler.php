<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$diagramsDir = "diagrams";
if (!file_exists($diagramsDir)) {
    mkdir($diagramsDir, 0755, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_position') {
        $entityId = $_POST['entityId'] ?? '';
        $left = $_POST['left'] ?? 0;
        $top = $_POST['top'] ?? 0;
        
        if (empty($entityId)) {
            http_response_code(400);
            echo json_encode(["error" => "Entity ID required"]);
            exit;
        }

        $latestFile = $diagramsDir . "/latest_diagram.json";
        $diagramData = file_exists($latestFile) ? 
            json_decode(file_get_contents($latestFile), true) : 
            ['entities' => [], 'connections' => []];

        // Find and update entity
        $updated = false;
        foreach ($diagramData['entities'] as &$entity) {
            if ($entity['id'] === $entityId) {
                $entity['position'] = [
                    'left' => (int)str_replace('px', '', $left),
                    'top' => (int)str_replace('px', '', $top)
                ];
                $updated = true;
                break;
            }
        }

        // If new entity, add it
        if (!$updated) {
            $diagramData['entities'][] = [
                'id' => $entityId,
                'type' => 'rectangle', // Default type
                'position' => [
                    'left' => (int)str_replace('px', '', $left),
                    'top' => (int)str_replace('px', '', $top)
                ]
            ];
        }

        file_put_contents($latestFile, json_encode($diagramData));
        echo json_encode(["success" => true]);
    }
    // ... (rest of your existing connection handling code)
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}
?>