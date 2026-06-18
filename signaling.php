<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$sessionId = isset($_REQUEST['session_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['session_id']) : 'default';
$signalingFile = "signaling_{$sessionId}.json";

$action = $_REQUEST['action'] ?? '';

function getSignalingData() {
    global $signalingFile;
    if (!file_exists($signalingFile)) return ['offer' => null, 'answers' => [], 'candidates' => []];
    $data = file_get_contents($signalingFile);
    return json_decode($data, true) ?: ['offer' => null, 'answers' => [], 'candidates' => []];
}

function saveSignalingData($data) {
    global $signalingFile;
    $fp = fopen($signalingFile, 'w');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $data = getSignalingData();

    if ($action === 'post_offer') {
        $data['offer'] = $input['offer'];
        $data['candidates'] = []; // Reset candidates for new offer
        $data['answers'] = [];
        saveSignalingData($data);
        echo json_encode(['status' => 'success']);
    }
    elseif ($action === 'post_answer') {
        $data['answers'][] = $input['answer'];
        saveSignalingData($data);
        echo json_encode(['status' => 'success']);
    }
    elseif ($action === 'post_candidate') {
        if (isset($input['candidate']) && $input['candidate'] !== null) {
            $data['candidates'][] = $input['candidate'];
            saveSignalingData($data);
        }
        echo json_encode(['status' => 'success']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(getSignalingData());
    exit;
}
?>
