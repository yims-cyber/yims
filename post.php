<?php
header('Content-Type: application/json');

$file = 'broadcast.json';
$data = file_exists($file) ? json_decode(file_get_contents($file), true) : [
    'type' => 'status',
    'text' => '',
    'timestamp' => 0,
    'is_live' => false,
    'is_speaking' => false
];

if (isset($_POST['post']) && $_POST['post'] == 'yes') {
    if (isset($_POST['msg'])) {
        $data['text'] = $_POST['msg'];
        $data['type'] = $_POST['type'] ?? 'final';
    }

    if (isset($_POST['live_status'])) {
        $data['is_live'] = ($_POST['live_status'] === 'active');
    }

    if (isset($_POST['speaking'])) {
        $data['is_speaking'] = ($_POST['speaking'] === 'yes');
    }

    $data['timestamp'] = round(microtime(true) * 1000);
    file_put_contents($file, json_encode($data));
    echo json_encode(['status' => 'success']);
} else {
    // Return current state for polling
    echo json_encode($data);
}
?>
