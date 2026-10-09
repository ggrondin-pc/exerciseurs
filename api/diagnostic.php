<?php
/* Diagnostic du collecteur — n'affiche que des états (oui/non, codes d'erreur), jamais de mot de passe ni de nom.
 * À ouvrir dans un navigateur : https://scienceexotic.fr/api/diagnostic.php — fichier temporaire, à retirer une fois le collecteur en marche.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo "PHP : " . PHP_VERSION . "\n";
echo "Extension pdo_mysql : " . (extension_loaded('pdo_mysql') ? "oui" : "NON") . "\n";
$fc = __DIR__ . '/config.php';
echo "Fichier api/config.php : " . (is_file($fc) ? "présent" : "ABSENT (copier config.exemple.php en config.php et le compléter)") . "\n";
if (!is_file($fc)) exit;
$c = @include $fc;
if (!is_array($c)) { echo "config.php : illisible (le fichier doit commencer par <?php et se terminer par ];)\n"; exit; }
foreach (['db_hote', 'db_nom', 'db_user', 'db_mdp', 'jeton_export'] as $k) {
  $v = (string)($c[$k] ?? '');
  $modele = in_array($v, ['', 'NOM_DE_LA_BASE', 'UTILISATEUR_DE_LA_BASE', 'MOT_DE_PASSE_DE_LA_BASE', 'UNE_LONGUE_CLE_AU_HASARD'], true);
  echo "  réglage $k : " . ($modele ? "NON RENSEIGNÉ" : "renseigné") . "\n";
}
try {
  $db = new PDO('mysql:host=' . $c['db_hote'] . ';dbname=' . $c['db_nom'] . ';charset=utf8mb4', $c['db_user'], $c['db_mdp'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
  echo "Connexion à la base : OK\n";
  $db->exec('CREATE TABLE IF NOT EXISTS compteurs (
      jour DATE NOT NULL, evenement VARCHAR(16) NOT NULL, cle CHAR(40) NOT NULL, dims VARCHAR(600) NOT NULL,
      n INT UNSIGNED NOT NULL DEFAULT 0, somme DOUBLE NULL, somme2 DOUBLE NULL, mini DOUBLE NULL, maxi DOUBLE NULL,
      PRIMARY KEY (jour, evenement, cle)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  echo "Table compteurs : OK\n";
  $n = $db->query('SELECT COUNT(*), IFNULL(SUM(n),0) FROM compteurs')->fetch(PDO::FETCH_NUM);
  echo "Lignes : {$n[0]} · événements comptés : {$n[1]}\n";
  echo "\nDétail des 3 derniers jours (événement, étiquettes, nombre) :\n";
  foreach ($db->query("SELECT jour, evenement, dims, n FROM compteurs WHERE jour >= UTC_DATE() - INTERVAL 2 DAY ORDER BY jour DESC, evenement, n DESC LIMIT 60") as $l)
    echo "  {$l['jour']}  {$l['evenement']}  {$l['dims']}  × {$l['n']}\n";
} catch (Throwable $t) {
  $code = ($t instanceof PDOException && isset($t->errorInfo[1])) ? $t->errorInfo[1] : $t->getCode();
  $aide = [1045 => "utilisateur ou mot de passe refusé", 1044 => "l'utilisateur n'a pas les droits sur cette base (ajouter « Tous les privilèges »)",
           1049 => "nom de base inconnu (vérifier le préfixe)", 2002 => "hôte injoignable (essayer localhost)", 1142 => "droit CREATE manquant"];
  echo "ERREUR base de données, code $code : " . ($aide[(int)$code] ?? "voir le code") . "\n";
}

// Essai automatique d'autres adresses de serveur MySQL (aucun mot de passe affiché)
echo "\nEssais d'autres adresses :\n";
$cands = ['localhost', '127.0.0.1', (string)ini_get('mysqli.default_host'), (string)ini_get('pdo_mysql.default_socket'), (string)ini_get('mysqli.default_socket'),
          '/var/lib/mysql/mysql.sock', '/var/run/mysqld/mysqld.sock', '/tmp/mysql.sock', '/run/mysqld/mysqld.sock'];
foreach (array_unique(array_filter($cands)) as $h) {
  $dsn = ($h[0] === '/') ? 'mysql:unix_socket=' . $h : 'mysql:host=' . $h;
  try {
    new PDO($dsn . ';dbname=' . $c['db_nom'] . ';charset=utf8mb4', $c['db_user'], $c['db_mdp'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
    echo "  $h : CONNEXION OK  <-- à mettre dans db_hote\n";
  } catch (Throwable $t) {
    $code = ($t instanceof PDOException && isset($t->errorInfo[1])) ? $t->errorInfo[1] : $t->getCode();
    echo "  $h : échec (code $code)\n";
  }
}
echo "Réglage db_hote actuel : " . (in_array($c['db_hote'], ['localhost', '127.0.0.1'], true) ? $c['db_hote'] : 'autre valeur') . "\n";
