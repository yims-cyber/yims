<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$file = 'broadcast.json';

// Function to safely read the file
function safe_read($file) {
    if (!file_exists($file)) return null;
    $fp = fopen($file, 'r');
    if (!$fp) return null;
    flock($fp, LOCK_SH);
    $content = file_get_contents($file);
    flock($fp, LOCK_UN);
    fclose($fp);
    return json_decode($content, true);
}

// Function to safely write the file
function safe_write($file, $data) {
    $fp = fopen($file, 'w');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

$data = safe_read($file) ?? [
    'type' => 'status',
    'text' => '',
    'timestamp' => 0,
    'is_live' => false,
    'is_speaking' => false
];

if (isset($_POST['post']) && $_POST['post'] == 'yes') {
    if (isset($_POST['msg'])) {
        $data['text'] = $_POST['msg'];
        $data['type'] = $_POST['type'] ?? 'final';
    }

    if (isset($_POST['live_status'])) {
        $data['is_live'] = ($_POST['live_status'] === 'active');
    }

    if (isset($_POST['speaking'])) {
        $data['is_speaking'] = ($_POST['speaking'] === 'yes');
    }

    $data['timestamp'] = round(microtime(true) * 1000);
    safe_write($file, $data);
    echo json_encode(['status' => 'success']);
} else {
    $data['server_time'] = round(microtime(true) * 1000);
    echo json_encode($data);
}
?>
