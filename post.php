<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = isset($_POST['msg']) ? $_POST['msg'] : '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$userAgent] $msg" . PHP_EOL;
    file_put_contents('transcriptions.log', $logEntry, FILE_APPEND);
    echo "Logged";
} else {
    http_response_code(405);
    echo "Method Not Allowed";
}
?>
