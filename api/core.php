<?php
// api/core.php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

define('SESSIONS_FILE', __DIR__ . '/../sessions.json');
define('HISTORY_FILE', __DIR__ . '/../history.json');

function atomic_write($file, $data) {
    $fp = fopen($file, 'c+');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function atomic_read($file) {
    if (!file_exists($file)) return null;
    $fp = fopen($file, 'r');
    $data = null;
    if (flock($fp, LOCK_SH)) {
        $content = stream_get_contents($fp);
        $data = json_decode($content, true);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $data;
}

function sanitize($input) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $input);
}
?>
