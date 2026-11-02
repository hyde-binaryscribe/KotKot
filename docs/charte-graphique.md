# Charte graphique KotKot

**L'idée** : un carnet de ferme clair et franc. Chaque écran doit pouvoir être lu par un enfant de 8 ans et par une personne de 80 ans, en plein soleil, au fond du jardin, avec des mains occupées.

## 1. Principes

1. **Un écran, une action principale** : le bouton le plus important est le plus gros, en jaune.
2. **Toujours une icône et un mot** : jamais d'icône seule, jamais de jargon (« Enregistrer », pas « Valider la saisie »).
3. **Annuler plutôt que confirmer** : on agit tout de suite, un bandeau « Annuler » reste 8 s. On ne demande confirmation que pour ce qui ne peut pas être annulé (supprimer une photo ou une note).
4. **Lisible d'abord** : le texte fait au moins 18 px, avec 3 tailles au choix (18, 21 et 25 px).
5. **Facile à toucher** : chaque cible fait au moins 48 px de haut, et 56 à 112 px pour les actions principales.
6. **La couleur n'est jamais seule** : l'état est toujours écrit en toutes lettres (« Porte fermée », « À surveiller »).

## 2. Couleurs

| Nom | Code | Rôle |
|---|---|---|
| **Jaune d'œuf** | `#F5B700` | Action principale (+1 œuf, J'ai fermé, Ajouter), bouton Œufs, icône |
| **Encre** | `#1B2419` | Texte, contours des gros boutons, bandeau Annuler |
| **Encre douce** | `#3D4A3A` | Texte secondaire, légendes |
| **Herbe** | `#EEF2EA` | Fond de l'appli |
| **Blanc** | `#FFFFFF` | Cartes, champs, boutons secondaires |
| **Trait** | `#C9D2C3` | Bordures discrètes, séparateurs |
| **Vert prairie** | `#1F6B3A` | Fait, porte ouverte, Poules |
| **Vert clair** | `#E3F1E7` | Fond d'une tâche faite, pastille « En forme » |
| **Bleu nuit** | `#1E3A5F` | Porte fermée, nuit |
| **Rouille** | `#B5470B` | Alerte : porte à fermer, poule à surveiller, retard, suppression |

Contrastes vérifiés (WCAG, minimum demandé : 4,5 pour 1) :

| Texte sur fond | Rapport |
|---|---|
| Encre sur Herbe | 14,1 |
| Encre sur Jaune d'œuf | 8,9 |
| Encre douce sur Herbe | 8,3 |
| Blanc sur Bleu nuit | 11,5 |
| Blanc sur Vert prairie | 6,5 |
| Blanc sur Rouille | 5,4 |
| Vert prairie sur Vert clair | 5,6 |

**À ne jamais faire** : du texte blanc sur le jaune (contraste trop faible), ou du rouge et du vert seuls pour opposer deux états.

## 3. Typographie

**Atkinson Hyperlegible** (Braille Institute, licence libre OFL), conçue pour les personnes malvoyantes : chaque lettre a une forme bien distincte (I/l/1, O/0, b/d). Elle est hébergée avec l'appli, sans service extérieur.

| Usage | Taille (texte « Normal ») | Graisse |
|---|---|---|
| Grand chiffre (œufs du jour) | 99 px | Gras |
| Titre de bouton d'accueil | 33 px | Gras |
| Titre d'écran | 33 px | Gras |
| Bouton principal | 26–33 px | Gras |
| Texte courant, boutons | 18–20 px | Normal / Gras |
| Légende | 16–17 px | Normal |

Toutes les tailles suivent le réglage **Taille du texte** (×1, ×1,17, ×1,39).

## 4. Formes

- **Arrondis généreux** : 20 à 24 px pour les boutons et les cartes, des cercles pour les personnes et les poules.
- **Contour encre de 3 px** sur les gros boutons de l'accueil et le bouton principal : ils se voient même en plein soleil.
- **Espacement** sur une grille de 4 px : 12 à 16 px entre les éléments, marges de 18 à 20 px.

## 5. Icônes

Un trait simple de 2 px, aux bouts arrondis, toujours accompagné d'un mot. Le jeu d'icônes : œuf, liste cochée, poule, porte, roue (réglages), cloche (rappel), appareil photo, repère (position), flèche retour.

L'**icône de l'appli** est un œuf blanc cerclé d'encre sur un carré jaune d'œuf arrondi.

## 6. Ton

- On dit « vous », avec simplicité et chaleur : « Pensez à fermer la porte ! », « Bienvenue à elle ! ».
- On remercie la personne qui a fait l'action : « Porte fermée, merci ! ».
- Les erreurs disent quoi faire : « Pas de connexion internet. Réessayez quand le téléphone capte. »
- Les heures s'écrivent à la française : **17 h 21**.
