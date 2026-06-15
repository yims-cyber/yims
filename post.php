<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

$sessions_file = 'sessions.json';

function atomic_write($file, $data) {
    $fp = fopen($file, 'c+');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function atomic_read($file) {
    if (!file_exists($file)) return [];
    $fp = fopen($file, 'r');
    $data = [];
    if (flock($fp, LOCK_SH)) {
        $size = filesize($file);
        if ($size > 0) {
            $content = fread($fp, $size);
            $data = json_decode($content, true);
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $data;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'start_session') {
    $session_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id'] ?? '');
    if (!$session_id) exit(json_encode(['error' => 'Invalid Session ID']));

    $sessions = atomic_read($sessions_file);
    $sessions[$session_id] = [
        'id' => $session_id,
        'start_time' => time(),
        'is_live' => true
    ];
    atomic_write($sessions_file, $sessions);

    $live_file = "live_{$session_id}.json";
    atomic_write($live_file, ['msg' => '', 'type' => 'interim', 'timestamp' => time()]);

    echo json_encode(['status' => 'started', 'session_id' => $session_id]);

} elseif ($action === 'stop_session') {
    $session_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id'] ?? '');
    $sessions = atomic_read($sessions_file);

    if (isset($sessions[$session_id])) {
        $sessions[$session_id]['is_live'] = false;
        atomic_write($sessions_file, $sessions);

        // Let it linger in sessions.json for a bit before cleanup if needed
        // For now, simple removal from active registry
        unset($sessions[$session_id]);
        atomic_write($sessions_file, $sessions);

        @unlink("live_{$session_id}.json");
        @unlink("signaling_{$session_id}.json");
    }
    echo json_encode(['status' => 'stopped']);

} elseif ($action === 'broadcast') {
    $session_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id'] ?? '');
    $msg = $_POST['msg'] ?? '';
    $type = $_POST['type'] ?? 'final';

    if ($session_id) {
        $live_file = "live_{$session_id}.json";
        atomic_write($live_file, [
            'msg' => $msg,
            'type' => $type,
            'timestamp' => time()
        ]);
        echo json_encode(['status' => 'broadcasted']);
    }

} elseif ($action === 'get_sessions') {
    $sessions = atomic_read($sessions_file);
    echo json_encode(array_values($sessions));

} elseif ($action === 'hard_cleanup') {
    // Delete all live_*.json, signaling_*.json and reset sessions.json
    $files = glob("live_*.json");
    foreach($files as $file) @unlink($file);

    $files = glob("signaling_*.json");
    foreach($files as $file) @unlink($file);

    atomic_write($sessions_file, []);
    echo "Nettoyage Global Terminé.";
}
?>
