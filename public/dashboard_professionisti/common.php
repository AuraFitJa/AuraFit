<?php
session_start();

$user = $_SESSION['user'] ?? null;
if (!$user && isset($_SESSION['idUtente'])) {
  $user = [
    'idUtente' => (int)$_SESSION['idUtente'],
    'email' => (string)($_SESSION['email'] ?? ''),
    'roles' => (array)($_SESSION['roles'] ?? []),
  ];
}

if (!$user || empty($user['idUtente'])) {
  header('Location: ../login.php');
  exit;
}

$roles = array_map('strtolower', (array)($user['roles'] ?? []));
$isPt = in_array('pt', $roles, true);
$isNutrizionista = in_array('nutrizionista', $roles, true);

if (!$isPt && !$isNutrizionista) {
  header('Location: ../login.php');
  exit;
}

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$email = h((string)($user['email'] ?? ''));
$roleBadge = $isPt && $isNutrizionista ? 'PT + Nutrizionista' : ($isPt ? 'Personal Trainer' : 'Nutrizionista');
$userId = (int)$user['idUtente'];

$dbAvailable = false;
$dbError = null;
if (file_exists(__DIR__ . '/../../config/database.php')) {
  require_once __DIR__ . '/../../config/database.php';
  if (class_exists('Database')) {
    $dbAvailable = true;
  } else {
    $dbError = 'Classe Database non disponibile.';
  }
} else {
  $dbError = 'Config DB mancante: crea config/database.php partendo da config/database.sample.php.';
}

function getProfessionistaId(int $idUtente): ?int {
  $row = Database::exec(
    'SELECT idProfessionista FROM Professionisti WHERE idUtente = ? LIMIT 1',
    [$idUtente]
  )->fetch();

  if (!$row) {
    return null;
  }
  return (int)$row['idProfessionista'];
}

$overview = [
  'clientiAttivi' => 24,
  'idKeyDisponibili' => 6,
  'idKeyTotaliPiano' => 30,
  'piano' => 'Pro Annuale',
  'pianoStato' => 'Attivo',
  'rinnovo' => '2026-09-01',
];

$latestActivities = [
  ['cliente' => 'Giulia R.', 'evento' => 'Workout completato', 'orario' => 'Oggi, 08:42'],
  ['cliente' => 'Marco T.', 'evento' => 'Nuove misure corporee', 'orario' => 'Oggi, 07:55'],
  ['cliente' => 'Silvia M.', 'evento' => 'Pasto registrato', 'orario' => 'Ieri, 21:19'],
  ['cliente' => 'Andrea F.', 'evento' => 'Feedback programma inviato', 'orario' => 'Ieri, 18:07'],
];

$notifiche = [
  '3 richieste check-in in sospeso',
  '2 ID-Key prossime a scadenza tecnica',
  'Report mensile di marzo pronto al download',
];

$mesi = ['Ott', 'Nov', 'Dic', 'Gen', 'Feb', 'Mar'];
$pesoSerie = [77.4, 76.9, 76.7, 76.1, 75.8, 75.5];
$performanceSerie = [62, 66, 68, 71, 74, 78];
$reportMensili = [
  ['mese' => 'Gennaio 2026', 'stato' => 'Disponibile'],
  ['mese' => 'Febbraio 2026', 'stato' => 'Disponibile'],
  ['mese' => 'Marzo 2026', 'stato' => 'In elaborazione server-side'],
];

function renderStart(string $title, string $activeTab, string $email, string $roleBadge, bool $isPt, bool $isNutrizionista): void {
  $tabs = [
    'overview' => ['label' => 'Overview', 'href' => 'overview.php', 'visible' => true],
    'clienti' => ['label' => 'Gestione clienti', 'href' => 'clienti.php', 'visible' => true],
    'idkey' => ['label' => 'Gestione ID-Key', 'href' => 'idkey.php', 'visible' => true],
    'allenamenti' => ['label' => 'Allenamenti (PT)', 'href' => 'allenamenti.php', 'visible' => $isPt],
    'nutrizione' => ['label' => 'Nutrizione (Nutrizionista)', 'href' => 'nutrizione.php', 'visible' => $isNutrizionista],
    'accessi' => ['label' => 'Accessi incrociati', 'href' => 'accessi.php', 'visible' => true],
    'report' => ['label' => 'Monitoraggio & Report', 'href' => 'report.php', 'visible' => true],
  ];

  echo '<!doctype html><html lang="it"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width,initial-scale=1" />';
  echo '<title>' . h($title) . ' — AuraFit</title><meta name="theme-color" content="#0B0F19" />';
  echo '<style>';
  echo ':root{--bg:#070A12;--text:#EAF0FF;--muted:rgba(234,240,255,.68);--line:rgba(234,240,255,.12);--brand1:#6D5EF3;--brand2:#2EE1A5;--brand3:#4CC9F0;--danger:#ff6f89;--warn:#ffd166;--ok:#2EE1A5;--max:1200px;--radius:18px;--sans:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;}';
  echo '*{box-sizing:border-box}body{margin:0;color:var(--text);font-family:var(--sans);background:var(--bg);min-height:100vh;position:relative}';
  echo 'body::before{content:"";position:fixed;inset:0;z-index:-1;background:radial-gradient(1200px 800px at 18% -12%, rgba(109,94,243,.28), transparent 58%),radial-gradient(1100px 700px at 92% 12%, rgba(76,201,240,.16), transparent 58%),radial-gradient(900px 700px at 55% 98%, rgba(46,225,165,.12), transparent 60%)}';
  echo '.container{max-width:var(--max);margin:0 auto;padding:0 18px}.topbar{position:sticky;top:0;z-index:30;border-bottom:1px solid rgba(255,255,255,.08);backdrop-filter:blur(16px);background:rgba(7,10,18,.66)}';
  echo '.nav{display:flex;align-items:center;justify-content:space-between;min-height:74px;gap:12px}.brand{display:flex;align-items:center;gap:10px;font-weight:700}.logo{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,var(--brand1),var(--brand2));box-shadow:0 10px 30px rgba(109,94,243,.2)}';
  echo '.nav-actions{display:flex;gap:8px;align-items:center}.pill{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;border:1px solid var(--line);color:var(--muted);background:rgba(255,255,255,.04);font-size:12px}';
  echo '.btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:10px 14px;text-decoration:none;color:var(--text);background:rgba(255,255,255,.05);font-weight:650;cursor:pointer}.btn.primary{background:linear-gradient(135deg, rgba(109,94,243,.92), rgba(76,201,240,.72));color:#061018;border-color:rgba(109,94,243,.55)}.btn.warn{border-color:rgba(255,209,102,.4)}.btn.danger{border-color:rgba(255,111,137,.45)}';
  echo '.layout{display:grid;grid-template-columns:260px 1fr;gap:16px;padding:20px 0 34px}.side,.card{background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04));border-radius:var(--radius);box-shadow:0 10px 40px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.05)}';
  echo '.side{padding:14px;height:fit-content;position:sticky;top:90px}.menu{display:grid;gap:8px}.menu a{color:var(--muted);text-decoration:none;border:1px solid transparent;padding:10px 12px;border-radius:12px;font-size:14px}.menu a:hover,.menu a.active{color:var(--text);background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.08)}';
  echo '.main{display:grid;gap:14px}.card{padding:18px}.hero{padding:22px}h1{margin:8px 0 10px;font-size:clamp(30px,4vw,44px);letter-spacing:-.02em}.lead{margin:0;color:var(--muted)}';
  echo '.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}.span-3{grid-column:span 3}.span-4{grid-column:span 4}.span-6{grid-column:span 6}.span-8{grid-column:span 8}.span-12{grid-column:span 12}.kpi{font-size:32px;font-weight:800;margin:6px 0 4px}.muted{color:var(--muted)}.section-title{margin:0 0 10px;font-size:20px}.list{margin:10px 0 0;padding-left:16px;color:var(--muted);line-height:1.6}';
  echo 'table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:10px 8px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08)}th{color:var(--muted);font-weight:600}.status{display:inline-flex;align-items:center;border-radius:999px;border:1px solid var(--line);padding:4px 9px;font-size:12px}.status.ok{color:var(--ok)}.status.warn{color:var(--warn)}.status.danger{color:var(--danger)}';
  echo '.toolbar{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px}.field{display:grid;gap:6px}.field input,.field textarea,.field select{width:100%;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.03);color:var(--text);border-radius:12px;padding:10px 12px;font:inherit}.two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.note{font-size:12px;color:var(--muted);margin-top:6px}.divider{height:1px;background:rgba(255,255,255,.08);margin:14px 0}.chart-wrap{height:280px}.alert{border:1px solid rgba(255,127,151,.45);background:rgba(255,127,151,.12);padding:10px 12px;border-radius:12px;color:#ffd7e1}.okbox{border:1px solid rgba(99,230,184,.45);background:rgba(99,230,184,.12);padding:10px 12px;border-radius:12px;color:#d8ffef}';
  echo '@media (max-width:1050px){.layout{grid-template-columns:1fr}.side{position:static}.span-3,.span-4,.span-6,.span-8{grid-column:span 12}.two,.three{grid-template-columns:1fr}}';
  echo '</style></head><body>';

  echo '<header class="topbar"><div class="container nav"><div class="brand"><div class="logo" aria-hidden="true"></div>AuraFit Professionista</div><div class="nav-actions"><span class="pill">' . h($roleBadge) . '</span><a class="btn" href="../logout.php">Logout</a></div></div></header>';
  echo '<div class="container layout"><aside class="side"><div class="menu">';

  foreach ($tabs as $key => $tab) {
    if (!$tab['visible']) {
      continue;
    }
    $isActive = $key === $activeTab ? 'active' : '';
    echo '<a class="' . $isActive . '" href="' . h($tab['href']) . '">' . h($tab['label']) . '</a>';
  }

  echo '</div></aside><main class="main">';
}

function renderEnd(string $scripts = ''): void {
  echo '</main></div>';
  if ($scripts !== '') {
    echo $scripts;
  }
  echo '</body></html>';
}