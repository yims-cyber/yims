<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

$sessionId = isset($_POST['session_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id']) : 'default';
$action = $_POST['action'] ?? '';

if ($action === 'broadcast') {
    $msg = $_POST['msg'] ?? '';
    $type = $_POST['type'] ?? 'final';
    $data = [
        'msg' => $msg,
        'type' => $type,
        'timestamp' => time()
    ];
    file_put_contents("live_{$sessionId}.json", json_encode($data));
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'get_sessions') {
    // Basic session listing logic for the auditor
    echo json_encode(['sessions' => [['id' => 'default', 'name' => 'Session Live']]]);
    exit;
}

echo json_encode(['status' => 'ready']);
