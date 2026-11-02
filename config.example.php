<?php
// Copiez ce fichier en config.php (à côté de lui) et adaptez-le. Tout est facultatif.
return [
    // Adresse publique du site (pour les liens envoyés par e-mail).
    // 'base_url' => 'https://poules.exemple.fr/',

    // Expéditeur des e-mails (mot de passe oublié). Une adresse de votre domaine.
    // 'mail_from' => 'kotkot@exemple.fr',

    // Contact affiché aux services de notifications (Google, Apple, Mozilla).
    // 'push_subject' => 'mailto:kotkot@exemple.fr',

    // Autoriser la création de nouveaux poulaillers par n'importe qui.
    // false = seuls les comptes existants (ou la famille déjà invitée) entrent.
    'registration' => true,

    // Abonnement Premium. false = tout est inclus pour tout le monde (votre propre serveur).
    'billing' => false,

    // Durée de l'essai Premium offert à la création d'un poulailler (si billing = true).
    'trial_days' => 30,

    // « Continuer avec Google » (facultatif) — voir docs/installation-plesk.md, étape 6 ter.
    // Adresse de retour à déclarer chez Google : https://VOTRE-SITE/oauth-google.php
    // 'google_client_id'     => '123456789-xxxx.apps.googleusercontent.com',
    // 'google_client_secret' => 'GOCSPX-xxxx',

    // « Continuer avec Apple » (facultatif, compte Apple Developer nécessaire).
    // Adresse de retour à déclarer chez Apple : https://VOTRE-SITE/oauth-apple.php
    // 'apple_client_id'   => 'fr.exemple.kotkot',          // le « Services ID »
    // 'apple_team_id'     => 'ABCDE12345',
    // 'apple_key_id'      => 'XYZ1234567',
    // 'apple_private_key' => __DIR__ . '/data/AuthKey_XYZ1234567.p8',  // hors du dossier public
];
