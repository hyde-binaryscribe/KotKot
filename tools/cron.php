<?php
declare(strict_types=1);

/**
 * Envoie les rappels (porte restée ouverte, soins à faire) sur les téléphones.
 * À lancer toutes les 5 minutes.
 *
 * Plesk : Outils > Tâches planifiées > Ajouter une tâche
 *   Type : « Exécuter un script PHP », script : <dossier KotKot>/tools/cron.php,
 *   Exécuter : toutes les 5 minutes (Cron : « 0,5,10,...,55 * * * * »)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/state.php';
require __DIR__ . '/../src/push.php';

$lock = fopen(KK_DATA . '/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // the previous run is still going
}
$report = run_all_reminders();
foreach ($report as $household => $keys) {
    echo date('c') . " foyer $household : " . implode(', ', $keys) . "\n";
}
flock($lock, LOCK_UN);
