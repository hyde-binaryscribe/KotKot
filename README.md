# KotKot 🥚

L'application du poulailler familial : **œufs, tâches, poules et porte**, sur le téléphone de toute la famille, de 8 à 80 ans.

- Très gros boutons, une icône et un mot, texte en 3 tailles, couleurs bien contrastées.
- Un **code familial à 4 chiffres** ; chacun touche son prénom (« Fait par Mamie à 8 h 05 »).
- Pas de « Êtes-vous sûr ? » : on agit, et un bouton **Annuler** reste 8 secondes.
- **Marche sans réseau** au fond du jardin : les clics sont gardés sur le téléphone et envoyés dès que ça capte.
- S'installe comme une appli (PWA) : « Ajouter à l'écran d'accueil ».

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
src/           ← code serveur (base, règles, actions)
data/          ← base + photos (jamais publiés, jamais sur GitHub)
tests/         ← php tests/api_test.php
docs/          ← charte graphique
```

## Installer sur Plesk (extension Git)

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
php tests/api_test.php              # 24 vérifications de bout en bout
```

## Licence des polices

Atkinson Hyperlegible, © Braille Institute of America — licence SIL Open Font 1.1 (`public/assets/fonts/OFL.txt`).
