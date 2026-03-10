<?php
function getSwitches(): mixed {
    $file = 'switches.json';
    if (file_exists(filename: $file)) {
        $data = file_get_contents(filename: $file);
        return json_decode(json: $data, associative: true);
    }
    return ['switch1' => false];
}

function saveSwitches($switches) {
    $file = 'switches.json';
    file_put_contents(filename: $file, data: json_encode(value: $switches, flags: JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(json: file_get_contents(filename: 'php://input'), associative: true);
    if (isset($input['switch']) && isset($input['state'])) {
        $switches = getSwitches();
        $switches[$input['switch']] = $input['state'];
        saveSwitches($switches);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'switches' => $switches]);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
}

if ($method === 'GET' && isset($_GET['api'])) {
    header('Content-Type: application/json');
    echo json_encode(getSwitches());
    exit;
}

if ($method === 'GET' && isset($_GET['stream'])) {
    header(header: 'Content-Type: text/event-stream');
    header(header: 'Cache-Control: no-cache');
    header(header: 'Connection: keep-alive');
    
    $lastModified = filemtime(filename: 'switches.json');
    $lastHash = md5_file(filename: 'switches.json');
    
    while (true) {
        clearstatcache();
        $currentModified = filemtime(filename: 'switches.json');
        $currentHash = md5_file(filename: 'switches.json');
        
        if ($currentHash !== $lastHash) {
            $switches = getSwitches();
            echo "data: " . json_encode(value: $switches) . "\n\n";
            $lastHash = $currentHash;
        }
        
        echo "heartbeat: ping\n\n";
        ob_flush();
        flush();
        sleep(seconds: 0.4);
    }
    exit;
}

$switches = getSwitches();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Switch Control System</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>Switch Control System</h1>
        
        <div class="switch-container">
            <div class="switch">
                <label class="switch-label">Switch 1</label>
                <div class="toggle <?php echo $switches['switch1'] ? 'on' : ''; ?>" data-switch="switch1">
                    <div class="toggle-slider"></div>
                </div>
            </div>
        </div>
        
        <div class="status" id="status">
            Click any switch to toggle its state
        </div>
    </div>

    <script>
        let eventSource;
        
        function connectEventSource() {
            eventSource = new EventSource('./index.php?stream=1');
            
            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);
                updateSwitches(data);
                updateStatus(data);
            };
            
            eventSource.addEventListener('heartbeat', function() {
                console.log('Connection alive');
            });
            
            eventSource.onerror = function() {
                console.log('EventSource connection lost, reconnecting...');
                setTimeout(connectEventSource, 1000);
            };
        }
        
        function updateSwitches(switches) {
            Object.keys(switches).forEach(switchName => {
                const toggle = document.querySelector(`[data-switch="${switchName}"]`);
                if (toggle) {
                    if (switches[switchName]) {
                        toggle.classList.add('on');
                    } else {
                        toggle.classList.remove('on');
                    }
                }
            });
        }
        
        function updateStatus(switches) {
            const status = Object.entries(switches)
                .map(([key, value]) => `${key}: ${value ? 'ON' : 'OFF'}`)
                .join(' | ');
            document.getElementById('status').textContent = status;
        }
        
        document.querySelectorAll('.toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const switchName = this.dataset.switch;
                const currentState = this.classList.contains('on');
                const newState = !currentState;
                
                fetch('./index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        switch: switchName,
                        state: newState
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        document.getElementById('status').textContent = 'Error updating switch';
                    }
                })
                .catch(error => {
                    document.getElementById('status').textContent = 'Network error';
                });
            });
        });
        
        connectEventSource();
    </script>
</body>
</html>
