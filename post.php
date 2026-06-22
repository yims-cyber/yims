<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = $_POST['msg'] ?? '';
    // Simply acknowledge receipt
    echo json_encode(['status' => 'success', 'received' => $msg]);
    exit;
}

echo json_encode(['status' => 'waiting']);
?>
