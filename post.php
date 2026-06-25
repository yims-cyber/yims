<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$sessionsFile = 'sessions.json';

// Initialize sessions file if not exists
if (!file_exists($sessionsFile)) {
    file_put_contents($sessionsFile, json_encode([]));
}

$action = $_GET['action'] ?? '';

// Hard Cleanup
if ($action === 'hard_cleanup') {
    $files = glob('live_*.json');
    foreach ($files as $file) unlink($file);
    file_put_contents($sessionsFile, json_encode([]));
    echo json_encode(['status' => 'success', 'message' => 'Global cleanup completed']);
    exit;
}

// Get Sessions
if ($action === 'get_sessions') {
    echo file_get_contents($sessionsFile);
    exit;
}

// Post Transcription
if (isset($_POST['post']) && isset($_POST['session_id'])) {
    $sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id']);
    $msg = $_POST['msg'] ?? '';

    $liveFile = "live_{$sessionId}.json";
    $timestamp = time();

    // Update live transcription file
    $data = [
        'session_id' => $sessionId,
        'last_msg' => $msg,
        'timestamp' => $timestamp,
        'is_live' => true
    ];
    file_put_contents($liveFile, json_encode($data));

    // Update registry
    $sessions = json_decode(file_get_contents($sessionsFile), true) ?: [];
    $sessions[$sessionId] = [
        'id' => $sessionId,
        'last_update' => $timestamp,
        'is_live' => true
    ];
    file_put_contents($sessionsFile, json_encode($sessions));

    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
