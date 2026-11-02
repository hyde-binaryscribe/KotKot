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
];
