# Installer KotKot sur Plesk — pas à pas

Durée : 15 à 20 minutes, une seule fois. Ensuite, chaque mise à jour poussée sur GitHub arrive toute seule sur le site.

Les noms des menus correspondent à Plesk en français (version Obsidian). Selon votre hébergeur, un bouton peut être placé un peu différemment, mais les mots restent les mêmes.

Dans ce guide, l'adresse d'exemple est **`poules.exemple.fr`** : remplacez-la par la vôtre.

---

## 0. Ce qu'il faut avant de commencer

- [ ] Un accès à Plesk avec l'extension **Git** (on la voit dans le menu du domaine).
- [ ] Un nom de domaine qui pointe vers ce Plesk (par exemple `exemple.fr`).
- [ ] Un accès au dépôt GitHub **hyde-binaryscribe/KotKot**.

> **Conseil** : installez KotKot sur un **sous-domaine** (`poules.exemple.fr`). Il aura ainsi son propre dossier, son propre certificat et ses propres réglages, sans toucher au reste du site.

---

## 1. Créer le sous-domaine

1. **Sites Web et domaines** › **Ajouter un sous-domaine**.
2. Nom du sous-domaine : `poules`. Domaine parent : `exemple.fr`.
3. Racine du document : laissez la valeur proposée (`poules.exemple.fr`), on la changera à l'étape 4.
4. Cochez **Sécuriser avec un certificat SSL/TLS Let's Encrypt** si la case est proposée.
5. **OK**.

> Le nom DNS peut mettre quelques minutes à fonctionner. Si Let's Encrypt échoue, attendez 10 minutes et refaites l'étape 2.

---

## 2. Activer le HTTPS (indispensable)

Le HTTPS est obligatoire pour installer l'appli sur le téléphone, recevoir les rappels et utiliser la position GPS.

1. Dans le bloc de `poules.exemple.fr` : **Certificats SSL/TLS**.
2. **Installer** un certificat gratuit **Let's Encrypt** (cochez aussi « www » si proposé).
3. Revenez sur le domaine : **Paramètres d'hébergement** › cochez **Redirection permanente SEO-safe 301 de HTTP vers HTTPS** › **OK**.

---

## 3. Brancher le dépôt GitHub

1. Dans le bloc de `poules.exemple.fr` : **Git**.
2. Choisissez **Dépôt Git distant**.
3. **URL du dépôt** :
   - Dépôt **privé** (cas le plus probable) : `git@github.com:hyde-binaryscribe/KotKot.git`
   - Dépôt **public** : `https://github.com/hyde-binaryscribe/KotKot.git`
4. **Si le dépôt est privé**, Plesk affiche une **clé SSH publique** (un long texte qui commence par `ssh-rsa` ou `ssh-ed25519`).
   1. Copiez-la.
   2. Sur GitHub : dépôt **KotKot** › **Settings** › **Deploy keys** › **Add deploy key**.
   3. Titre : `Plesk`. Collez la clé. **Ne cochez pas** « Allow write access ». **Add key**.
   4. Revenez dans Plesk.
5. **Mode de déploiement** : **Automatique**.
6. **Chemin du serveur** : le dossier du sous-domaine, par exemple `/poules.exemple.fr`.
7. **OK**. Plesk télécharge le code ; la page Git affiche la branche **main** et le dernier changement.

### 3 bis. Mises à jour automatiques (webhook)

Pour que chaque modification arrive seule sur le site :

1. Dans la page **Git** de Plesk, ouvrez les **paramètres du dépôt** et copiez l'**URL du webhook**.
2. Sur GitHub : dépôt **KotKot** › **Settings** › **Webhooks** › **Add webhook**.
3. **Payload URL** : collez l'URL. **Content type** : `application/json`. Événement : **Just the push event**. **Add webhook**.

> Sans webhook, cliquez sur **Récupérer maintenant** puis **Déployer maintenant** dans Plesk après chaque mise à jour.

---

## 4. Faire pointer le site vers le dossier `public`

C'est l'étape la plus importante pour la sécurité : seul le dossier `public` doit être visible sur internet. La base de données et les photos (`data/`) restent à côté, hors de portée.

1. Dans le bloc de `poules.exemple.fr` : **Paramètres d'hébergement**.
2. **Racine du document** : ajoutez `/public` à la fin, par exemple `poules.exemple.fr/public`.
3. **OK**.

> Si votre hébergeur ne permet pas de changer la racine, KotKot fonctionne quand même grâce au fichier `.htaccess` placé à la racine, qui redirige vers `public` et bloque `data`, `src` et `tools`. Mais cette protection ne marche qu'avec Apache (voir l'étape 6) : préférez vraiment la racine `public`.

---

## 5. Régler PHP

1. Dans le bloc de `poules.exemple.fr` : **Paramètres PHP**.
2. **Version de PHP** : **8.1 ou plus récente** (8.3 conseillée).
3. **Gestionnaire** : « FPM servi par Apache » ou « FPM servi par nginx ».
4. Dans la liste des réglages, mettez :

   | Réglage | Valeur | Pourquoi |
   |---|---|---|
   | `upload_max_filesize` | `16M` | photos prises au téléphone |
   | `post_max_size` | `20M` | idem |
   | `memory_limit` | `256M` | redimensionner les photos |

5. **OK**.

**Extensions nécessaires** : `pdo_sqlite` (la base) et `gd` (les photos), plus `exif` en option (photos à l'endroit et datées automatiquement). Elles sont presque toujours actives. Si l'étape 7 affiche une erreur « could not find driver », demandez à votre hébergeur (ou dans **Outils et paramètres** › **Paramètres PHP** si vous êtes administrateur) d'activer `pdo_sqlite`.

---

## 6. Apache et nginx (à vérifier seulement en cas de souci)

Dans **Apache et nginx**, laissez le **mode proxy** coché (réglage par défaut) : les fichiers `.htaccess` de KotKot sont alors bien pris en compte.

---

## 6 bis. Configuration et rappels (V2)

### Le fichier `config.php`

Dans le **Gestionnaire de fichiers**, dossier de KotKot : copiez `config.example.php` en **`config.php`** et modifiez au moins :

```php
'base_url'  => 'https://poules.exemple.fr/',   // pour les liens envoyés par e-mail
'mail_from' => 'kotkot@exemple.fr',            // une adresse de VOTRE domaine
```

- `registration` : `true` pour que d'autres familles puissent créer leur poulailler, `false` pour garder le site pour vous.
- `billing` : laissez `false` tant que le paiement en ligne n'est pas branché (tout est inclus).

`config.php` n'est jamais envoyé sur GitHub et les mises à jour n'y touchent pas.

> **E-mails** (mot de passe oublié) : KotKot utilise l'envoi de mail de PHP. Sur Plesk, il fonctionne si le service de messagerie est actif pour le domaine (**Messagerie** › **Paramètres de messagerie** activés). Pour éviter les indésirables, vérifiez que le domaine a bien ses enregistrements **SPF** et **DKIM** (Plesk › **Paramètres de messagerie** › DKIM).

### La tâche planifiée des rappels

C'est elle qui envoie les rappels sur les téléphones (porte restée ouverte, soins à faire), même quand l'appli est fermée.

1. **Sites Web et domaines** › **Tâches planifiées** (ou **Outils et paramètres** › **Tâches planifiées**) › **Ajouter une tâche**.
2. Type de tâche : **Exécuter un script PHP**.
3. Chemin du script : `poules.exemple.fr/tools/cron.php` (le dossier de KotKot, puis `tools/cron.php`).
4. Version de PHP : la même qu'à l'étape 5.
5. Exécuter : **Cron style**, et tapez `*/5 * * * *` (toutes les 5 minutes).
6. Notifier : **Ne pas notifier** (ou « en cas d'erreur »).
7. **OK**, puis **Exécuter maintenant** une fois : aucun message = tout va bien.

---

## 6 ter. « Continuer avec Google » et « Continuer avec Apple » (facultatif)

Chacun pourra créer son compte et se connecter en un clic, sans mot de passe. Les boutons n'apparaissent que lorsque les clés sont dans `config.php`. Vous pouvez activer l'un sans l'autre.

### Google (gratuit, 10 minutes)

1. Allez sur **console.cloud.google.com** avec votre compte Google, créez un projet (par ex. « KotKot »).
2. **API et services** › **Écran de consentement OAuth** : type **Externe**, nom de l'appli « KotKot », votre e-mail d'assistance, le logo si vous voulez. Pas besoin d'ajouter d'autorisations particulières (e-mail et profil suffisent). Puis **Publier l'application** (statut « En production »).
3. **API et services** › **Identifiants** › **Créer des identifiants** › **ID client OAuth** :
   - Type : **Application Web** ;
   - **URI de redirection autorisés** : `https://poules.exemple.fr/oauth-google.php`.
4. Copiez l'**ID client** et le **Code secret du client** dans `config.php` :
   ```php
   'google_client_id'     => '…apps.googleusercontent.com',
   'google_client_secret' => 'GOCSPX-…',
   ```

### Apple (compte Apple Developer, 99 € par an)

1. Sur **developer.apple.com** › **Certificates, Identifiers & Profiles** :
   - **Identifiers** › **App IDs** : créez-en un (par ex. `fr.exemple.kotkot.app`) avec la case **Sign In with Apple**.
   - **Identifiers** › **Services IDs** : créez-en un (par ex. `fr.exemple.kotkot`), cochez **Sign In with Apple** › **Configure** : domaine `poules.exemple.fr`, **Return URL** `https://poules.exemple.fr/oauth-apple.php`.
   - **Keys** : créez une clé avec **Sign In with Apple**, téléchargez le fichier `AuthKey_XXXXXXXXXX.p8` (une seule fois possible !) et notez son **Key ID**.
2. Envoyez le fichier `.p8` dans le dossier **`data/`** de KotKot (jamais dans `public/`).
3. Dans `config.php` :
   ```php
   'apple_client_id'   => 'fr.exemple.kotkot',   // le Services ID
   'apple_team_id'     => 'ABCDE12345',          // en haut à droite du compte développeur
   'apple_key_id'      => 'XXXXXXXXXX',
   'apple_private_key' => __DIR__ . '/data/AuthKey_XXXXXXXXXX.p8',
   ```

> Avec Apple, la personne peut choisir de **masquer son e-mail** : KotKot reçoit alors une adresse `…@privaterelay.appleid.com`. Pour que les e-mails « mot de passe oublié » y arrivent, déclarez votre adresse d'envoi (`mail_from`) dans **Services ID › Sign In with Apple › Email Sources**.

---

## 7. Premier lancement

1. Sur votre téléphone, ouvrez **`https://poules.exemple.fr`**.
2. L'écran **Bienvenue** s'affiche : touchez **Créer mon poulailler**, puis indiquez :
   - votre **e-mail** et un **mot de passe** (c'est le compte du responsable : pour retrouver le poulailler, exporter, gérer l'abonnement) ;
   - le **nom** du poulailler ;
   - les **prénoms** de ceux qui s'occupent des poules (4 au départ, d'autres plus tard dans Réglages) ;
   - le **code de la famille** à 4 chiffres, tapé deux fois ;
   - **Utiliser ma position** (ou tapez la ville et les coordonnées) pour les heures du soleil.
3. **C'est parti** : vous arrivez sur l'accueil.
4. **Réglages** › **Autoriser les rappels sur ce téléphone**.

**Vérification de sécurité** (30 secondes) : ouvrez `https://poules.exemple.fr/data/kotkot.sqlite` et `https://poules.exemple.fr/tools/reset-pin.php`. Les deux doivent afficher une erreur (404 ou 403), **jamais** un téléchargement.

---

## 8. Installer l'appli sur chaque téléphone

**Android (Chrome)** : ouvrez l'adresse › menu **⋮** › **Installer l'application** (ou **Ajouter à l'écran d'accueil**).

**iPhone (Safari)** : ouvrez l'adresse › bouton **Partager** › **Sur l'écran d'accueil**.
> Sur iPhone, les rappels ne fonctionnent que si KotKot a été ajouté à l'écran d'accueil et ouvert depuis l'icône (iOS 16.4 ou plus récent).

Pour inviter la famille : **Réglages** › **Inviter la famille** › **Envoyer l'invitation** (SMS, WhatsApp, e-mail…). Sur le téléphone du proche : ouvrir le lien (ou « Rejoindre ma famille » et taper le code à 8 caractères), toucher son prénom, taper le code de la famille. Le téléphone reste connecté (plus d'un an).

> **Vous aviez déjà la V1 ?** Rien à refaire : à la mise à jour, vos données deviennent le poulailler n° 1 et les téléphones restent connectés. Dans **Réglages**, utilisez **Devenir responsable du poulailler** pour y attacher votre e-mail.

---

## 9. Sauvegardes (à ne pas oublier)

Les poules, les œufs, les notes et les photos sont dans le dossier `data/` du serveur. **GitHub n'en garde pas de copie.**

1. **Sites Web et domaines** › **Gestionnaire de sauvegarde** › **Planifier**.
2. **Activer** la sauvegarde programmée : **chaque semaine**, contenu **Configuration et contenu** (les fichiers du domaine incluent `data/`).
3. Conservez au moins **4 sauvegardes**. Si possible, envoyez-les vers un **stockage distant** (FTP, Google Drive, etc.).

Pour récupérer à la main : **Gestionnaire de fichiers** › dossier du sous-domaine › `data` › téléchargez `kotkot.sqlite` et le dossier `photos`.

> Les mises à jour par Git ne touchent jamais au dossier `data/`.

---

## 10. Dépannage

| Ce que vous voyez | Cause probable | Que faire |
|---|---|---|
| Page « 403 » ou liste de fichiers | La racine ne pointe pas sur `public` | Étape 4 |
| Page blanche ou « Erreur 500 » | Version de PHP trop ancienne, extension manquante | Étape 5, puis **Journaux** du domaine |
| « could not find driver » dans les journaux | `pdo_sqlite` désactivé | Étape 5, extensions |
| « Petit souci technique, réessayez » | Erreur côté serveur | **Journaux** : cherchez une ligne qui commence par `KotKot:` et envoyez-la |
| « unable to open database file » | Le dossier `data/` n'est pas modifiable | **Gestionnaire de fichiers** › `data` › **Modifier les permissions** : droits d'écriture pour le propriétaire |
| La photo ne s'envoie pas | Photo trop lourde | Étape 5 (`upload_max_filesize`, `post_max_size`) |
| Le site ne se met pas à jour | Webhook absent ou en erreur | Étape 3 bis, ou **Déployer maintenant** dans Plesk |
| Git : « Permission denied (publickey) » | Clé de déploiement absente | Étape 3, point 4 |
| Pas de bouton « Installer l'application » | Le site n'est pas en HTTPS | Étape 2 |
| Les rappels n'arrivent pas | Tâche planifiée absente, ou rappels non activés sur le téléphone | Étape 6 bis ; puis Réglages › Rappels › **Essayer** |
| L'e-mail « mot de passe oublié » n'arrive pas | Messagerie du domaine inactive, ou classé en indésirable | Étape 6 bis (E-mails) |
| « Trop d'essais. Réessayez dans … » | 5 codes faux de suite | Attendez le temps indiqué |

### Code de la famille oublié

Le code n'est stocké que sous forme chiffrée : personne ne peut le relire, mais on peut en choisir un nouveau.

- **Le plus simple** : le responsable du poulailler, connecté avec son e-mail : **Réglages** › **Mon compte** › **Code de la famille oublié ?**
- **Sans SSH** : **Tâches planifiées** › **Ajouter une tâche** › **Exécuter un script PHP**.
  - Chemin du script : `poules.exemple.fr/tools/reset-pin.php`
  - Arguments : le nouveau code, par exemple `5678` (s'il y a plusieurs poulaillers : le code d'invitation du poulailler, un espace, puis le nouveau code)
  - Version de PHP : la même qu'à l'étape 5
  - **Exécuter maintenant**, puis supprimez la tâche.
- **Avec SSH** : depuis le dossier de KotKot, `php tools/reset-pin.php 5678`.

Les téléphones déjà connectés le restent ; seuls les nouveaux devront taper le nouveau code.

---

## Récapitulatif

- [ ] Sous-domaine créé
- [ ] HTTPS Let's Encrypt + redirection
- [ ] Dépôt Git branché (+ clé de déploiement si privé)
- [ ] Webhook GitHub
- [ ] Racine du document → `/public`
- [ ] PHP 8.1+ et tailles d'envoi
- [ ] Écran Bienvenue rempli
- [ ] Vérification de sécurité de `data/`
- [ ] Appli installée sur les téléphones
- [ ] `config.php` créé (adresse du site, expéditeur des e-mails)
- [ ] Tâche planifiée `tools/cron.php` toutes les 5 minutes
- [ ] (Facultatif) Clés Google et/ou Apple dans `config.php`
- [ ] Sauvegarde hebdomadaire programmée
