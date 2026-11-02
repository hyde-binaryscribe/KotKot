/* KotKot — aide à la race : questions simples et races courantes dans les jardins en France.
 * Les poules de jardin sont souvent des croisements : le résultat est une piste, pas un diagnostic. */
'use strict';

window.KOTKOT_BREEDS = (() => {
  const questions = [
    {
      id: 'color',
      text: 'De quelle couleur est-elle surtout ?',
      options: [
        ['roux', 'Rousse', 'brun-roux, parfois un peu de blanc à la queue'],
        ['blanc', 'Blanche'],
        ['noir', 'Noire', 'souvent avec des reflets verts ou cuivrés'],
        ['hermine', 'Blanche avec le cou noir', 'collier rayé noir et blanc, queue noire'],
        ['coucou', 'Grise rayée', 'petites rayures grises et blanches partout'],
        ['dore', 'Dorée ou perdrix', 'mélange de brun, d\'or et de noir'],
        ['fauve', 'Beige ou fauve', 'couleur crème, sable, saumon'],
        ['lisere', 'Plumes bordées', 'chaque plume a un contour d\'une autre couleur'],
        ['bleu', 'Gris-bleu'],
        ['multi', 'Tachetée, plusieurs couleurs'],
      ],
      swatches: {
        roux: '#9C4A1A', blanc: '#FFFFFF', noir: '#1B2419', hermine: 'linear-gradient(#1B2419 0 35%, #FFFFFF 35%)',
        coucou: 'repeating-linear-gradient(135deg,#7A7F86 0 4px,#E9EAEC 4px 8px)', dore: 'linear-gradient(135deg,#C9891E,#5A3412)',
        fauve: '#E4C08A', lisere: 'radial-gradient(circle,#F3EBDD 45%,#1B2419 47% 60%,#F3EBDD 62%)', bleu: '#7E8FA6',
        multi: 'radial-gradient(circle at 30% 30%,#FFFFFF 18%,transparent 20%),radial-gradient(circle at 70% 60%,#1B2419 16%,transparent 18%),#B5651D',
      },
    },
    {
      id: 'feathers',
      text: 'Ses plumes ont-elles quelque chose de spécial ?',
      options: [
        ['normal', 'Non, des plumes ordinaires'],
        ['soie', 'Toutes douces, comme de la fourrure', 'on dirait une peluche'],
        ['frise', 'Frisées, retournées vers l\'avant'],
        ['counu', 'Le cou sans plumes', 'peau rouge visible sur le cou'],
      ],
    },
    {
      id: 'head',
      text: 'Comment est le haut de sa tête ?',
      options: [
        ['simple', 'Une grande crête droite', 'avec des « dents », comme une scie'],
        ['rosee', 'Une crête plate et grumeleuse', 'large, couchée sur la tête'],
        ['petite', 'Une toute petite crête', 'en forme de pois ou de noix'],
        ['huppe', 'Un pompon de plumes', 'une touffe sur le dessus de la tête'],
      ],
    },
    {
      id: 'legs',
      text: 'Ses pattes ont-elles des plumes ?',
      options: [
        ['nues', 'Non, pattes nues'],
        ['plumes', 'Oui, des plumes jusqu\'aux doigts'],
      ],
    },
    {
      id: 'size',
      text: 'Quelle taille fait-elle ?',
      options: [
        ['naine', 'Petite', 'nettement plus petite qu\'une poule ordinaire'],
        ['moyenne', 'Moyenne', 'la taille d\'une poule rousse classique'],
        ['grosse', 'Grosse', 'lourde, imposante'],
      ],
    },
    {
      id: 'eggs',
      text: 'De quelle couleur sont ses œufs ?',
      options: [
        ['blanc', 'Blancs'],
        ['creme', 'Crème, beige clair'],
        ['brun', 'Bruns'],
        ['chocolat', 'Brun très foncé, chocolat'],
        ['bleuvert', 'Bleus ou verts'],
      ],
    },
  ];

  // color: couleurs possibles · feathers · head · legs · size · eggs
  const breeds = [
    { name: 'Poule rousse (hybride)', color: ['roux'], feathers: ['normal'], head: ['simple'], legs: ['nues'], size: ['moyenne'], eggs: ['brun'],
      desc: 'La pondeuse la plus courante : rousse, souvent un peu de blanc à la queue. Calme et très bonne pondeuse.' },
    { name: 'Hybride noire', color: ['noir'], feathers: ['normal'], head: ['simple'], legs: ['nues'], size: ['moyenne'], eggs: ['brun'],
      desc: 'Noire à reflets verts, souvent un plastron cuivré. Excellente pondeuse, rustique.' },
    { name: 'Sussex', color: ['hermine', 'blanc', 'fauve'], feathers: ['normal'], head: ['simple'], legs: ['nues'], size: ['grosse'], eggs: ['creme', 'brun'],
      desc: 'Blanche au cou rayé noir et à la queue noire (« herminée »). Grosse, douce, bonne pondeuse.' },
    { name: 'Orpington', color: ['fauve', 'noir', 'blanc', 'bleu'], feathers: ['normal'], head: ['simple'], legs: ['nues'], size: ['grosse'], eggs: ['creme', 'brun'],
      desc: 'Très ronde avec un plumage abondant, souvent fauve. Très calme, aime les câlins.' },
    { name: 'Marans', color: ['noir', 'coucou', 'blanc'], feathers: ['normal'], head: ['simple'], legs: ['nues', 'plumes'], size: ['grosse'], eggs: ['chocolat'],
      desc: 'Race française célèbre pour ses œufs brun très foncé. Souvent noire au camail cuivré.' },
    { name: 'Australorp', color: ['noir', 'bleu'], feathers: ['normal'], head: ['simple'], legs: ['nues'], size: ['grosse'], eggs: ['brun', 'creme'],
      desc: 'Grande poule noire brillante aux reflets verts. Calme et bonne pondeuse.' },
    { name: 'Brahma', color: ['hermine', 'dore', 'fauve', 'bleu'], feathers: ['normal'], head: ['petite'], legs: ['plumes'], size: ['grosse'], eggs: ['creme'],
      desc: 'Géante aux pattes emplumées et à la petite crête. Très douce, supporte bien le froid.' },
    { name: 'Pékin', color: ['fauve', 'noir', 'blanc', 'coucou', 'bleu'], feathers: ['normal', 'frise'], head: ['simple'], legs: ['plumes'], size: ['naine'], eggs: ['creme'],
      desc: 'Petite boule de plumes aux pattes emplumées. Très familière, idéale avec les enfants.' },
    { name: 'Soie', color: ['blanc', 'noir', 'fauve', 'bleu'], feathers: ['soie'], head: ['huppe', 'petite'], legs: ['plumes'], size: ['naine'], eggs: ['creme'],
      desc: 'Plumage doux comme de la fourrure, peau noire, petit pompon. Excellente couveuse.' },
    { name: 'Leghorn', color: ['blanc', 'roux', 'noir'], feathers: ['normal'], head: ['simple'], legs: ['nues'], size: ['moyenne'], eggs: ['blanc'],
      desc: 'Fine et vive, souvent blanche avec une grande crête qui retombe. Grande pondeuse d\'œufs blancs.' },
    { name: 'Wyandotte', color: ['lisere', 'blanc', 'dore', 'bleu'], feathers: ['normal'], head: ['rosee'], legs: ['nues'], size: ['moyenne', 'grosse'], eggs: ['creme', 'brun'],
      desc: 'Plumes souvent bordées d\'argent ou d\'or, crête plate. Ronde et rustique.' },
    { name: 'Araucana', color: ['multi', 'dore', 'noir', 'bleu', 'blanc'], feathers: ['normal'], head: ['petite'], legs: ['nues'], size: ['moyenne'], eggs: ['bleuvert'],
      desc: 'Pond des œufs bleus ou verts ! Parfois des touffes de plumes sur les joues.' },
    { name: 'Faverolles', color: ['fauve', 'blanc', 'noir'], feathers: ['normal'], head: ['simple'], legs: ['plumes'], size: ['grosse'], eggs: ['creme'],
      desc: 'Race française avec une barbe, des pattes emplumées et 5 doigts. Souvent saumon. Très douce.' },
    { name: 'Gauloise dorée', color: ['dore'], feathers: ['normal'], head: ['simple'], legs: ['nues'], size: ['moyenne'], eggs: ['blanc'],
      desc: 'La poule française « historique » : dorée et noire, pattes bleutées. Vive et rustique.' },
    { name: 'Coucou de Rennes', color: ['coucou'], feathers: ['normal'], head: ['simple'], legs: ['nues'], size: ['grosse'], eggs: ['creme', 'brun'],
      desc: 'Race bretonne au plumage gris rayé. Rustique, bonne pondeuse.' },
    { name: 'Bresse Gauloise', color: ['blanc', 'noir', 'bleu'], feathers: ['normal'], head: ['simple'], legs: ['nues'], size: ['moyenne'], eggs: ['blanc', 'creme'],
      desc: 'Blanche à crête rouge et pattes bleues : les couleurs du drapeau. Active.' },
    { name: 'Sebright', color: ['lisere'], feathers: ['normal'], head: ['rosee'], legs: ['nues'], size: ['naine'], eggs: ['blanc', 'creme'],
      desc: 'Toute petite, chaque plume bordée de noir comme un vitrail. Plutôt décorative.' },
    { name: 'Padoue', color: ['lisere', 'blanc', 'noir', 'dore'], feathers: ['normal', 'frise'], head: ['huppe'], legs: ['nues'], size: ['moyenne'], eggs: ['blanc'],
      desc: 'Grosse huppe ronde qui cache les yeux. Spectaculaire, un peu craintive.' },
    { name: 'Cou nu', color: ['roux', 'noir', 'blanc', 'coucou', 'dore', 'fauve'], feathers: ['counu'], head: ['simple'], legs: ['nues'], size: ['moyenne'], eggs: ['brun', 'creme'],
      desc: 'Cou sans plumes, peau rouge. Supporte très bien la chaleur, rustique.' },
    { name: 'Poule frisée', color: ['blanc', 'noir', 'roux', 'fauve', 'bleu'], feathers: ['frise'], head: ['simple'], legs: ['nues'], size: ['moyenne', 'naine'], eggs: ['creme'],
      desc: 'Plumes retournées vers l\'avant. Originale, craint un peu le froid et la pluie.' },
    { name: "Barbu d'Uccle", color: ['multi', 'noir', 'blanc', 'bleu'], feathers: ['normal'], head: ['simple'], legs: ['plumes'], size: ['naine'], eggs: ['creme', 'blanc'],
      desc: 'Naine à barbe et pattes emplumées, souvent « mille fleurs » (tachetée). Très familière.' },
  ];

  // A rare trait (silky, frizzled, naked neck, crest, feathered legs, blue eggs) weighs more than a colour.
  const weight = { color: 3, feathers: 5, head: 3, legs: 4, size: 2, eggs: 4 };

  function match(answers) {
    const asked = Object.keys(answers);
    const max = asked.reduce((t, k) => t + weight[k], 0) || 1;
    return breeds
      .map((b) => {
        let score = 0;
        for (const k of asked) score += b[k].includes(answers[k]) ? weight[k] : (k === 'feathers' && answers[k] !== 'normal' ? -6 : -1);
        const ratio = Math.max(0, score) / max;
        return { ...b, score, level: ratio >= 0.8 ? 'Très probable' : ratio >= 0.5 ? 'Possible' : 'Peu probable' };
      })
      .sort((a, b) => b.score - a.score);
  }

  return { questions, breeds, match };
})();
