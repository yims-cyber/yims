<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$action = $_GET['action'] ?? '';
$role = $_GET['role'] ?? '';
$sessionId = $_GET['session_id'] ?? $_POST['session_id'] ?? 'default';
$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId); // Sanitize

// Create a session-specific signaling file
$file = "signaling_{$sessionId}.json";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Read current session data or init
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [
        'offer' => null,
        'answers' => [], // Multiple answers for multiple auditors
        'orator_candidates' => [],
        'auditor_candidates' => [] // Map of auditor_id => candidates
    ];

    if ($action === 'push') {
        if ($role === 'orator') {
            if (isset($input['offer'])) $data['offer'] = $input['offer'];
            if (isset($input['candidate']) && $input['candidate'] !== null) {
                $data['orator_candidates'][] = $input['candidate'];
            }
        } else if ($role === 'auditor') {
            $auditorId = $input['auditor_id'] ?? 'anon';
            if (isset($input['answer'])) {
                $data['answers'][$auditorId] = $input['answer'];
            }
            if (isset($input['candidate']) && $input['candidate'] !== null) {
                $data['auditor_candidates'][$auditorId][] = $input['candidate'];
            }
        }
        file_put_contents($file, json_encode($data));
        echo json_encode(['status' => 'success']);
    } elseif ($action === 'clear') {
        if (file_exists($file)) unlink($file);
        echo json_encode(['status' => 'cleared']);
    }
} else {
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['offer' => null, 'answers' => [], 'orator_candidates' => [], 'auditor_candidates' => []]);
    }
}
?>
