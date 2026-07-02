<?php
if (isset($_POST['msg']) && isset($_POST['post']) && $_POST['post'] === 'yes') {
    $msg = $_POST['msg'];
    $timestamp = date('Y-m-d H:i:s');
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';

    $logEntry = "[$timestamp] [$userAgent] $msg" . PHP_EOL;

    file_put_contents('transcriptions.log', $logEntry, FILE_APPEND);
    echo "Logged";
} else {
    echo "Invalid request";
}
?>
