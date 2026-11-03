/* KotKot — La Gazette du poulailler : les nouvelles du jour, façon presse à scandale.
 *
 * Les rubriques « événement » ne parlent que de ce qui s'est vraiment passé.
 * Les rubriques quotidiennes (horoscope, météo du perchoir, petites annonces…)
 * sont des jeux, présentés comme tels.
 * Chaque texte est tiré au sort dans de grands réservoirs : la graine dépend du jour
 * et de « Autre version », pour que ça change tous les jours sans jamais se répéter.
 * Dans les textes : {n} nombre, {name} animal, {who} personne, {t} témoin, {time}, {date}, {age}… */
'use strict';

window.KOTKOT_GAZETTE = (() => {
  // ------------------------------------------------------------------ hasard reproductible
  function seeded(text) {
    let h = 1779033703 ^ text.length;
    for (let i = 0; i < text.length; i++) {
      h = Math.imul(h ^ text.charCodeAt(i), 3432918353);
      h = (h << 13) | (h >>> 19);
    }
    return () => {
      h = Math.imul(h ^ (h >>> 16), 2246822507);
      h = Math.imul(h ^ (h >>> 13), 3266489909);
      h ^= h >>> 16;
      return (h >>> 0) / 4294967296;
    };
  }
  const pick = (r, list) => list[Math.floor(r() * list.length)];
  const fill = (text, v) => text.replace(/\{(\w+)\}/g, (m, k) => (v[k] !== undefined ? v[k] : m));

  // ------------------------------------------------------------------ réservoirs communs
  const TEMOINS = [
    'une poule qui souhaite garder l\'anonymat', 'une source proche du perchoir', 'le coq du voisin (pas invité)',
    'une mangeoire très bien informée', 'un ver de terre, juste avant de disparaître', 'la doyenne du poulailler',
    'une plume tombée par hasard', 'un moineau de passage', 'le seau d\'eau, témoin de tout', 'une pondeuse sous couvert d\'anonymat',
    'le hérisson du jardin', 'une rumeur insistante près de l\'abreuvoir', 'le chat de la maison, faussement indifférent',
    'un observateur caché derrière le grillage', 'la brouette, qui en a vu d\'autres',
  ];
  const CHUTES = [
    'Affaire à suivre.', 'La rédaction reste sur le qui-vive.', 'Plus d\'informations dans notre prochaine édition.',
    'Aucune poule n\'a souhaité réagir.', 'Le perchoir est sous le choc.', 'On n\'avait pas vu ça depuis la dernière fois.',
    'Le grain, lui, ne commente pas.', 'Une enquête est ouverte.', 'Les paris sont ouverts.', 'Rendez-vous demain, même heure, même pondoir.',
    '« Cot cot », a simplement déclaré {t}.', 'Selon {t}, « c\'était prévisible ».', '« On n\'en revient pas », confie {t}.',
    'D\'après {t}, la nouvelle aurait fait le tour de la basse-cour en trois secondes.', 'Les vers de terre retiennent leur souffle.',
    'Le renard, lui, n\'a pas été prévenu.', 'Une minute de caquetage a été observée.', 'L\'histoire retiendra ce jour.',
    'Les commentaires sont fermés (le bec aussi).', 'Plus personne ne parle d\'autre chose au poulailler.',
  ];

  // ------------------------------------------------------------------ rubriques « événement »
  const EV = {
    record: {
      kickers: ['Record historique', 'Exclusif', 'Dernière minute', 'Incroyable'],
      titles: ['{n} œufs aujourd\'hui !', 'Le pondoir explose les compteurs', 'Historique : {n} œufs en un jour', 'Nouveau record au poulailler !',
        'Du jamais vu : {n} œufs', 'Journée record au pondoir', '{n} œufs : la barre est placée très haut'],
      bodies: ['{n} œufs : du jamais vu dans l\'histoire du poulailler. Les poules refusent de commenter, mais l\'ambiance est électrique au pondoir.',
        '{n} œufs en une journée : c\'est le nouveau record. Une plaque commémorative est à l\'étude.',
        '{n} œufs ! La rédaction a recompté trois fois. Les poules, elles, font comme si c\'était parfaitement normal.',
        '{n} œufs en un jour. Selon {t}, c\'est « un alignement des planètes et des vers de farine ».',
        'Avec {n} œufs, le précédent record tombe. Les pondeuses réclament déjà une augmentation de grain.',
        '{n} œufs : le panier n\'était pas assez grand. Une réunion d\'urgence sur la taille des paniers est prévue.'],
    },
    today: {
      kickers: ['Ponte du jour', 'Production', 'Au rapport', 'Côté nid'],
      titles: ['{n} œufs ce jour !', 'Le pondoir tourne à plein régime', 'Coup de chaud sur la production', '{n} œufs au compteur',
        'Belle récolte au poulailler', 'Le nid ne désemplit pas', 'La production tient ses promesses', 'Bonne journée pour les paniers',
        'Les pondeuses en grande forme', 'Ça pond sec au poulailler', 'Panier bien rempli', 'Le pondoir ne chôme pas'],
      bodies: ['{n} œufs : la direction du poulailler salue une « performance remarquable ». Les poules, elles, sont retournées gratter comme si de rien n\'était.',
        '{n} œufs ramassés. Selon {t}, c\'est « un vrai travail d\'équipe ».',
        'Avec {n} œufs, le poulailler dépasse ses objectifs. Le grain sera-t-il augmenté ? La question est posée.',
        '{n} œufs dans le panier. Les omelettes de ce soir sont, selon nos informations, garanties.',
        '{n} œufs, un rythme soutenu, une organisation sans faille et zéro réunion inutile. Les entreprises devraient s\'en inspirer.',
        '{n} œufs aujourd\'hui. Selon {t}, « certaines ont même pondu en chantant ».',
        'Le compteur affiche {n}. Les pondeuses réclament une photo de groupe.',
        '{n} œufs, et pas un de plus. Ou peut-être que si : la journée n\'est pas finie.'],
    },
    one: {
      kickers: ['Ponte du jour', 'Édition limitée'],
      titles: ['Un œuf, un seul, mais quel œuf !', 'Édition limitée au pondoir', 'Un œuf, et c\'est déjà ça', 'Le minimum syndical'],
      bodies: ['La production du jour tient en un exemplaire. La rédaction salue la qualité plutôt que la quantité.',
        'Un œuf unique ce jour. Les collectionneurs s\'arrachent déjà la boîte.',
        'Une seule pondeuse a fait le travail. Les autres auraient eu « des choses à faire ».',
        'Un œuf, c\'est peu, mais c\'est beau. Ainsi parle {t}.'],
    },
    strike: {
      kickers: ['Grève', 'Mouvement social', 'Alerte'],
      titles: ['Grève surprise au pondoir', 'Silence radio au nid', 'Les poules en télétravail ?', 'Zéro œuf : le mystère s\'épaissit',
        'Le nid reste désespérément vide', 'Journée sans œuf au poulailler', 'Pause syndicale au pondoir'],
      bodies: ['Aucun œuf aujourd\'hui. Les syndicats réclament des vers de farine et une sieste supplémentaire.',
        'Zéro œuf à l\'heure où nous écrivons. La rédaction suit la situation de très près.',
        'Pas un œuf de la journée. Une réunion de crise est prévue autour de la mangeoire.',
        'Selon {t}, les poules « réfléchissent à leur avenir ». La réflexion pourrait durer.',
        'Le panier attend. Le panier espère. Le panier commence à douter.',
        'Mue, fraîcheur ou simple flemme ? Les experts sont partagés, les poules aussi.'],
    },
    quail: {
      kickers: ['Mini mais costauds', 'Côté cailles'],
      titles: ['{n} œufs de caille !', 'Les cailles ne lâchent rien', 'Petits œufs, grand talent', 'Les cailles en pleine forme'],
      bodies: ['{n} œufs de caille : petits par la taille, grands par le talent. La rédaction applaudit des deux ailes.',
        '{n} mini-œufs ramassés. « On n\'a pas besoin d\'être grosses pour assurer », confie l\'une d\'elles.',
        '{n} œufs de caille. Les poules regardent les cailles avec un mélange d\'admiration et d\'agacement.'],
    },
    double: {
      kickers: ['Insolite', 'Phénomène', 'Science'],
      titles: ['Un œuf à double jaune !', 'Deux pour le prix d\'un', 'Le mystère du double jaune', 'Bonus au pondoir : deux jaunes !'],
      bodies: ['Découvert {when}{byWho}. Les scientifiques sont perplexes, la pondeuse reste modeste.',
        'Un œuf à double jaune {when}. Une poule aurait-elle fait des heures supplémentaires ?',
        'Selon {t}, « elle a voulu bien faire ». Mission accomplie.',
        'Les mathématiciens du poulailler confirment : un œuf, deux jaunes, ça fait beaucoup.'],
    },
    dirty: {
      kickers: ['Fait divers', 'Scandale'],
      titles: ['Scandale : un œuf crotté', 'L\'affaire de l\'œuf sale', 'Le pondoir mis en cause', 'Un œuf dans un triste état'],
      bodies: ['« Ce n\'est pas moi ! », jure toute la basse-cour d\'une seule voix. L\'enquête piétine.',
        'Retrouvé dans un état déplorable. Les soupçons se portent sur un passage un peu pressé au pondoir.',
        'Selon {t}, « la paille n\'avait pas été changée ». Une accusation grave.',
        'La rédaction rappelle qu\'un pondoir propre fait des œufs propres. Le message est passé.'],
    },
    broken: {
      kickers: ['Fait divers', 'Accident'],
      titles: ['Un œuf mal en point', 'Drame dans la paille', 'Coup dur au pondoir', 'Un œuf n\'a pas tenu le choc'],
      bodies: ['Un œuf {what} retrouvé {when}. Aucune poule n\'a été mise en examen.',
        'Un œuf n\'a pas survécu à sa première journée. Une minute de silence a été observée (environ trois secondes).',
        'Selon {t}, « il est tombé tout seul ». Version à confirmer.',
        'La coquille, trop fragile ? Les experts recommandent coquilles d\'huîtres et patience.'],
    },
    week: {
      kickers: ['La semaine en chiffres', 'Bilan', 'Statistiques'],
      titles: ['{n} œufs en 7 jours', 'Bilan de la semaine', 'La semaine au pondoir', 'Sept jours, {n} œufs', 'Les comptes de la semaine', 'Une semaine bien remplie'],
      bodies: ['{n} œufs en 7 jours, soit {per} par pondeuse. À la bourse de l\'œuf, les cours s\'affolent.',
        '{n} œufs au compteur cette semaine. De quoi faire {omelettes} omelettes de trois œufs. La rédaction a faim.',
        '{n} œufs, une moyenne de {perDay} par jour. Le panier ne sait plus où donner de la tête.',
        '{n} œufs en une semaine. Selon {t}, « on peut faire mieux ». Personne ne lui a demandé son avis.'],
    },
    up: {
      kickers: ['Économie', 'Croissance'],
      titles: ['Ponte en hausse de {pct} %', 'La croissance est au rendez-vous', 'L\'économie du poulailler s\'envole'],
      bodies: ['+{pct} % par rapport au mois dernier à la même date. Les économistes parlent d\'un « miracle du grain ».',
        '+{pct} % d\'œufs ce mois-ci. Le ministère du Poulailler se félicite.',
        'Une hausse de {pct} % qui se confirme. Selon {t}, « c\'est grâce aux vers de farine du dimanche ».'],
    },
    down: {
      kickers: ['Économie', 'Ralentissement'],
      titles: ['Ponte en baisse de {pct} %', 'Ralentissement au pondoir', 'Le moral des pondeuses en berne ?'],
      bodies: ['-{pct} % sur un mois. Mue, froid ou simple coup de mou ? Les poules invoquent leur droit à la déconnexion.',
        '-{pct} % d\'œufs par rapport au mois dernier. Les experts recommandent patience et vers de farine.',
        'Une baisse de {pct} % qui inquiète les marchés. Les poules, elles, ne lisent pas les marchés.'],
    },
    total: {
      kickers: ['Chiffre rond', 'Cap franchi'],
      titles: ['Plus de {round} œufs depuis le début !', '{n} œufs au compteur', 'Cap des {round} œufs franchi'],
      bodies: ['{n} œufs : de quoi faire {omelettes} omelettes de six œufs. Personne n\'a osé compter les coquilles.',
        '{n} œufs mis bout à bout mesureraient… beaucoup. La rédaction n\'a pas de mètre assez long.',
        '{n} œufs depuis le début : une fête est prévue. Les poules apportent les œufs, comme d\'habitude.'],
    },
    recordOld: {
      kickers: ['Le saviez-vous ?', 'Archives'],
      titles: ['Le record tient toujours : {n} œufs', 'Qui battra le record de {n} œufs ?'],
      bodies: ['C\'était le {date}. Depuis, personne n\'a fait mieux. Les poules sont-elles sous pression ?',
        'Le {date}, le poulailler pondait {n} œufs. Les anciennes en parlent encore aux jeunes.'],
    },
    curfew: {
      kickers: ['Couvre-feu', 'Porte'],
      titles: ['Porte fermée à {time}', 'Retard au couvre-feu', 'La porte a fait des heures sup'],
      bodies: ['{who} a fermé bien après le coucher du soleil. Les poules, couchées depuis longtemps, n\'ont rien remarqué. Ouf.',
        'Fermeture de la porte à {time}. Le renard, lui, regardait sa montre.',
        'Selon {t}, « certaines ont attendu, un œil ouvert ». On les comprend.'],
    },
    early: {
      kickers: ['Lève-tôt', 'Porte'],
      titles: ['Porte ouverte à {time}', 'Réveil aux aurores', '{who}, champion·ne du réveil'],
      bodies: ['{who} était debout avant tout le monde. Les poules exigent des explications.',
        'Ouverture à {time}. Même le soleil n\'était pas complètement réveillé.',
        'Selon {t}, « il faisait encore nuit, mais on a apprécié l\'attention ».'],
    },
    worker: {
      kickers: ['Palmarès', 'Médaille'],
      titles: ['{who}, champion·ne de la semaine', 'Tout le monde dit merci à {who}', 'Médaille d\'or pour {who}'],
      bodies: ['{n} tâches accomplies en 7 jours. Une statue en graines de tournesol est à l\'étude.',
        '{n} corvées en une semaine. Les poules envisagent de lui offrir un œuf (un seul, faut pas exagérer).',
        '{n} tâches en une semaine. Selon {t}, « sans {who}, ce poulailler ne tiendrait pas debout ».'],
    },
    clean: {
      kickers: ['Propreté', 'Inspection'],
      titles: ['Le poulailler impeccable', 'Toutes les tâches faites !', 'Mission accomplie au poulailler'],
      bodies: ['Toutes les tâches du jour sont faites. Les poules envisagent une inspection surprise, gants blancs compris.',
        'Eau fraîche, grain servi, œufs ramassés : rien à redire. Les poules cherchent quand même.',
        'Selon {t}, « on se croirait à l\'hôtel ». Cinq plumes sur cinq.'],
    },
    welcome: {
      kickers: ['Bienvenue', 'Nouvelle recrue'],
      titles: ['{name} rejoint la basse-cour', 'Arrivée remarquée : {name}', 'Une nouvelle tête au poulailler'],
      bodies: ['« Sérieuse, motivée, bonne picoreuse », d\'après ses nouvelles voisines. Les places au perchoir sont en cours de renégociation.',
        'Premières impressions de l\'équipe : « On attend de voir les œufs. »',
        '{name} prend ses marques. Selon {t}, « elle a déjà repéré la meilleure place à la mangeoire ».'],
    },
    birthday: {
      kickers: ['Anniversaire', 'Joyeux anniversaire'],
      titles: ['Joyeux anniversaire {name} !', '{name} fête ses {age}', 'Gâteau aux graines pour {name}'],
      bodies: ['{age} déjà ! Un gâteau aux graines est prévu au goûter. Les invitations ont été picorées.',
        'Toute la basse-cour chante (faux) pour {name}. Les cadeaux : un ver de terre et beaucoup d\'affection.'],
    },
    sick: {
      kickers: ['Bulletin de santé', 'Courage'],
      titles: ['{name} se repose', 'Prompt rétablissement à {name}', '{name} en convalescence'],
      bodies: ['Toute la basse-cour lui souhaite un prompt rétablissement. Les visites sont limitées aux vers de terre.',
        'Repos, eau fraîche et petits soins. Toute la famille veille sur {name}.'],
    },
    firstEgg: {
      kickers: ['Grande première', 'Événement', 'Ça y est !'],
      titles: ['{name} a pondu son premier œuf !', 'Premier œuf pour {name}', '{name} entre dans la cour des grandes', 'Bienvenue au club des pondeuses, {name}'],
      bodies: ['Toute la basse-cour a applaudi. La jeune pondeuse, émue, a demandé un ver de farine et un peu d\'intimité.',
        'Un moment historique. Selon {t}, « elle était fière comme une poule qui a trouvé un couteau ». Enfin, à peu près.',
        'Les anciennes l\'avaient bien dit : « Ça viendra quand ça viendra. » C\'est venu.',
        'Premier œuf, premier pas dans le monde du travail. La rédaction lui souhaite une longue et belle carrière.'],
    },
    photo: {
      kickers: ['Photo du jour', 'Paparazzi'],
      titles: ['{name} prend la pose', '{name}, star de la semaine', 'Séance photo pour {name}', 'Regardez-moi cette beauté'],
      bodies: ['Notre photographe est encore sous le charme. Les autres réclament déjà leur séance.',
        'Les paparazzis du poulailler ont encore frappé. Aucun démenti de l\'intéressée.',
        'Profil gauche, profil droit : {name} assure. Selon {t}, « elle sait qu\'elle est belle ».'],
    },
  };

  // ------------------------------------------------------------------ rubriques quotidiennes (des jeux)
  const HOROSCOPE = [
    'Vénus est dans la mangeoire : journée idéale pour pondre dans un endroit inattendu.',
    'Mars vous rend d\'humeur bagarreuse. Évitez les discussions autour de l\'abreuvoir.',
    'Une rencontre importante vous attend : probablement un ver de terre.',
    'Côté cœur, le coq du voisin pense à vous. Restez digne.',
    'Votre étoile vous conseille une sieste au soleil, puis une deuxième.',
    'Prudence : un humain avec un seau pourrait changer votre journée. En bien.',
    'Énergie au maximum. Vous gratterez plus que d\'habitude, et c\'est tant mieux.',
    'Les astres annoncent des restes de repas. Soyez à l\'heure.',
    'Journée calme. Vous méritez ce bain de poussière.',
    'Ne prêtez pas votre place au perchoir. Elle ne vous serait jamais rendue.',
    'Votre créativité est au sommet : un œuf de forme originale n\'est pas exclu.',
    'Saturne recommande la patience. Le grain arrive, il arrive toujours.',
    'Un nuage pourrait vous faire peur. C\'est un nuage.',
    'Votre charme naturel fera des envieuses. Assumez.',
    'Chance au jeu : vous trouverez la meilleure limace du jardin.',
  ];
  const METEO = {
    hiver: ['Ciel gris sur le perchoir, plumes gonflées de rigueur. Prévoir de l\'eau non gelée.',
      'Froid de canard (sans vouloir vexer personne). Les poules restent groupées.', 'Matinée fraîche : les plus courageuses sortiront après le café.'],
    printemps: ['Grand soleil sur la basse-cour, bains de poussière en hausse.', 'Averses passagères, vers de terre en abondance : la journée s\'annonce excellente.',
      'Douceur printanière : la ponte remonte, le moral aussi.'],
    ete: ['Chaleur sur le poulailler : ombre et eau fraîche obligatoires.', 'Canicule possible : les poules exigent un parasol.',
      'Soirée douce, couvre-feu tardif : on ferme la porte après le coucher du soleil.'],
    automne: ['Vent de feuilles mortes : jeu de grattage garanti.', 'Temps de mue : quelques plumes au sol, aucune inquiétude.',
      'Brouillard matinal : on distingue à peine le pondoir. On le trouve quand même.'],
  };
  const ANNONCES = [
    'Échange ver de terre bien dodu contre place près de la fenêtre. Sérieuses uniquement.',
    'Cherche colocataire calme pour partager le pondoir de gauche. Pas de ronfleuses.',
    'Perdu : une plume, couleur rousse, très belle. Récompense : un regard reconnaissant.',
    'Vends coin de poussière idéal pour bain, très bien exposé. Prix à débattre en graines.',
    'Donne cours de grattage, tous niveaux. Résultats garantis ou grain remboursé.',
    'Recherche activement : l\'humain qui avait des restes de pâtes hier. Merci de se manifester.',
    'Club de lecture du perchoir : ce mois-ci, « Le renard et moi, une histoire de méfiance ».',
    'Chorale de l\'aube recrute. Aucune expérience demandée, beaucoup de volume exigé.',
    'Location de nid saisonnière. Paille fraîche, vue sur la mangeoire.',
    'Cherche partenaire de course poursuite pour sauterelle. Sportives bienvenues.',
  ];
  const PENSEES = [
    '« Un œuf par jour, c\'est déjà une belle journée. »', '« Qui gratte trouve. »',
    '« Le grain d\'aujourd\'hui vaut mieux que deux demain. »', '« Ne mettez pas tous vos œufs dans le même panier. Sauf si c\'est le bon panier. »',
    '« Il n\'y a pas de petit ver. »', '« Après la pluie, les vers de terre. »', '« Le perchoir n\'appartient à personne. Surtout pas à toi. »',
    '« Chaque matin est une nouvelle chance de caqueter. »', '« La patience est la mère de la ponte. »', '« Picorer, c\'est réfléchir avec le bec. »',
  ];
  const CONSEILS = [
    'Changez l\'eau tous les jours, et un peu plus souvent quand il fait chaud.',
    'Des coquilles d\'huîtres broyées à volonté rendent les coquilles plus solides.',
    'Une paille propre dans les pondoirs, c\'est des œufs plus propres.',
    'Ramassez les œufs chaque jour : ils restent propres et les poules ne prennent pas de mauvaises habitudes.',
    'Un bain de poussière (terre fine, cendre de bois) aide les poules à se débarrasser des parasites.',
    'Pendant la mue, elles pondent moins : c\'est normal, un peu plus de protéines les aide.',
    'Fermez la porte chaque soir : le renard est ponctuel, lui aussi.',
    'Quelques restes de légumes font plaisir, mais pas d\'avocat ni d\'aliments salés.',
    'En hiver, vérifiez que l\'eau n\'a pas gelé le matin.',
    'Un œuf qui flotte dans un verre d\'eau n\'est plus frais : direction le compost.',
    'Vermifugez au printemps et à l\'automne, et notez-le dans le carnet santé de KotKot.',
    'Les œufs non lavés se gardent mieux : leur fine pellicule naturelle les protège.',
  ];
  const COURRIER = [
    ['Pourquoi mes poules pondent moins en hiver ?', 'Parce que les jours raccourcissent : elles ont besoin de lumière pour pondre. Patience, ça revient au printemps.'],
    ['Faut-il un coq pour avoir des œufs ?', 'Non ! Les poules pondent très bien sans coq. Il faut un coq seulement pour avoir des poussins.'],
    ['Pourquoi ma poule perd ses plumes ?', 'C\'est sûrement la mue, en général à l\'automne. Elle repousse en quelques semaines, toute neuve.'],
    ['Pourquoi certains œufs ont deux jaunes ?', 'Souvent chez les jeunes pondeuses dont le rythme n\'est pas encore réglé. Rien d\'inquiétant, et c\'est la fête à l\'omelette.'],
    ['Mes poules picorent leurs œufs, que faire ?', 'Ramassez souvent, ajoutez des coquilles d\'huîtres et mettez un œuf factice dans le nid.'],
    ['Combien de temps vit une poule ?', 'Souvent 6 à 10 ans quand elle est bien soignée. Elle pond surtout ses premières années.'],
  ];

  // ------------------------------------------------------------------ le journal du jour
  /**
   * c: { today, hour, eggs, hens, door, tasks, g (faits du serveur), species(x), avatarOf(hen), photoUrl(id),
   *      longDay(iso), dayWord(iso), time(ts), plural(n, one, many), daysBetween(a, b), dayKey(ts), salt(id) }
   */
  function stories(c) {
    const out = [];
    const e = c.eggs || {};
    const g = c.g || {};
    const hens = c.hens || [];
    const add = (id, ev, vars = {}, extra = {}) => out.push({ id, ev, vars, priority: 1, ...extra });

    const n = e.today || 0;
    if (g.record && g.record.count >= 2 && g.record.day === c.today) add('record', EV.record, { n: g.record.count }, { priority: 5 });
    else if (n === 1) add('today', EV.one, { n }, { priority: 3 });
    else if (n > 1) add('today', EV.today, { n }, { priority: 3 });
    else if (c.hour >= 12 && hens.length) add('strike', EV.strike, {}, { priority: 3 });

    const quail = e.todayBySpecies?.caille || 0;
    if (quail) add('quail', EV.quail, { n: quail }, { priority: 2 });

    const recent = e.quality?.recent || [];
    const dbl = recent.find((x) => x.defects.includes('double') && c.daysBetween(x.day, c.today) <= 7);
    if (dbl) add('double', EV.double, { when: c.dayWord(dbl.day), byWho: dbl.who ? ` par ${dbl.who}` : '' }, { priority: 4 });
    const bad = recent.find((x) => x.quality !== 'bon' && c.daysBetween(x.day, c.today) <= 3);
    if (bad) {
      if (bad.defects.includes('sale')) add('dirty', EV.dirty, {}, { priority: 3 });
      else add('broken', EV.broken, { what: c.criteria(bad.defects) || 'abîmé', when: c.dayWord(bad.day) }, { priority: 3 });
    }

    const week = e.last7 || 0;
    if (week) {
      const layers = hens.filter((x) => c.species(x) === 'poule' && x.lays !== 'non').length || hens.filter((x) => x.lays !== 'non').length || 1;
      add('week', EV.week, {
        n: week,
        per: (week / layers).toLocaleString('fr-FR', { maximumFractionDigits: 1 }),
        perDay: (week / 7).toLocaleString('fr-FR', { maximumFractionDigits: 1 }),
        omelettes: Math.max(1, Math.floor(week / 3)),
      });
    }
    if (g.prevMonthSoFar > 0) {
      const pct = Math.round(((g.month - g.prevMonthSoFar) / g.prevMonthSoFar) * 100);
      if (pct >= 10) add('economy', EV.up, { pct });
      else if (pct <= -10) add('economy', EV.down, { pct: -pct });
    }
    if (g.total >= 100) add('total', EV.total, { n: g.total, round: Math.floor(g.total / 100) * 100, omelettes: Math.floor(g.total / 6) });
    if (g.record && g.record.count >= 2 && g.record.day !== c.today) add('record-old', EV.recordOld, { n: g.record.count, date: c.longDay(g.record.day) });

    const hist = c.door?.history || [];
    const close = hist.find((x) => x.action === 'close' && Date.now() / 1000 - x.at < 2 * 86400);
    if (close && c.door?.sunset) {
      const sunset = c.door.sunset - (c.dayKey(close.at) === c.today ? 0 : 86400);
      if (close.at - sunset > 3600) add('curfew', EV.curfew, { time: c.time(close.at), who: close.who || 'Quelqu\'un' }, { priority: 2 });
    }
    const open = hist.find((x) => x.action === 'open' && c.dayKey(x.at) === c.today);
    if (open && c.hourOf(open.at) < 7) add('early', EV.early, { time: c.time(open.at), who: open.who || 'Quelqu\'un' }, { priority: 2 });

    if (g.worker && g.worker.tasks >= 3) add('worker', EV.worker, { who: g.worker.name, n: g.worker.tasks }, { priority: 2 });
    const due = (c.tasks || []).filter((t) => t.dueToday);
    if (due.length && due.every((t) => t.done)) add('clean', EV.clean, {}, { priority: 2 });

    for (const hen of hens) {
      if (hen.arrivedOn && c.daysBetween(hen.arrivedOn, c.today) <= 14) add(`welcome-${hen.id}`, EV.welcome, { name: hen.name }, { image: c.avatarOf(hen), priority: 3 });
      if (hen.bornOn) {
        const thisYear = `${c.today.slice(0, 4)}${hen.bornOn.slice(4)}`;
        const gap = c.daysBetween(thisYear, c.today);
        const years = Number(c.today.slice(0, 4)) - Number(hen.bornOn.slice(0, 4));
        if (years >= 1 && gap >= 0 && gap <= 3) add(`birthday-${hen.id}`, EV.birthday, { name: hen.name, age: c.plural(years, 'an', 'ans') }, { image: c.avatarOf(hen), priority: 4 });
      }
      if (hen.layingSince && c.daysBetween(hen.layingSince, c.today) <= 7) add(`first-${hen.id}`, EV.firstEgg, { name: hen.name }, { image: c.avatarOf(hen), priority: 5 });
      if (hen.status === 'watch') add(`sick-${hen.id}`, EV.sick, { name: hen.name }, { image: c.avatarOf(hen), priority: 2 });
    }
    for (const shot of (g.photos || []).slice(0, 2)) {
      add(`photo-${shot.photoId}`, EV.photo, { name: shot.name }, { image: c.photoUrl(shot.photoId), priority: 2 });
    }

    // Daily columns (games): three of them each day, chosen by the date
    const day = seeded(`rubriques|${c.today}`);
    const columns = ['horoscope', 'meteo', 'annonce', 'pensee', 'conseil', 'courrier', 'portrait', 'chiffre']
      .map((k) => [k, day()]).sort((a, b) => a[1] - b[1]).map(([k]) => k);
    let wanted = 3;
    for (const col of columns) {
      if (wanted === 0) break;
      const before = out.length;
      const r = seeded(`${col}|${c.today}|${c.salt(col)}`);
      const hen = hens.length ? pick(r, hens) : null;
      if (col === 'horoscope' && hen) out.push({ id: col, kicker: 'Horoscope de la basse-cour', title: `${hen.name}, votre journée`, body: pick(r, HOROSCOPE), image: c.avatarOf(hen), game: true });
      if (col === 'meteo') {
        const m = Number(c.today.slice(5, 7));
        const season = m === 12 || m <= 2 ? 'hiver' : m <= 5 ? 'printemps' : m <= 8 ? 'ete' : 'automne';
        out.push({ id: col, kicker: 'Météo du perchoir', title: pick(r, ['Le temps qu\'il fera au poulailler', 'Prévisions pour la basse-cour', 'Bulletin météo des plumes']), body: pick(r, METEO[season]), game: true });
      }
      if (col === 'annonce') out.push({ id: col, kicker: 'Petites annonces', title: pick(r, ['Vu à la mangeoire', 'Annonce du jour', 'Affiché près de l\'abreuvoir']), body: pick(r, ANNONCES), game: true });
      if (col === 'pensee') out.push({ id: col, kicker: 'Pensée du jour', title: pick(r, ['La sagesse du perchoir', 'Proverbe de poule', 'À méditer en grattant']), body: pick(r, PENSEES), game: true });
      if (col === 'conseil') out.push({ id: col, kicker: 'Le conseil de Mamie Poule', title: pick(r, ['Le bon geste du jour', 'Astuce de basse-cour', 'Ça vaut de l\'or (et des œufs)']), body: pick(r, CONSEILS), game: true });
      if (col === 'courrier') { const [q, a] = pick(r, COURRIER); out.push({ id: col, kicker: 'Courrier des lecteurs', title: q, body: a, game: true }); }
      if (col === 'portrait' && hen) {
        const bits = [];
        if (hen.breed) bits.push(hen.breed);
        if (hen.bornOn) bits.push(`${c.plural(Math.max(0, Math.floor(c.daysBetween(hen.bornOn, c.today) / 365)), 'an', 'ans')}`.replace(/^0 ans$/, 'moins d\'un an'));
        if (hen.arrivedOn) bits.push(`au poulailler depuis ${c.longDay(hen.arrivedOn)}`);
        out.push({ id: col, kicker: 'Portrait du jour', title: `Qui êtes-vous, ${hen.name} ?`,
          body: `${bits.length ? cap(bits.join(', ')) + '. ' : ''}${pick(r, ['Signe particulier : un regard qui en dit long.', 'Personnalité affirmée, coup de bec précis.', 'Discrète mais toujours la première à la mangeoire.', 'Adore les bains de soleil et les débats animés.', 'Une vraie star, mais qui reste simple.'])}`,
          image: c.avatarOf(hen), game: true });
      }
      if (col === 'chiffre') {
        const facts = [];
        if (g.total) facts.push([`${g.total}`, 'œufs comptés depuis le début dans KotKot. Bravo à toute l\'équipe, plumes comprises.']);
        if (hens.length) facts.push([`${hens.length}`, `${hens.length > 1 ? 'animaux vivent' : 'animal vit'} au poulailler. Et chacun pense être le chef.`]);
        if (week) facts.push([`${week}`, 'œufs cette semaine. De quoi tenir quelques gâteaux.']);
        if (facts.length) { const [num, text] = pick(r, facts); out.push({ id: col, kicker: 'Le chiffre du jour', title: num, body: text, game: true }); }
      }
      if (out.length > before) wanted--; // a column that had nothing to say lets the next one in
    }

    // Writing: title, text, sometimes a punchline, a witness, all from the pools.
    return out.map((st) => {
      if (!st.ev) return st;
      const r = seeded(`${st.id}|${c.today}|${c.salt(st.id)}`);
      const vars = { ...st.vars, t: pick(r, TEMOINS) };
      let body = fill(pick(r, st.ev.bodies), vars);
      if (r() < 0.55) body += ` ${fill(pick(r, CHUTES), { ...vars, t: pick(r, TEMOINS) })}`;
      return { ...st, kicker: pick(r, st.ev.kickers), title: fill(pick(r, st.ev.titles), vars), body };
    }).sort((a, b) => (b.priority || 0) - (a.priority || 0));
  }

  const cap = (t) => t.charAt(0).toUpperCase() + t.slice(1);
  return { stories };
})();
