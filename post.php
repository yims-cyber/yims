<?php
if (isset($_POST['msg']) && isset($_POST['post'])) {
    $message = $_POST['msg'];
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents('transcriptions.log', $logEntry, FILE_APPEND);
    echo "Success";
} else {
    http_response_code(400);
    echo "Invalid request";
}
?>
