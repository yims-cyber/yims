<?php
if (isset($_POST['log'])) {
    $log = "[" . date('Y-m-d H:i:s') . "] " . $_POST['log'] . PHP_EOL;
    file_put_contents('voix.log', $log, FILE_APPEND);
    echo "logged";
}
?>
