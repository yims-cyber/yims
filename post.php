<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

$sessionsFile = 'sessions.json';

function atomic_read($path) {
    if (!file_exists($path)) return null;
    $fp = fopen($path, 'r');
    flock($fp, LOCK_SH);
    $content = file_get_contents($path);
    flock($fp, LOCK_UN);
    fclose($fp);
    return json_decode($content, true);
}

function atomic_write($path, $data) {
    $fp = fopen($path, 'c+');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id'] ?? $_GET['session_id'] ?? '');

if ($action === 'start_session') {
    $sessions = atomic_read($sessionsFile) ?? [];
    $sessions[$sessionId] = [
        'id' => $sessionId,
        'start_time' => time(),
        'is_live' => true,
        'last_update' => time()
    ];
    atomic_write($sessionsFile, $sessions);

    $liveFile = "live_{$sessionId}.json";
    atomic_write($liveFile, ['transcript' => '', 'type' => 'interim', 'timestamp' => time()]);

    echo json_encode(['status' => 'success', 'session_id' => $sessionId]);
    exit;
}

if ($action === 'stop_session') {
    $sessions = atomic_read($sessionsFile) ?? [];
    if (isset($sessions[$sessionId])) {
        $sessions[$sessionId]['is_live'] = false;
        atomic_write($sessionsFile, $sessions);

        // Let auditors see it's ended before deletion
        sleep(2);

        unset($sessions[$sessionId]);
        atomic_write($sessionsFile, $sessions);

        @unlink("live_{$sessionId}.json");
        @unlink("signaling_{$sessionId}.json");
    }
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'broadcast') {
    $msg = $_POST['msg'] ?? '';
    $type = $_POST['type'] ?? 'interim';

    $liveFile = "live_{$sessionId}.json";
    atomic_write($liveFile, [
        'transcript' => $msg,
        'type' => $type,
        'timestamp' => time()
    ]);

    $sessions = atomic_read($sessionsFile) ?? [];
    if (isset($sessions[$sessionId])) {
        $sessions[$sessionId]['last_update'] = time();
        atomic_write($sessionsFile, $sessions);
    }

    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'get_sessions') {
    $sessions = atomic_read($sessionsFile) ?? [];
    echo json_encode([
        'sessions' => array_values($sessions),
        'server_time' => time()
    ]);
    exit;
}

if ($action === 'clean_all') {
    $sessions = atomic_read($sessionsFile) ?? [];
    foreach ($sessions as $id => $s) {
        @unlink("live_{$id}.json");
        @unlink("signaling_{$id}.json");
    }
    atomic_write($sessionsFile, []);
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
