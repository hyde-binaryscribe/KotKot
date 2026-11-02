<?php
declare(strict_types=1);

/**
 * Code de la famille oublié ? Choisit un nouveau code pour un foyer et débloque les essais.
 *
 *   php tools/reset-pin.php 5678                 (s'il n'y a qu'un foyer)
 *   php tools/reset-pin.php ABCD2345 5678        (code d'invitation du foyer, puis nouveau code)
 *
 * Sur Plesk : Tâches planifiées > « Exécuter un script PHP », arguments comme ci-dessus,
 * puis « Exécuter maintenant » et supprimer la tâche. Ou en SSH depuis le dossier de KotKot.
 *
 * Le responsable du foyer peut aussi le faire lui-même : Réglages > Mon compte.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';

$args = array_slice($argv, 1);
$pin = array_pop($args) ?? '';
if (!valid_pin($pin)) {
    fwrite(STDERR, "Usage : php tools/reset-pin.php [CODE-INVITATION] 1234\n");
    exit(1);
}
if ($args) {
    $st = central()->prepare('SELECT id FROM households WHERE invite_code = ? AND deleted_at IS NULL');
    $st->execute([normalize_invite_code($args[0])]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
} else {
    $ids = central()->query('SELECT id FROM households WHERE deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids) > 1) {
        fwrite(STDERR, "Il y a plusieurs foyers : indiquez le code d'invitation du foyer avant le nouveau code.\n");
        exit(1);
    }
}
if (count($ids) !== 1) {
    fwrite(STDERR, "Foyer introuvable.\n");
    exit(1);
}
use_household((int) $ids[0]);
if (setting('pin_hash') === null) {
    fwrite(STDERR, "Ce foyer n'est pas encore configuré.\n");
    exit(1);
}
set_setting('pin_hash', password_hash($pin, PASSWORD_DEFAULT));
central()->prepare('DELETE FROM login_attempts WHERE key LIKE ?')->execute(['pin:' . (int) $ids[0] . ':%']);
echo "Nouveau code enregistré : $pin. Les téléphones déjà connectés le restent.\n";
