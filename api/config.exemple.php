<?php
// MODÈLE — à copier sur le serveur sous le nom config.php (même dossier api/),
// puis à compléter avec les informations de la base MySQL créée dans cPanel.
// Le fichier config.php ne doit JAMAIS être mis sur GitHub : il contient un mot de passe.
return [
  'db_hote'   => 'localhost',            // en général « localhost » chez LWS
  'db_nom'    => 'NOM_DE_LA_BASE',       // ex. scien2881468_stats
  'db_user'   => 'UTILISATEUR_DE_LA_BASE',
  'db_mdp'    => 'MOT_DE_PASSE_DE_LA_BASE',
  // Clé de lecture pour l'export mensuel des statistiques (agrégées, anonymes).
  // Mettre une longue suite de lettres et de chiffres au hasard.
  'jeton_export' => 'UNE_LONGUE_CLE_AU_HASARD',
];
