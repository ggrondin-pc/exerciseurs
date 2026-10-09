<?php
/* Lecture des statistiques anonymes — scienceexotic.fr (réservée au professeur)
 * Guillaume Grondin - Enseignement Physique Chimie - Collège · CC BY-NC-SA 4.0
 * Accès : saisir le jeton d'export (celui de config.php). Un cookie de 30 jours évite de le retaper sur cet appareil.
 * Le cookie ne contient pas le jeton lui-même, seulement une empreinte calculée à partir de lui.
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");

$fc = __DIR__ . '/config.php';
if (!is_file($fc)) { http_response_code(503); exit('config.php absent'); }
$c = require $fc;
$jeton = (string)($c['jeton_export'] ?? '');
if ($jeton === '' || $jeton === 'UNE_LONGUE_CLE_AU_HASARD') { http_response_code(503); exit('jeton_export non renseigné'); }
$empreinte = hash_hmac('sha256', 'lecture-stats', $jeton);
$COOKIE = 'se_prof';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (isset($_GET['sortir'])) { setcookie($COOKIE, '', ['expires' => 1, 'path' => '/api/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']); header('Location: stats.php'); exit; }
$ok = hash_equals($empreinte, (string)($_COOKIE[$COOKIE] ?? ''));
$erreur = '';
if (!$ok && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  usleep(400000); // ralentit les essais au hasard
  if (hash_equals($jeton, (string)($_POST['jeton'] ?? ''))) {
    setcookie($COOKIE, $empreinte, ['expires' => time() + 30 * 86400, 'path' => '/api/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    header('Location: stats.php'); exit;
  }
  $erreur = 'Jeton incorrect.';
}

$CSS = <<<CSS
:root{--fond:#f7f7f5;--carte:#fff;--encre:#1b1b1b;--enc2:#555;--enc3:#888;--trait:#e3e3df;--barre:#2f6fb5;--barre-pale:#c9dbef}
@media (prefers-color-scheme:dark){:root{--fond:#16171a;--carte:#202226;--encre:#ececec;--enc2:#b4b4b4;--enc3:#8a8a8a;--trait:#33353a;--barre:#6ea6e6;--barre-pale:#2b3b52}}
*{box-sizing:border-box}body{margin:0;background:var(--fond);color:var(--encre);font:15px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
main{max-width:760px;margin:0 auto;padding:16px}h1{font-size:1.3rem;margin:.2em 0 .1em}h2{font-size:1rem;margin:0 0 .6em}
.sous{color:var(--enc2);margin:0 0 1em}.carte{background:var(--carte);border:1px solid var(--trait);border-radius:10px;padding:14px;margin:0 0 14px}
.tuiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:0 0 14px}
.tuile{background:var(--carte);border:1px solid var(--trait);border-radius:10px;padding:12px}.tuile b{display:block;font-size:1.6rem;font-variant-numeric:tabular-nums}
.tuile span{color:var(--enc2);font-size:.85rem}nav{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px}
nav a{padding:6px 12px;border:1px solid var(--trait);border-radius:999px;color:var(--encre);text-decoration:none;background:var(--carte)}
nav a.actif{background:var(--encre);color:var(--carte);border-color:var(--encre)}
table{width:100%;border-collapse:collapse}td{padding:5px 0;border-top:1px solid var(--trait);vertical-align:middle}
td.nb{text-align:right;width:6.8em;white-space:nowrap;padding-left:8px;font-variant-numeric:tabular-nums;color:var(--enc2)}td.lib{width:45%;padding-right:8px;word-break:break-word}
.jauge{height:10px;border-radius:0 4px 4px 0;background:var(--barre);min-width:2px}
svg text{fill:var(--enc3);font-size:22px}.vide{color:var(--enc3)}
form{display:flex;gap:8px;flex-wrap:wrap}input{flex:1;min-width:200px;padding:10px;border:1px solid var(--trait);border-radius:8px;background:var(--carte);color:var(--encre);font:inherit}
button{padding:10px 16px;border:0;border-radius:8px;background:var(--encre);color:var(--carte);font:inherit}
.note{color:var(--enc3);font-size:.85rem}.err{color:#c0392b}a{color:var(--barre)}
CSS;

function page(string $titre, string $corps, string $css): void {
  echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
     . '<meta name="robots" content="noindex,nofollow"><title>' . h($titre) . '</title><style>' . $css . '</style></head><body><main>' . $corps . '</main></body></html>';
  exit;
}

if (!$ok) {
  page('Statistiques du site', '<h1>Statistiques du site</h1><p class="sous">Espace réservé au professeur.</p><div class="carte"><form method="post">'
    . '<input type="password" name="jeton" autocomplete="current-password" placeholder="Jeton (jeton_export de config.php)" required>'
    . '<button>Ouvrir</button></form>' . ($erreur ? '<p class="err">' . h($erreur) . '</p>' : '')
    . '<p class="note">Cet appareil s\'en souviendra 30 jours.</p></div>', $CSS);
}

// ---------------------------------------------------------------- lecture des compteurs
$PERIODES = ['7' => '7 jours', '30' => '30 jours', '90' => '3 mois', '365' => '1 an', 'tout' => 'Tout'];
$p = (string)($_GET['p'] ?? '30'); if (!isset($PERIODES[$p])) $p = '30';
$depuis = $p === 'tout' ? '2000-01-01' : gmdate('Y-m-d', time() - ((int)$p - 1) * 86400);

$db = new PDO('mysql:host=' . $c['db_hote'] . ';dbname=' . $c['db_nom'] . ';charset=utf8mb4', $c['db_user'], $c['db_mdp'],
              [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
$q = $db->prepare('SELECT jour, evenement, dims, n FROM compteurs WHERE jour >= ? ORDER BY jour');
$q->execute([$depuis]);

$LIEUX = ['Indian/Reunion' => 'La Réunion', 'Indian/Mauritius' => 'Maurice', 'Indian/Mayotte' => 'Mayotte', 'Indian/Antananarivo' => 'Madagascar',
  'Indian/Comoro' => 'Comores', 'Indian/Mahe' => 'Seychelles', 'Europe/Paris' => 'France métropolitaine', 'Europe/Brussels' => 'Belgique',
  'Europe/Zurich' => 'Suisse', 'Europe/Luxembourg' => 'Luxembourg', 'America/Martinique' => 'Martinique', 'America/Guadeloupe' => 'Guadeloupe',
  'America/Cayenne' => 'Guyane', 'America/Montreal' => 'Québec', 'America/Toronto' => 'Canada (Est)', 'Pacific/Noumea' => 'Nouvelle-Calédonie',
  'Pacific/Tahiti' => 'Polynésie', 'America/Miquelon' => 'Saint-Pierre-et-Miquelon', 'Africa/Johannesburg' => 'Afrique du Sud',
  'Africa/Casablanca' => 'Maroc', 'Africa/Algiers' => 'Algérie', 'Africa/Tunis' => 'Tunisie', 'Africa/Dakar' => 'Sénégal', 'Africa/Abidjan' => 'Côte d\'Ivoire',
  'UTC' => 'Inconnu (fuseau masqué)', 'Etc/UTC' => 'Inconnu (fuseau masqué)', '' => 'Inconnu'];
$parJour = []; $pages = []; $lieux = []; $app = []; $src = []; $retour = []; $ecart = []; $duree = []; $robots = []; $rejets = [];
$vues = 0;
foreach ($q as $l) {
  $d = json_decode($l['dims'], true) ?: []; $n = (int)$l['n'];
  if (isset($d['page']) && strpos($d['page'], 'test-') === 0) continue;   // lignes d'essai du 09/10/2026
  switch ($l['evenement']) {
    case 'vue':
      $vues += $n; $parJour[$l['jour']] = ($parJour[$l['jour']] ?? 0) + $n;
      $pages[$d['page'] ?? '?'] = ($pages[$d['page'] ?? '?'] ?? 0) + $n;
      $tz = $d['tz'] ?? ''; $lieu = $LIEUX[$tz] ?? str_replace('_', ' ', $tz);
      $lieux[$lieu] = ($lieux[$lieu] ?? 0) + $n;
      foreach (['appareil' => &$app, 'source' => &$src, 'retour' => &$retour, 'ecart' => &$ecart] as $k => &$t) { $v = $d[$k] ?? '?'; $t[$v] = ($t[$v] ?? 0) + $n; }
      unset($t);
      break;
    case 'duree': $v = $d['tranche'] ?? '?'; $duree[$v] = ($duree[$v] ?? 0) + $n; break;
    case 'robot': $v = $d['famille'] ?? '?'; $robots[$v] = ($robots[$v] ?? 0) + $n; break;
    case 'rejet': $v = $d['motif'] ?? '?'; $rejets[$v] = ($rejets[$v] ?? 0) + $n; break;
  }
}

function liste(array $t, array $libelles = [], array $ordre = [], int $max = 12): string {
  if (!$t) return '<p class="vide">Rien pour cette période.</p>';
  if ($ordre) { $o = []; foreach ($ordre as $k) if (isset($t[$k])) $o[$k] = $t[$k]; foreach ($t as $k => $v) if (!isset($o[$k])) $o[$k] = $v; $t = $o; }
  else arsort($t);
  $tot = array_sum($t); $mx = max($t); $r = '<table>'; $i = 0; $autres = 0;
  foreach ($t as $k => $v) {
    if (++$i > $max) { $autres += $v; continue; }
    $pc = $tot ? round(100 * $v / $tot) : 0;
    $r .= '<tr><td class="lib">' . h($libelles[$k] ?? $k) . '</td><td><div class="jauge" style="width:' . max(1, round(100 * $v / $mx)) . '%"></div></td>'
        . '<td class="nb">' . $v . ' <small>(' . $pc . ' %)</small></td></tr>';
  }
  if ($autres) $r .= '<tr><td class="lib">Autres</td><td></td><td class="nb">' . $autres . '</td></tr>';
  return $r . '</table>';
}

// courbe des vues par jour (barres), jours sans visite inclus
$debutJ = $p === 'tout' ? ($parJour ? array_key_first($parJour) : gmdate('Y-m-d')) : $depuis;
$jours = []; for ($t = strtotime($debutJ . ' UTC'); $t <= time(); $t += 86400) $jours[gmdate('Y-m-d', $t)] = $parJour[gmdate('Y-m-d', $t)] ?? 0;
$nbj = count($jours); $mx = max(1, max($jours ?: [0])); $W = 700; $H = 190; $bas = 155; $pas = $W / max(1, $nbj); $lb = max(1.0, $pas - 2);
$svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" width="100%" role="img" aria-label="Vues par jour"><line x1="0" y1="' . $bas . '" x2="' . $W . '" y2="' . $bas . '" stroke="currentColor" stroke-opacity=".2"/>';
$i = 0;
foreach ($jours as $j => $v) {
  $hgt = $v ? max(2, ($bas - 10) * $v / $mx) : 0; $x = $i * $pas + 1;
  if ($hgt) $svg .= '<rect x="' . round($x, 1) . '" y="' . round($bas - $hgt, 1) . '" width="' . round($lb, 1) . '" height="' . round($hgt, 1) . '" rx="' . min(4, $lb / 2) . '" fill="var(--barre)"><title>' . h(date('d/m/Y', strtotime($j))) . ' : ' . $v . ' vue' . ($v > 1 ? 's' : '') . '</title></rect>';
  if ($i === 0 || $i === $nbj - 1 || ($nbj > 10 && $i % (int)ceil($nbj / 6) === 0 && $i < $nbj - 3))
    $svg .= '<text x="' . ($i === 0 ? 0 : ($i === $nbj - 1 ? $W : round($x + $lb / 2, 1))) . '" y="182" text-anchor="' . ($i === 0 ? 'start' : ($i === $nbj - 1 ? 'end' : 'middle')) . '">' . date('d/m', strtotime($j)) . '</text>';
  $i++;
}
$svg .= '<text x="2" y="20">max ' . $mx . '</text></svg>';

$habitues = $retour['habitue'] ?? 0; $nouveaux = $retour['nouveau'] ?? 0;
$nav = '<nav>'; foreach ($PERIODES as $k => $lib) $nav .= '<a href="?p=' . $k . '"' . ($k === $p ? ' class="actif"' : '') . '>' . $lib . '</a>'; $nav .= '</nav>';

$corps = '<h1>Statistiques du site</h1><p class="sous">scienceexotic.fr · anonymes · jours comptés en heure de Greenwich (La Réunion : +4 h)</p>' . $nav
 . '<div class="tuiles"><div class="tuile"><b>' . $vues . '</b><span>pages vues</span></div>'
 . '<div class="tuile"><b>' . ($ecart['premiere'] ?? 0) . '</b><span>nouveaux visiteurs (première page vue)</span></div>'
 . '<div class="tuile"><b>' . $habitues . '</b><span>pages vues par des habitués</span></div>'
 . '<div class="tuile"><b>' . array_sum($robots) . '</b><span>passages de robots</span></div></div>'
 . '<div class="carte"><h2>Pages vues par jour</h2>' . $svg . '<p class="note">Touchez ou survolez une barre pour voir le chiffre du jour.</p></div>'
 . '<div class="carte"><h2>Pages les plus vues</h2>' . liste($pages, [], [], 15) . '</div>'
 . '<div class="carte"><h2>D\'où viennent les visiteurs</h2>' . liste($lieux) . '<p class="note">Déduit du fuseau horaire de l\'appareil (pas d\'adresse IP) : territoire ou pays, pas la ville.</p></div>'
 . '<div class="carte"><h2>Temps passé sur une page</h2>' . liste($duree, ['0-10s' => 'moins de 10 s', '10-30s' => '10 à 30 s', '30s-2min' => '30 s à 2 min', '2-10min' => '2 à 10 min', '10min+' => 'plus de 10 min'], ['0-10s', '10-30s', '30s-2min', '2-10min', '10min+']) . '</div>'
 . '<div class="carte"><h2>Retour sur le site</h2>' . liste($ecart, ['premiere' => 'première visite', '0j' => 'le même jour', '1j' => 'le lendemain', '2-7j' => '2 à 7 jours après', '8-30j' => '8 à 30 jours après', '30j+' => 'plus d\'un mois après'], ['premiere', '0j', '1j', '2-7j', '8-30j', '30j+']) . '</div>'
 . '<div class="carte"><h2>Appareils</h2>' . liste($app, ['mobile' => 'téléphone', 'tablette' => 'tablette', 'ordi' => 'ordinateur']) . '</div>'
 . '<div class="carte"><h2>Comment ils arrivent</h2>' . liste($src, ['direct' => 'adresse tapée, favori ou lien partagé', 'interne' => 'depuis une autre page du site', 'moteur' => 'moteur de recherche', 'autre' => 'autre site']) . '</div>'
 . '<div class="carte"><h2>Robots (qui lisent les pages)</h2>' . liste($robots) . '<p class="note">Seuls les robots qui exécutent le JavaScript sont vus ici.</p></div>'
 . ($rejets ? '<div class="carte"><h2>Envois refusés par le collecteur</h2>' . liste($rejets) . '<p class="note">Normalement proche de zéro ; un nombre élevé signale un problème ou des essais malveillants.</p></div>' : '')
 . '<p class="note">Export tableur : export.php?jeton=…&amp;depuis=AAAA-MM-JJ · <a href="?sortir=1">Se déconnecter sur cet appareil</a></p>';
page('Statistiques du site', $corps, $CSS);
