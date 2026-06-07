<?php
// api/broadcast.php
require_once 'core.php';

$sessionId = sanitize($_POST['session_id'] ?? '');
$msg = $_POST['msg'] ?? '';
$type = $_POST['type'] ?? 'interim';
$isSpeaking = ($_POST['speaking'] === 'yes');

if (!empty($sessionId)) {
    $liveFile = __DIR__ . "/../live_{$sessionId}.json";
    $data = [
        'is_live' => true,
        'is_speaking' => $isSpeaking,
        'text' => $msg,
        'type' => $type,
        'timestamp' => round(microtime(true) * 1000)
    ];
    atomic_write($liveFile, $data);
    echo json_encode(['status' => 'broadcasted']);
} else {
    echo json_encode(['error' => 'No session ID']);
}
?>
