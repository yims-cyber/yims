<?php
if (isset($_POST['post']) && $_POST['post'] == 'yes' && isset($_POST['msg'])) {
    $msg = $_POST['msg'];
    $timestamp = date('Y-m-d H:i:s');
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $logEntry = "[$timestamp] UA: $userAgent | MSG: $msg" . PHP_EOL;

    file_put_contents('transcriptions.log', $logEntry, FILE_APPEND);
    echo "Logged";
} else {
    echo "Invalid request";
}
?>
