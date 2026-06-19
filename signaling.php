<?php
header('Content-Type: application/json');

function sanitize($data) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $data);
}

$sessionId = sanitize($_POST['session_id'] ?? $_GET['session_id'] ?? '');
if (!$sessionId) die(json_encode(['error' => 'No session ID']));

$signalingFile = "signaling_{$sessionId}.json";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? ''; // offer, answer, candidate
    $data = $_POST['data'] ?? '';

    $lock = fopen($signalingFile . '.lock', 'w');
    if (flock($lock, LOCK_EX)) {
        $current = file_exists($signalingFile) ? json_decode(file_get_contents($signalingFile), true) : ['candidates' => []];

        if ($type === 'offer') {
            $current['offer'] = $data;
        } elseif ($type === 'answer') {
            $current['answers'][] = $data;
        } elseif ($type === 'candidate') {
            if ($data) $current['candidates'][] = $data;
        }

        file_put_contents($signalingFile, json_encode($current));
        flock($lock, LOCK_UN);
    }
    fclose($lock);
    echo json_encode(['status' => 'success']);
} else {
    if (file_exists($signalingFile)) {
        echo file_get_contents($signalingFile);
    } else {
        echo json_encode(['candidates' => []]);
    }
}
?>
