<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post']) && $_POST['post'] === 'yes') {
    $msg = $_POST['msg'] ?? '';
    if ($msg !== '') {
        $timestamp = date('Y-m-d H:i:s');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $logEntry = "[$timestamp] [$userAgent] $msg" . PHP_EOL;
        file_put_contents('transcriptions.log', $logEntry, FILE_APPEND);
        echo "Logged";
    }
}
?>
