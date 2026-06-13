<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

/**
 * Atomic write function with flock
 */
function atomic_write($file, $data) {
    $fp = fopen($file, 'c+');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

/**
 * Atomic read function with flock
 */
function atomic_read($file) {
    if (!file_exists($file)) return null;
    $fp = fopen($file, 'r');
    $content = null;
    if (flock($fp, LOCK_SH)) {
        $size = filesize($file);
        if ($size > 0) {
            $content = json_decode(fread($fp, $size), true);
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $content;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'broadcast';
$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id'] ?? 'default');
$msg = $_POST['msg'] ?? '';

if ($action === 'broadcast' || isset($_POST['post'])) {
    $file = "live_{$sessionId}.json";
    $data = [
        'session_id' => $sessionId,
        'msg' => $msg,
        'timestamp' => time(),
        'type' => $_POST['type'] ?? 'final'
    ];
    atomic_write($file, $data);

    // Update central registry
    $registryFile = 'sessions.json';
    $sessions = atomic_read($registryFile) ?? [];
    if (!isset($sessions[$sessionId])) {
        $sessions[$sessionId] = [
            'id' => $sessionId,
            'is_live' => true,
            'start_time' => time()
        ];
        atomic_write($registryFile, $sessions);
    }

    echo json_encode(['status' => 'success', 'file' => $file]);
} elseif ($action === 'stop_session') {
    $file = "live_{$sessionId}.json";
    if (file_exists($file)) unlink($file);

    $registryFile = 'sessions.json';
    $sessions = atomic_read($registryFile) ?? [];
    if (isset($sessions[$sessionId])) {
        unset($sessions[$sessionId]);
        atomic_write($registryFile, $sessions);
    }
    echo json_encode(['status' => 'stopped']);
} elseif ($action === 'get_sessions') {
    $registryFile = 'sessions.json';
    $sessions = atomic_read($registryFile) ?? [];
    echo json_encode(array_values($sessions));
} elseif ($action === 'hard_cleanup') {
    // Delete all live_*.json files
    foreach (glob("live_*.json") as $file) {
        unlink($file);
    }
    if (file_exists("sessions.json")) unlink("sessions.json");
    echo json_encode(['status' => 'cleaned']);
}
?>
