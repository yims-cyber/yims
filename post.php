<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$sessionFile = 'sessions.json';
$logsFile = 'broadcast_debug.log';

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

$action = $_POST['action'] ?? $_GET['action'] ?? 'get_sessions';
$sessionId = $_POST['session_id'] ?? $_GET['session_id'] ?? '';
$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId); // Sanitize

if ($action === 'start_session' && !empty($sessionId)) {
    $sessions = atomic_read($sessionFile) ?? [];
    $sessions[$sessionId] = [
        'id' => $sessionId,
        'start_time' => time(),
        'status' => 'active'
    ];
    atomic_write($sessionFile, $sessions);

    $initialData = [
        'is_live' => true,
        'is_speaking' => false,
        'text' => '',
        'type' => 'status',
        'timestamp' => round(microtime(true) * 1000)
    ];
    atomic_write("live_{$sessionId}.json", $initialData);
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
    // Notify auditors that session is ending
    $liveFile = "live_{$sessionId}.json";
    if (file_exists($liveFile)) {
        $endData = [
            'is_live' => false,
            'timestamp' => round(microtime(true) * 1000)
        ];
        atomic_write($liveFile, $endData);
        sleep(2); // Give 2 seconds for auditors to see the status before deletion
    }

    $sessions = atomic_read($sessionFile) ?? [];
    if (isset($sessions[$sessionId])) {
        unset($sessions[$sessionId]);
        if (empty($sessions)) {
            if (file_exists($sessionFile)) unlink($sessionFile);
        } else {
            atomic_write($sessionFile, $sessions);
        }
    }

    // Cleanup
    $filesToDelete = ["live_{$sessionId}.json", "signaling_{$sessionId}.json"];
    foreach($filesToDelete as $f) {
        if (file_exists($f)) unlink($f);
    }

    if (empty($sessions)) {
        if (file_exists($logsFile)) unlink($logsFile);
        if (file_exists('voix.log')) unlink('voix.log');
    }

    echo json_encode(['status' => 'stopped']);

} elseif ($action === 'hard_cleanup') {
    // Delete registries and logs
    $globals = [$sessionFile, $logsFile, 'voix.log', 'webrtc_signaling.json', 'broadcast.json', 'sessions.json'];
    foreach($globals as $g) { if (file_exists($g)) unlink($g); }

    // Delete all session-specific files
    foreach (glob("live_*.json") as $f) unlink($f);
    foreach (glob("signaling_*.json") as $f) unlink($f);

    echo json_encode(['status' => 'hard_cleanup_done']);

} elseif ($action === 'get_sessions') {
    $sessions = atomic_read($sessionFile) ?? [];
    echo json_encode(['sessions' => array_values($sessions), 'server_time' => round(microtime(true) * 1000)]);

} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>
