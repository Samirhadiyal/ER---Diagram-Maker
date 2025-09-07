jsPlumb.ready(function() {
    // Initialize jsPlumb instance with better defaults
    let instance = jsPlumb.getInstance({
        Connector: ["Bezier", { curviness: 50 }],
        PaintStyle: { stroke: "#666", strokeWidth: 2 },
        EndpointStyle: { fill: "#666", radius: 5 },
        HoverPaintStyle: { stroke: "#007bff", strokeWidth: 3 },
        ConnectionOverlays: [
            ["Arrow", { 
                width: 10, 
                length: 10, 
                location: 1,
                id: "arrow"
            }]
        ],
        Container: "canvas"
    });

    // Connection mode variables
    let connectionMode = false;
    let entityElements = {};

    // Function to add a new entity
    $("#addEntity").click(function() {
        let shapeType = $("#entityType").val(); // Get selected shape
        
        // Special handling for connection mode
        if (shapeType === "arrow") {
            toggleConnectionMode();
            return;
        }
        
        addNewEntity(shapeType);
    });
    
    // Function to create and add a new entity
    function addNewEntity(shapeType) {
        let entityId = "entity" + new Date().getTime(); // Use timestamp for unique IDs
        
        // Create entity with random position inside canvas
        let canvas = $("#canvas");
        let canvasWidth = canvas.width() - 150;  // Account for entity width
        let canvasHeight = canvas.height() - 100; // Account for entity height
        
        let randomLeft = Math.floor(Math.random() * canvasWidth) + 20;
        let randomTop = Math.floor(Math.random() * canvasHeight) + 20;
        
        let entity = $('<div class="entity ' + shapeType + '" id="' + entityId + '" style="left:' + randomLeft + 'px; top:' + randomTop + 'px;">' +
            '<input type="text" class="entity-input" placeholder="Enter name">' +
        '</div>');
    
        $("#canvas").append(entity);
    
        // Make entity draggable
        instance.draggable(entityId, { 
            containment: "parent",
            stop: function() {
                // Repaint connections when dragging stops
                instance.repaintEverything();
            }
        });
        
        // Store reference to the entity element
        entityElements[entityId] = $("#" + entityId);
        
        // Setup for connection endpoints
        setupEntityForConnections(entityId);
        
        return entityId;
    }
    
    // Function to enable/disable dragging for all entities
    function toggleDraggable(enable) {
        for (let id in entityElements) {
            if (enable) {
                instance.setDraggable(id, true);
                $("#" + id).css("cursor", "move");
            } else {
                instance.setDraggable(id, false);
                $("#" + id).css("cursor", "pointer");
            }
        }
    }
    
    // Setup entity to be source and target for connections
    function setupEntityForConnections(entityId) {
        // Important: Set unlimited connections with -1
        instance.makeSource(entityId, {
            anchor: "Continuous",
            isSource: true,
            maxConnections: -1,  // This allows unlimited outgoing connections
            connectorStyle: { stroke: "#333", strokeWidth: 2 },
            connectorHoverStyle: { stroke: "#007bff", strokeWidth: 3 }
        });
        
        instance.makeTarget(entityId, {
            anchor: "Continuous",
            isTarget: true,
            maxConnections: -1,  // This allows unlimited incoming connections
            dropOptions: { hoverClass: "dragHover" },
            allowLoopback: true
        });
    }

    // Toggle connection mode
    function toggleConnectionMode() {
        connectionMode = !connectionMode;
        
        if (connectionMode) {
            $("#connModeBtn").addClass("active");
            $("#canvas").addClass("connection-mode");
            $("#connectionStatus").text("Connection Mode: Active - Click and drag from one element to another");
            
            // Make all entities ready for connections and disable dragging
            $(".entity").each(function() {
                $(this).addClass("connectable");
            });
            toggleDraggable(false);
        } else {
            $("#connModeBtn").removeClass("active");
            $("#canvas").removeClass("connection-mode");
            $("#connectionStatus").text("Connection Mode: Inactive");
            
            // Remove connection styling and re-enable dragging
            $(".entity").each(function() {
                $(this).removeClass("connectable");
            });
            toggleDraggable(true);
        }
    }

    // Toggle connection mode with button
    $("#connModeBtn").click(function() {
        toggleConnectionMode();
    });

    // Save diagram as PNG
    $("#saveDiagram").click(function () {
        let canvasElement = document.querySelector("#canvas");
    
        html2canvas(canvasElement, {
            backgroundColor: null,
            allowTaint: true,
            useCORS: true
        }).then(canvas => {
            let link = document.createElement('a');
            link.download = 'diagram.png';
            link.href = canvas.toDataURL("image/png");
            link.click();
        });
        
        // Also save diagram data
        saveDiagramData();
    });
    
    // Save diagram data
    function saveDiagramData() {
        // Collect entities
        let entities = [];
        $(".entity").each(function() {
            let $this = $(this);
            entities.push({
                id: $this.attr('id'),
                type: $this.hasClass('rectangle') ? 'rectangle' : 
                      $this.hasClass('diamond') ? 'diamond' : 'ellipse',
                name: $this.find('.entity-input').val() || '',
                position: {
                    left: parseInt($this.css('left')),
                    top: parseInt($this.css('top'))
                }
            });
        });
        
        // Collect connections
        let connections = [];
        instance.getAllConnections().forEach(function(conn) {
            connections.push({
                source: conn.sourceId,
                target: conn.targetId
            });
        });
        
        // Save data
        let diagramData = {
            entities: entities,
            connections: connections
        };
        
        // Send to server
        $.ajax({
            url: 'save_diagram.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(diagramData),
            success: function(response) {
                alert("Diagram saved successfully!");
            },
            error: function(xhr, status, error) {
                console.error("Error saving diagram:", error);
                alert("Error saving diagram");
            }
        });
    }

    // Clear canvas
    $("#clearCanvas").click(function () {
        if (confirm("Are you sure you want to clear the canvas? All elements and connections will be deleted.")) {
            // Remove all elements and connections
            instance.reset();
            $("#canvas").empty();
            
            // Reset connection mode
            connectionMode = false;
            entityElements = {};
            $("#connModeBtn").removeClass("active");
            $("#canvas").removeClass("connection-mode");
            $("#connectionStatus").text("Connection Mode: Inactive");
        }
    });
    
    // Add sample entities to get started
    function addSampleEntities() {
        const types = ["rectangle", "diamond", "ellipse"];
        for (let i = 0; i < 3; i++) {
            addNewEntity(types[i]);
        }
    }
    
    // Pre-populate with some sample entities
    setTimeout(addSampleEntities, 500);
});