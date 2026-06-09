<?php
/**
 * Orateur Ultra Backend
 * Manages session state and transcription broadcasts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

$sessionsFile = 'sessions.json';

function atomic_write($filename, $data) {
    $fp = fopen($filename, 'w');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function atomic_read($filename) {
    if (!file_exists($filename)) return [];
    $content = '';
    $fp = fopen($filename, 'r');
    if (flock($fp, LOCK_SH)) {
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return json_decode($content, true) ?: [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['session_id'] ?? 'default');

switch ($action) {
    case 'broadcast':
        $msg = $_POST['msg'] ?? '';
        $type = $_POST['type'] ?? 'interim';

        if (!empty($msg)) {
            $data = [
                'msg' => htmlspecialchars($msg),
                'type' => $type,
                'timestamp' => time(),
                'is_live' => true
            ];
            atomic_write("live_{$sessionId}.json", $data);

            // Update main registry
            $registry = atomic_read($sessionsFile);
            $registry[$sessionId] = [
                'last_update' => time(),
                'is_live' => true
            ];
            atomic_write($sessionsFile, $registry);

            echo json_encode(['status' => 'success', 'received' => $msg]);
        }
        break;

    case 'stop_session':
        $registry = atomic_read($sessionsFile);
        if (isset($registry[$sessionId])) {
            $registry[$sessionId]['is_live'] = false;
            atomic_write($sessionsFile, $registry);
        }

        // Signal auditors that live ended
        if (file_exists("live_{$sessionId}.json")) {
            $data = atomic_read("live_{$sessionId}.json");
            $data['is_live'] = false;
            atomic_write("live_{$sessionId}.json", $data);
        }

        echo json_encode(['status' => 'stopped']);
        break;

    case 'get_sessions':
        echo json_encode(atomic_read($sessionsFile));
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
        break;
}
