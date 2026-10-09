<?php
/* Export des compteurs agrégés (anonymes) en CSV, pour l'archive mensuelle.
 * Appel : export.php?jeton=…&depuis=AAAA-MM-JJ   (le jeton est dans config.php)
 */
declare(strict_types=1);
$fc = __DIR__ . '/config.php';
if (!is_file($fc)) { http_response_code(503); exit; }
$c = require $fc;
$jeton = (string)($_GET['jeton'] ?? '');
if ($jeton === '' || !hash_equals((string)$c['jeton_export'], $jeton)) { http_response_code(403); exit; }
$depuis = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['depuis'] ?? '')) ? $_GET['depuis'] : '2000-01-01';
$db = new PDO('mysql:host=' . $c['db_hote'] . ';dbname=' . $c['db_nom'] . ';charset=utf8mb4', $c['db_user'], $c['db_mdp'],
              [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="compteurs_depuis_' . $depuis . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['jour', 'evenement', 'dims', 'n', 'somme', 'somme2', 'mini', 'maxi']);
$q = $db->prepare('SELECT jour, evenement, dims, n, somme, somme2, mini, maxi FROM compteurs WHERE jour >= ? ORDER BY jour, evenement');
$q->execute([$depuis]);
while ($l = $q->fetch(PDO::FETCH_NUM)) fputcsv($out, $l);
