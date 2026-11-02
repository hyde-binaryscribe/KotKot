# KotKot 🥚

L'application du poulailler familial : **œufs, tâches, poules et porte**, sur le téléphone de toute la famille, de 8 à 80 ans.

- Très gros boutons, une icône et un mot, texte en 3 tailles, couleurs bien contrastées.
- Un **code familial à 4 chiffres** ; chacun touche son prénom (« Fait par Mamie à 8 h 05 »).
- Pas de « Êtes-vous sûr ? » : on agit, et un bouton **Annuler** reste 8 secondes.
- **Marche sans réseau** au fond du jardin : les clics sont gardés sur le téléphone et envoyés dès que ça capte.
- S'installe comme une appli (PWA) : « Ajouter à l'écran d'accueil ».

## Nouveautés V2.6

- **La Gazette du poulailler** : chaque jour, les vraies nouvelles du poulailler racontées façon presse à scandale (« Grève surprise au pondoir », « Un œuf à double jaune ! »…). Plusieurs versions par article, texte modifiable, et une belle image à partager (WhatsApp, SMS, réseaux sociaux).

## Nouveautés V2.5

- **Qualité des œufs** : on note les mauvais œufs (fêlé, sale, coquille molle…) avec un commentaire ; « 4 bons · 1 mauvais » et bilan sur 30 jours.
- **Photo de profil** : la plus récente par défaut, ou celle qu'on choisit (« En profil ») ; **recadrage** au doigt.

## Nouveautés V2.3

- **Toutes les volailles** : poules, cailles, canards, oies, dindes, pintades ; les œufs sont comptés par espèce.
- **Date de naissance et d'arrivée** (facultatives) : l'âge est calculé, et affiché sur chaque photo de l'album.
- Les photos sont **réduites sur le téléphone** avant l'envoi (rapide en 4G, aucune limite serveur à régler).

## Nouveautés V2.2

- **Chacun son compte** (e-mail, **Google** ou **Apple**) : on retrouve son poulailler sur n'importe quel appareil, sans code.
- **Synchro en direct** entre les téléphones de la famille (quelques secondes).
- **Calendrier des œufs** : on touche un jour passé et on indique les œufs ramassés.

## Nouveautés V2

- **Comptes et poulaillers** : chacun peut créer son poulailler en ligne ; un compte peut en gérer plusieurs ; les familles sont totalement séparées (une base de données par poulailler).
- **Inviter la famille** par un code ou un lien ; chacun garde le code familial à 4 chiffres.
- **Carnet santé** : vermifuge, poux, vaccins, soins, avec la date du prochain traitement et un bandeau d'alerte.
- **Aide à la race** : 6 questions simples → les 3 races les plus probables.
- **Rappels même appli fermée** (porte restée ouverte, soins) grâce à une tâche planifiée.
- Mot de passe oublié, **export** de toutes les données, **suppression** du compte (RGPD).
- Formule gratuite / Premium prête, désactivée par défaut (`config.php`).

## Ce que fait la V1

| Écran | Contenu |
|---|---|
| **Accueil** | État de la porte en bandeau, puis 3 gros boutons : Œufs, Tâches, Poules |
| **Porte** | Bouton unique « J'ai ouvert / J'ai fermé », lever et coucher du soleil, rappel *X* min après le coucher, historique |
| **Œufs** | +1 / −1, total du jour, 7 et 30 derniers jours, graphique 14 jours |
| **Tâches** | Tâches du jour et de la semaine, qui l'a fait et à quelle heure, retards ; on peut en ajouter et en retirer |
| **Poules** | Autant de poules que l'on veut, fiche (race, arrivée, signe distinctif, couleur des œufs), « à surveiller », carnet de notes |
| **Album photo** | Photos datées de chaque poule, comparaison « Avant / Maintenant » pour voir la croissance |
| **Réglages** | Taille du texte, délai du rappel, ville (soleil), personnes du foyer, code familial |

**Rappels** : quand la porte est encore ouverte après le coucher du soleil, le bandeau passe en orange et, si on l'a autorisé, le téléphone affiche une notification tant que KotKot est ouvert ou en arrière-plan. Les vraies notifications « push » (appli fermée) sont prévues en V2.

## Technique

- PHP 8.1+ (testé en 8.3) avec les extensions `pdo_sqlite` et `gd` — présentes par défaut sur Plesk.
- Base **SQLite** : un seul fichier `data/kotkot.sqlite`, rien à configurer.
- JavaScript simple, sans framework ni étape de compilation.

```
public/        ← racine web (index.php, api.php, sw.js, assets/)
src/           ← code serveur (comptes, foyers, actions, rappels)
data/          ← central.sqlite + foyers/<n>/ (base + photos), jamais publiés ni sur GitHub
tools/         ← cron.php (rappels, toutes les 5 min), reset-pin.php
config.php     ← réglages du site (copie de config.example.php, jamais sur GitHub)
tests/         ← php tests/api_test.php
docs/          ← charte graphique
```

## Installer sur Plesk (extension Git)

👉 **Guide complet pas à pas, avec dépannage : [docs/installation-plesk.md](docs/installation-plesk.md)**

En bref :


1. **Sites et domaines › votre domaine › Git › Ajouter un dépôt**
   - Dépôt distant : `https://github.com/hyde-binaryscribe/kotkot.git` (dépôt privé : ajoutez la clé SSH que Plesk affiche dans *GitHub › Settings › Deploy keys*, et utilisez l'adresse `git@github.com:hyde-binaryscribe/kotkot.git`).
   - Branche : `main`. Mode de déploiement : **automatique**.
   - Chemin du serveur : par exemple `/httpdocs/kotkot` (ou un sous-domaine `poules.mondomaine.fr`).
2. **Hébergement et DNS › Paramètres d'hébergement** : réglez la **racine du document** sur le dossier `public` (ex. `httpdocs/kotkot/public`). *Si ce n'est pas possible, le fichier `.htaccess` à la racine redirige vers `public/` et bloque `data/` et `src/`.*
3. **PHP** : version 8.1 ou plus.
4. **HTTPS** : activez le certificat **Let's Encrypt** (indispensable pour installer l'appli sur le téléphone et pour la position GPS).
5. Le dossier `data/` doit être accessible en écriture par PHP (c'est le cas par défaut avec Plesk). Pour le ranger ailleurs, définissez la variable d'environnement `KOTKOT_DATA`.
6. Ouvrez le site : l'écran **Bienvenue** demande les prénoms, le code familial et la ville. C'est tout.

À chaque `git push` sur `main`, Plesk met le site à jour tout seul. Les données du dossier `data/` ne sont jamais touchées.

**Sauvegarde** : pensez à inclure le dossier `data/` dans les sauvegardes Plesk (base + photos).

## Développer en local

```bash
php -S localhost:8000 -t public     # puis http://localhost:8000
php tests/api_test.php              # parcours complets : comptes, familles, cloisonnement…
php tests/push_test.php             # chiffrement des rappels (exemple officiel RFC 8291)
php tests/reminders_test.php        # rappels porte/santé, passage de la V1 à la V2
php tests/accounts_test.php         # comptes membres, Google/Apple (faux fournisseur), synchro
```

## Licence des polices

Atkinson Hyperlegible, © Braille Institute of America — licence SIL Open Font 1.1 (`public/assets/fonts/OFL.txt`).
