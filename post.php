<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$sessionsFile = 'sessions.json';

// Sanitize session_id
$sessionId = isset($_REQUEST['session_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['session_id']) : 'default';
$liveFile = "live_{$sessionId}.json";

$action = $_REQUEST['action'] ?? '';

function getRegistry() {
    global $sessionsFile;
    if (!file_exists($sessionsFile)) return [];
    $data = file_get_contents($sessionsFile);
    return json_decode($data, true) ?: [];
}

function saveRegistry($registry) {
    global $sessionsFile;
    $fp = fopen($sessionsFile, 'w');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($registry, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['post']) && $_POST['post'] === 'yes') {
        $msg = $_POST['msg'] ?? '';
        $data = [
            'text' => $msg,
            'timestamp' => time(),
            'is_live' => true
        ];
        file_put_contents($liveFile, json_encode($data));
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'start_session') {
        $registry = getRegistry();
        $registry[$sessionId] = [
            'id' => $sessionId,
            'start_time' => time(),
            'last_active' => time(),
            'is_live' => true
        ];
        saveRegistry($registry);
        echo json_encode(['status' => 'started', 'session_id' => $sessionId]);
        exit;
    }

    if ($action === 'stop_session') {
        // Signal auditors that session is ending
        if (file_exists($liveFile)) {
            $data = json_decode(file_get_contents($liveFile), true) ?: [];
            $data['is_live'] = false;
            file_put_contents($liveFile, json_encode($data));
        }

        sleep(2); // Give auditors time to receive the signal

        $registry = getRegistry();
        unset($registry[$sessionId]);
        saveRegistry($registry);

        if (file_exists($liveFile)) unlink($liveFile);
        $signalingFile = "signaling_{$sessionId}.json";
        if (file_exists($signalingFile)) unlink($signalingFile);

        echo json_encode(['status' => 'stopped']);
        exit;
    }

    if ($action === 'cleanup') {
        $registry = getRegistry();
        foreach ($registry as $id => $session) {
            if (file_exists("live_{$id}.json")) unlink("live_{$id}.json");
            if (file_exists("signaling_{$id}.json")) unlink("signaling_{$id}.json");
        }
        if (file_exists($sessionsFile)) unlink($sessionsFile);
        echo json_encode(['status' => 'cleaned']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'get_sessions') {
        echo json_encode(array_values(getRegistry()));
        exit;
    }

    if (file_exists($liveFile)) {
        echo file_get_contents($liveFile);
    } else {
        echo json_encode(['text' => '', 'is_live' => false]);
    }
    exit;
}
?>
