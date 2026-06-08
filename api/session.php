<?php
// api/session.php
require_once 'core.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$sessionId = sanitize($_POST['session_id'] ?? $_GET['session_id'] ?? '');
$sessionName = $_POST['session_name'] ?? 'Session sans nom';
$oratorName = $_POST['orator_name'] ?? 'Orateur Anonyme';

if ($action === 'start') {
    $sessions = atomic_read(SESSIONS_FILE) ?? [];
    $sessions[$sessionId] = [
        'id' => $sessionId,
        'name' => $sessionName,
        'orator' => $oratorName,
        'start_time' => time(),
        'participants' => 0,
        'status' => 'active'
    ];
    atomic_write(SESSIONS_FILE, $sessions);

    $liveData = [
        'is_live' => true,
        'is_speaking' => false,
        'text' => '',
        'type' => 'status',
        'timestamp' => round(microtime(true) * 1000)
    ];
    atomic_write(__DIR__ . "/../live_{$sessionId}.json", $liveData);
    echo json_encode(['status' => 'success', 'session' => $sessions[$sessionId]]);

} elseif ($action === 'stop') {
    $sessions = atomic_read(SESSIONS_FILE) ?? [];
    if (isset($sessions[$sessionId])) {
        // Save to history before deleting
        $history = atomic_read(HISTORY_FILE) ?? [];
        $sessions[$sessionId]['end_time'] = time();
        $sessions[$sessionId]['status'] = 'ended';
        $history[] = $sessions[$sessionId];
        atomic_write(HISTORY_FILE, array_slice($history, -50)); // Keep last 50

        // Notify auditors
        $liveFile = __DIR__ . "/../live_{$sessionId}.json";
        if (file_exists($liveFile)) {
            atomic_write($liveFile, ['is_live' => false, 'timestamp' => round(microtime(true) * 1000)]);
        }

        unset($sessions[$sessionId]);
        atomic_write(SESSIONS_FILE, $sessions);

        // Cleanup files
        @unlink(__DIR__ . "/../live_{$sessionId}.json");
        @unlink(__DIR__ . "/../signaling_{$sessionId}.json");
    }
    echo json_encode(['status' => 'stopped']);

} elseif ($action === 'list' || $action === 'get_sessions') {
    $sessions = atomic_read(SESSIONS_FILE) ?? [];
    echo json_encode([
        'sessions' => array_values($sessions),
        'server_time' => round(microtime(true) * 1000)
    ]);

} elseif ($action === 'history') {
    $history = atomic_read(HISTORY_FILE) ?? [];
    echo json_encode(['history' => array_reverse($history)]);

} elseif ($action === 'stats') {
    // Simple participant update (would be more accurate with WebSockets/Pulse)
    $sessions = atomic_read(SESSIONS_FILE) ?? [];
    if (isset($sessions[$sessionId])) {
        $sessions[$sessionId]['participants'] = (int)($_POST['count'] ?? $sessions[$sessionId]['participants']);
        atomic_write(SESSIONS_FILE, $sessions);
    }
    echo json_encode(['status' => 'updated']);
}
?>
