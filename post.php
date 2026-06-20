<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$sessionsFile = 'sessions.json';

// Sanitize session_id
function sanitize($id) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
}

// Atomic read/write for JSON
function updateJsonFile($filepath, $callback, $defaultData = []) {
    $fp = fopen($filepath, 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $data = json_decode($content, true) ?: $defaultData;
    $newData = $callback($data);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($newData, JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

$action = $_REQUEST['action'] ?? '';
$sessionId = sanitize($_REQUEST['session_id'] ?? '');

if (isset($_POST['post']) && $_POST['post'] === 'yes') {
    $msg = $_POST['msg'] ?? '';
    if ($sessionId && $msg) {
        // Update session registry
        updateJsonFile($sessionsFile, function($sessions) use ($sessionId) {
            $sessions[$sessionId] = [
                'id' => $sessionId,
                'last_active' => time(),
                'is_live' => true
            ];
            return $sessions;
        }, (object)[]);

        // Save live transcription
        $liveFile = "live_{$sessionId}.json";
        updateJsonFile($liveFile, function($data) use ($msg) {
            $data['last_msg'] = $msg;
            $data['timestamp'] = time();
            if (!isset($data['history'])) $data['history'] = [];
            array_unshift($data['history'], ['text' => $msg, 'time' => time()]);
            $data['history'] = array_slice($data['history'], 0, 50);
            return $data;
        });
        echo json_encode(['status' => 'success']);
    }
    exit;
}

switch ($action) {
    case 'get_sessions':
        if (file_exists($sessionsFile)) {
            echo file_get_contents($sessionsFile);
        } else {
            echo json_encode((object)[]);
        }
        break;

    case 'stop_session':
        if ($sessionId) {
            // Mark as not live first so auditors can see
            $liveFile = "live_{$sessionId}.json";
            if (file_exists($liveFile)) {
                updateJsonFile($liveFile, function($data) {
                    $data['is_live'] = false;
                    return $data;
                });
            }

            sleep(2); // Wait for auditors to poll

            updateJsonFile($sessionsFile, function($sessions) use ($sessionId) {
                unset($sessions[$sessionId]);
                return $sessions;
            });

            @unlink($liveFile);
            @unlink("signaling_{$sessionId}.json");
            echo json_encode(['status' => 'stopped']);
        }
        break;

    case 'clear_all':
        $files = array_merge(
            glob('live_*.json'),
            glob('signaling_*.json'),
            [$sessionsFile]
        );
        foreach ($files as $file) {
            @unlink($file);
        }
        echo json_encode(['status' => 'cleaned']);
        break;

    default:
        echo json_encode(['error' => 'unknown_action']);
}
