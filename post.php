<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$sessionId = "main_session";
$liveFile = "live_" . $sessionId . ".json";
$registryFile = "sessions.json";

// Initialize registry if not exists
if (!file_exists($registryFile)) {
    file_put_contents($registryFile, json_encode([]));
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['msg'])) {
        $msg = $_POST['msg'];
        $data = [
            'text' => $msg,
            'timestamp' => time()
        ];
        file_put_contents($liveFile, json_encode($data));

        // Update registry
        $registry = json_decode(file_get_contents($registryFile), true);
        if (!isset($registry[$sessionId])) {
            $registry[$sessionId] = [
                'id' => $sessionId,
                'is_live' => true,
                'start_time' => time()
            ];
            file_put_contents($registryFile, json_encode($registry));
        }

        echo json_encode(['status' => 'success']);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'stop_session') {
        $registry = json_decode(file_get_contents($registryFile), true);
        if (isset($registry[$sessionId])) {
            $registry[$sessionId]['is_live'] = false;
            file_put_contents($registryFile, json_encode($registry));
        }

        // Hygiene
        if (file_exists($liveFile)) unlink($liveFile);

        echo json_encode(['status' => 'session_stopped']);
        exit;
    }
}

if ($action === 'get_sessions') {
    echo file_get_contents($registryFile);
    exit;
}

echo json_encode(['status' => 'idle']);
?>
