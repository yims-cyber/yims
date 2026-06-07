<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$sessionFile = 'sessions.json';
$logsFile = 'broadcast_debug.log';

// Helper for atomic operations
function atomic_write($file, $data) {
    $fp = fopen($file, 'c+');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function atomic_read($file) {
    if (!file_exists($file)) return null;
    $fp = fopen($file, 'r');
    $data = null;
    if (flock($fp, LOCK_SH)) {
        $content = stream_get_contents($fp);
        $data = json_decode($content, true);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $data;
}

// Action selection
$action = $_POST['action'] ?? $_GET['action'] ?? 'get_sessions';
$sessionId = $_POST['session_id'] ?? $_GET['session_id'] ?? '';

if ($action === 'start_session' && !empty($sessionId)) {
    $sessions = atomic_read($sessionFile) ?? [];
    $sessions[$sessionId] = [
        'id' => $sessionId,
        'start_time' => time(),
        'status' => 'active'
    ];
    atomic_write($sessionFile, $sessions);

    // Create initial live file
    $initialData = [
        'is_live' => true,
        'is_speaking' => false,
        'text' => '',
        'type' => 'status',
        'timestamp' => round(microtime(true) * 1000)
    ];
    atomic_write("live_{$sessionId}.json", $initialData);

    file_put_contents($logsFile, "[START] Session $sessionId created at " . date('H:i:s') . PHP_EOL, FILE_APPEND);
    echo json_encode(['status' => 'success']);

} elseif ($action === 'broadcast' && !empty($sessionId)) {
    $liveFile = "live_{$sessionId}.json";
    $data = [
        'is_live' => true,
        'is_speaking' => ($_POST['speaking'] === 'yes'),
        'text' => $_POST['msg'] ?? '',
        'type' => $_POST['type'] ?? 'interim',
        'timestamp' => round(microtime(true) * 1000)
    ];
    atomic_write($liveFile, $data);
    echo json_encode(['status' => 'broadcasted']);

} elseif ($action === 'stop_session' && !empty($sessionId)) {
    $sessions = atomic_read($sessionFile) ?? [];
    if (isset($sessions[$sessionId])) {
        unset($sessions[$sessionId]);
        atomic_write($sessionFile, $sessions);
    }
    // Update live file to notify auditors
    $finalData = ['is_live' => false, 'timestamp' => round(microtime(true) * 1000)];
    atomic_write("live_{$sessionId}.json", $finalData);

    file_put_contents($logsFile, "[STOP] Session $sessionId terminated at " . date('H:i:s') . PHP_EOL, FILE_APPEND);
    echo json_encode(['status' => 'stopped']);

} elseif ($action === 'get_sessions') {
    $sessions = atomic_read($sessionFile) ?? [];
    echo json_encode(['sessions' => array_values($sessions), 'server_time' => round(microtime(true) * 1000)]);

} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>
