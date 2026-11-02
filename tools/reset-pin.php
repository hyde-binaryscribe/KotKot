<?php
declare(strict_types=1);

/**
 * Code de la famille oublié ? Choisit un nouveau code et débloque les essais.
 *
 *   php tools/reset-pin.php 5678
 *
 * Sur Plesk : Outils > Tâches planifiées > « Exécuter un script PHP »
 * (script : tools/reset-pin.php, arguments : 5678, puis « Exécuter maintenant »),
 * ou en SSH depuis le dossier de KotKot.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../src/bootstrap.php';

$pin = $argv[1] ?? '';
if (!valid_pin($pin)) {
    fwrite(STDERR, "Usage : php tools/reset-pin.php 1234   (4 chiffres)\n");
    exit(1);
}
if (setting('pin_hash') === null) {
    fwrite(STDERR, "KotKot n'est pas encore installé : ouvrez le site pour le configurer.\n");
    exit(1);
}
set_setting('pin_hash', password_hash($pin, PASSWORD_DEFAULT));
db()->exec('DELETE FROM login_attempts');
echo "Nouveau code enregistré : $pin. Les téléphones déjà connectés le restent.\n";
