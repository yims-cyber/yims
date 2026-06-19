<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$sessionsFile = 'sessions.json';

function sanitize($data) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $data);
}

$action = $_POST['action'] ?? '';
$sessionId = sanitize($_POST['session_id'] ?? '');

if ($action === 'start_session' && $sessionId) {
    $lock = fopen($sessionsFile . '.lock', 'w');
    if (flock($lock, LOCK_EX)) {
        $sessions = file_exists($sessionsFile) ? json_decode(file_get_contents($sessionsFile), true) : [];
        $sessions[$sessionId] = [
            'id' => $sessionId,
            'start_time' => time(),
            'is_live' => true
        ];
        file_put_contents($sessionsFile, json_encode($sessions));
        flock($lock, LOCK_UN);
    }
    fclose($lock);
    echo json_encode(['status' => 'success']);
}

if ($action === 'post_transcript' && $sessionId) {
    $msg = $_POST['msg'] ?? '';
    $liveFile = "live_{$sessionId}.json";

    $lock = fopen($liveFile . '.lock', 'w');
    if (flock($lock, LOCK_EX)) {
        $data = [
            'text' => $msg,
            'timestamp' => time()
        ];
        file_put_contents($liveFile, json_encode($data));
        flock($lock, LOCK_UN);
    }
    fclose($lock);
    echo json_encode(['status' => 'success']);
}

if ($action === 'stop_session' && $sessionId) {
    $lock = fopen($sessionsFile . '.lock', 'w');
    if (flock($lock, LOCK_EX)) {
        $sessions = file_exists($sessionsFile) ? json_decode(file_get_contents($sessionsFile), true) : [];
        if (isset($sessions[$sessionId])) {
            $sessions[$sessionId]['is_live'] = false;
            file_put_contents($sessionsFile, json_encode($sessions));

            // Wait for auditors to receive end signal
            sleep(2);

            unset($sessions[$sessionId]);
            file_put_contents($sessionsFile, json_encode($sessions));
        }
        flock($lock, LOCK_UN);
    }
    fclose($lock);

    @unlink("live_{$sessionId}.json");
    @unlink("live_{$sessionId}.json.lock");
    @unlink("signaling_{$sessionId}.json");
    echo json_encode(['status' => 'success']);
}

if ($action === 'cleanup') {
    $files = glob("live_*.json*");
    $files = array_merge($files, glob("signaling_*.json*"));
    $files[] = 'sessions.json';
    $files[] = 'sessions.json.lock';
    foreach ($files as $file) {
        @unlink($file);
    }
    echo json_encode(['status' => 'success']);
}
?>
