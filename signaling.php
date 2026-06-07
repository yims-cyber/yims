<?php
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$role = $_GET['role'] ?? ''; // orator or auditor

$file = 'webrtc_signaling.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

    if ($action === 'push') {
        if ($role === 'orator') {
            $data['offer'] = $input['offer'] ?? $data['offer'];
            $data['orator_candidates'][] = $input['candidate'];
        } else {
            $data['answer'] = $input['answer'] ?? $data['answer'];
            $data['auditor_candidates'][] = $input['candidate'];
        }
        file_put_contents($file, json_encode($data));
        echo json_encode(['status' => 'success']);
    } elseif ($action === 'clear') {
        file_put_contents($file, json_encode(['offer' => null, 'answer' => null, 'orator_candidates' => [], 'auditor_candidates' => []]));
        echo json_encode(['status' => 'cleared']);
    }
} else {
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['offer' => null, 'answer' => null, 'orator_candidates' => [], 'auditor_candidates' => []]);
    }
}
?>
