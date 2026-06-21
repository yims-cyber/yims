<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$sessionId = isset($_POST['session_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id']) : 'default';
$file = "live_" . $sessionId . ".json";

if (isset($_POST['post']) && $_POST['post'] == 'yes') {
    $msg = $_POST['msg'] ?? '';
    if (!empty($msg)) {
        $data = [
            'text' => $msg,
            'timestamp' => time(),
            'is_live' => true
        ];
        file_put_contents($file, json_encode($data));

        // Also update sessions registry
        $registryFile = 'sessions.json';
        $sessions = [];
        if (file_exists($registryFile)) {
            $sessions = json_decode(file_get_contents($registryFile), true) ?: [];
        }

        $sessions[$sessionId] = [
            'last_active' => time(),
            'is_live' => true
        ];

        file_put_contents($registryFile, json_encode($sessions));
    }
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'get_sessions') {
    $registryFile = 'sessions.json';
    if (file_exists($registryFile)) {
        echo file_get_contents($registryFile);
    } else {
        echo json_encode((object)[]);
    }
    exit;
}
?>
