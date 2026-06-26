<?php
if (isset($_POST['msg'])) {
    $msg = $_POST['msg'];
    $log = date('Y-m-d H:i:s') . " - " . $msg . PHP_EOL;
    file_put_contents('transcriptions.log', $log, FILE_APPEND);
    echo json_encode(['status' => 'success']);
}
?>