# Feuille de route KotKot

La V1 est en ligne. Toutes les idées ci-dessous ont été validées ; l'ordre proposé va du plus utile au plus « bonus ».

## V2 — fait
- [x] **Comptes et poulaillers** : création en ligne, plusieurs poulaillers par compte, chaque famille séparée
- [x] **Invitation** de la famille par code ou lien, code familial à 4 chiffres
- [x] Mot de passe oublié, export des données (zip), suppression (RGPD)
- [x] **Rappels même appli fermée** (notifications push) pour la porte et la santé
- [x] **Carnet santé** : vermifuge, poux, vaccins, soins, avec rappel du prochain traitement
- [x] **Aide à l'identification de la race** — questionnaire en 6 questions, 21 races
- [x] Formule gratuite / Premium prête (album limité à 3 photos, rappels en Premium) — désactivée tant que `billing` est à `false`
- [x] Vue ordinateur (accueil sur plusieurs colonnes)

## V2.2 — fait
- [x] **Chacun son compte** (e-mail, Google, Apple) lié à sa personne : le poulailler sur tous ses appareils, sans code
- [x] **Continuer avec Google / Apple** (activé dans `config.php`)
- [x] **Synchro en direct** : ce que fait un téléphone apparaît sur les autres en quelques secondes
- [x] **Calendrier des œufs** : un jour oublié ? on touche le jour et on indique le nombre (jusqu'à 2 ans en arrière)

## V2.3 — fait
- [x] **Toutes les volailles** : poules, cailles, canards, oies, dindes, pintades… et œufs comptés par espèce
- [x] **Dates de naissance et d'arrivée** (facultatives), âge calculé, âge sur chaque photo
- [x] Photos réduites sur le téléphone avant l'envoi (plus de « La photo n'a pas pu être envoyée »)
- [x] Champ date bien affiché sur iPhone

## V2.4 / V2.5 — fait
- [x] **Qualité des œufs** : bons et mauvais, critères (fêlé, sale, coquille molle…), commentaires, bilan 30 jours
- [x] **Photo de profil** : la plus récente par défaut, ou une photo choisie ; **recadrage** au doigt (toucher, glisser, zoomer, flèches)
- [x] Photos de l'album gardées entières

## V2.6 — fait
- [x] **La Gazette du poulailler** : les nouvelles du poulailler en articles comiques (record, grève de la ponte, double jaune, couvre-feu, anniversaire, photo du jour…), à partager en image

## V2.7 — fait
- [x] **« À la une »** de la Gazette sur l'accueil, différente à chaque ouverture (masquable dans Réglages)
- [x] Gazette enrichie : des centaines de variantes, et des rubriques quotidiennes (horoscope de la basse-cour, météo du perchoir, petites annonces, portrait, chiffre du jour, conseil de Mamie Poule, pensée du jour, courrier des lecteurs)

## V2.8 — fait
- [x] Porte : indiquer l'heure après coup, corriger ou supprimer une ligne de l'historique
- [x] Mauvais œuf noté pour un jour passé (depuis le calendrier)
- [x] Tâches : 2 fois par jour, 1 jour sur 2, tous les N jours, toutes les 2 semaines, chaque mois ; tâches modifiables
- [x] Ponte : « Pond / Ne pond pas encore / Je ne sais pas » ; premier œuf fêté dans la Gazette

## Prochaine étape
- [ ] **Paiement en ligne** (Stripe : abonnement annuel, factures, résiliation)
- [ ] **Pages légales** : mentions légales, CGV, confidentialité
- [ ] **Aide à la race par photo** (IA Claude) — Premium, nécessite une clé d'API
- [ ] Rappel des tâches hebdomadaires en retard

## V3 — poules et œufs
- [ ] Mue et couvaison sur la fiche (explique la baisse de ponte)
- [ ] Suivi des poussins : poids et taille à côté de l'album photo
- [ ] Qui a pondu (quand on le sait)
- [ ] Œufs donnés ou vendus
- [ ] Alerte de baisse de ponte (comparée à l'an passé)

## V4 — le poulailler
- [ ] Stock de nourriture (« encore environ 5 jours de grain »)
- [ ] Carnet de dépenses et coût d'un œuf
- [ ] Alerte météo : grand froid, canicule
- [ ] Mode vacances : code et liste de tâches pour le voisin

## Pour les enfants
- [ ] Chef poulailler de la semaine
- [ ] Badges (100 œufs ramassés, porte fermée 7 jours de suite…)

## Déjà fait (V1)
- [x] Accueil, porte avec soleil et rappel, œufs, tâches, poules illimitées, fiches, carnet
- [x] Album photo daté par poule, comparaison Avant / Maintenant
- [x] Code familial, hors ligne, installable sur le téléphone
