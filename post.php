<?php
if (isset($_POST['msg']) && $_POST['post'] == 'yes') {
    $msg = $_POST['msg'];
    $timestamp = date('Y-m-d H:i:s');
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $logEntry = "[$timestamp] [UA: $userAgent] $msg" . PHP_EOL;
    file_put_contents('transcriptions.log', $logEntry, FILE_APPEND);
    echo "Success";
} else {
    echo "Invalid request";
}
?>
