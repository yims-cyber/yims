<?php
if (isset($_POST['msg']) && isset($_POST['post']) && $_POST['post'] == 'yes') {
    $msg = $_POST['msg'];
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $msg" . PHP_EOL;
    file_put_contents('transcriptions.log', $logEntry, FILE_APPEND);
    echo "Success";
} else {
    echo "Error: Missing parameters";
}
?>
