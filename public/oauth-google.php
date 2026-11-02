<?php
declare(strict_types=1);

// Retour de « Continuer avec Google » : adresse à déclarer chez le fournisseur.
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/state.php';
require __DIR__ . '/../src/actions.php';
require __DIR__ . '/../src/accounts.php';
require __DIR__ . '/../src/push.php';
require __DIR__ . '/../src/oauth.php';

header('Cache-Control: no-store');
oauth_callback('google');
