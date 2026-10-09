<?php
/* Collecteur de statistiques anonymes — scienceexotic.fr
 * Guillaume Grondin - Enseignement Physique Chimie - Collège · CC BY-NC-SA 4.0
 * Principe : « le navigateur calcule, le serveur compte ».
 * - Aucune adresse IP, aucun navigateur, aucun identifiant de visiteur n'est enregistré.
 * - Chaque événement autorisé ne fait qu'augmenter un compteur par jour et par combinaison d'étiquettes.
 * - Les étiquettes et leurs valeurs sont filtrées par une liste blanche.
 */
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$ORIGINES = ['https://scienceexotic.fr', 'https://www.scienceexotic.fr', 'https://ggrondin-pc.github.io'];
$origine = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origine !== '' && in_array($origine, $ORIGINES, true)) {
  header('Access-Control-Allow-Origin: ' . $origine);
  header('Vary: Origin');
  header('Access-Control-Allow-Methods: POST');
  header('Access-Control-Allow-Headers: Content-Type');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
if ($origine !== '' && !in_array($origine, $ORIGINES, true)) { http_response_code(403); exit; }

$brut = file_get_contents('php://input', false, null, 0, 2048);
$msg = json_decode($brut ?: '', true);
if (!is_array($msg)) { http_response_code(400); exit; }

// ---- liste blanche des événements et de leurs étiquettes -------------------------------
$MOT  = '/^[a-z0-9_.-]{1,40}$/';
$PAGE = '/^[a-z0-9_\/.-]{1,80}$/';
$TZ   = '/^[A-Za-z_\/+-]{1,40}$/';
$EVENEMENTS = [
  'vue'   => ['page' => $PAGE, 'tz' => $TZ, 'retour' => ['nouveau', 'habitue'],
              'ecart' => ['premiere', '0j', '1j', '2-7j', '8-30j', '30j+'],
              'appareil' => ['mobile', 'tablette', 'ordi'], 'source' => ['direct', 'interne', 'moteur', 'autre']],
  'duree' => ['page' => $PAGE, 'tranche' => ['0-10s', '10-30s', '30s-2min', '2-10min', '10min+']],
  'exo'   => ['page' => $PAGE, 'niveau' => ['primaire', '6e', '5e', '4e', '3e', 'lycee', 'adulte', 'enseignant', 'inconnu'],
              'chapitre' => $MOT, 'exercice' => $MOT, 'mode' => $MOT, 'essai' => ['1', '2', '3', '4-5', '6+'],
              'maitrise' => ['oui', 'non'], 'retour' => ['nouveau', 'habitue'], 'frequence' => ['rare', 'hebdo', 'quotidien']],
];

$ev = is_string($msg['e'] ?? null) ? $msg['e'] : '';
$dims_in = is_array($msg['d'] ?? null) ? $msg['d'] : [];

// Les robots qui exécutent le JavaScript sont comptés à part, par grande famille seulement.
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/(gptbot|claudebot|anthropic|perplexity|ccbot|bytespider|googlebot|bingbot|yandex|baidu|duckduck|bot\b|crawl|spider|slurp|headless|python|curl|wget|java\/|go-http)/i', $ua, $m)) {
  $ev = 'robot';
  $dims = ['famille' => strtolower(preg_replace('/[^a-z0-9]/i', '', $m[1]))];
} else {
  if (!isset($EVENEMENTS[$ev])) { http_response_code(400); exit; }
  $dims = [];
  foreach ($EVENEMENTS[$ev] as $cle => $regle) {
    if (!isset($dims_in[$cle]) || !is_scalar($dims_in[$cle])) continue;
    $val = (string)$dims_in[$cle];
    $ok = is_array($regle) ? in_array($val, $regle, true) : (bool)preg_match($regle, $val);
    if ($ok) $dims[$cle] = $val;
  }
}
ksort($dims);
$dims_json = json_encode($dims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$valeur = (isset($msg['v']) && is_numeric($msg['v']) && $ev === 'exo') ? max(0.0, min(100.0, (float)$msg['v'])) : null;

// ---- enregistrement ---------------------------------------------------------------------
$fc = __DIR__ . '/config.php';
if (!is_file($fc)) { http_response_code(503); exit; }
$c = require $fc;
try {
  $db = new PDO('mysql:host=' . $c['db_hote'] . ';dbname=' . $c['db_nom'] . ';charset=utf8mb4', $c['db_user'], $c['db_mdp'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
  $db->exec('CREATE TABLE IF NOT EXISTS compteurs (
      jour DATE NOT NULL, evenement VARCHAR(16) NOT NULL, cle CHAR(40) NOT NULL, dims VARCHAR(600) NOT NULL,
      n INT UNSIGNED NOT NULL DEFAULT 0, somme DOUBLE NULL, somme2 DOUBLE NULL, mini DOUBLE NULL, maxi DOUBLE NULL,
      PRIMARY KEY (jour, evenement, cle)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $q = $db->prepare('INSERT INTO compteurs (jour, evenement, cle, dims, n, somme, somme2, mini, maxi)
      VALUES (UTC_DATE(), :e, :k, :d, 1, :v, :v2, :v, :v)
      ON DUPLICATE KEY UPDATE n = n + 1,
        somme  = IF(VALUES(somme) IS NULL, somme, IFNULL(somme, 0) + VALUES(somme)),
        somme2 = IF(VALUES(somme2) IS NULL, somme2, IFNULL(somme2, 0) + VALUES(somme2)),
        mini   = IF(VALUES(mini) IS NULL, mini, LEAST(IFNULL(mini, VALUES(mini)), VALUES(mini))),
        maxi   = IF(VALUES(maxi) IS NULL, maxi, GREATEST(IFNULL(maxi, VALUES(maxi)), VALUES(maxi)))');
  $q->execute([':e' => $ev, ':k' => sha1($dims_json), ':d' => $dims_json,
               ':v' => $valeur, ':v2' => $valeur === null ? null : $valeur * $valeur]);
  http_response_code(204);
} catch (Throwable $t) {
  http_response_code(500);
}
