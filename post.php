<?php
/**
 * Simple broadcast handler for Orateur Ultra
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = isset($_POST['msg']) ? $_POST['msg'] : '';
    $isPost = isset($_POST['post']) && $_POST['post'] === 'yes';

    if ($isPost && !empty($msg)) {
        // In a real scenario, this would write to a file or database for auditors
        file_put_contents('broadcast.json', json_encode([
            'last_msg' => $msg,
            'timestamp' => time()
        ]));
        echo json_encode(['status' => 'success', 'received' => $msg]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Only POST allowed']);
}
