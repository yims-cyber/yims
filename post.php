<?php
header("Access-Control-Allow-Origin: *");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = $_POST['msg'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$userAgent] $msg" . PHP_EOL;
    file_put_contents('transcriptions.log', $logEntry, FILE_APPEND);
    echo "Logged";
}
?>