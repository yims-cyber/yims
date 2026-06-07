<?php
/**
 * Pont de compatibilité pour Orateur Ultra V5
 * Redirige les appels legacy vers la nouvelle structure api/
 */
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'get_sessions' || $action === 'list') {
    require_once 'api/session.php';
} elseif ($action === 'broadcast') {
    require_once 'api/broadcast.php';
} elseif ($action === 'start_session') {
    $_POST['action'] = 'start';
    require_once 'api/session.php';
} elseif ($action === 'stop_session') {
    $_POST['action'] = 'stop';
    require_once 'api/session.php';
} elseif ($action === 'hard_cleanup') {
    // Nettoyage global
    foreach (glob("live_*.json") as $f) @unlink($f);
    foreach (glob("signaling_*.json") as $f) @unlink($f);
    @unlink("sessions.json");
    @unlink("history.json");
    echo json_encode(['status' => 'hard_cleanup_done']);
} else {
    echo json_encode(['error' => 'Action non reconnue']);
}
?>
