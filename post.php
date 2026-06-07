<?php
header('Content-Type: application/json');

if (isset($_POST['msg']) && $_POST['post'] == 'yes') {
    $data = [
        'type' => $_POST['type'] ?? 'final',
        'text' => $_POST['msg'],
        'timestamp' => round(microtime(true) * 1000)
    ];
    file_put_contents('broadcast.json', json_encode($data));
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
