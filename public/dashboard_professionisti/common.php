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

function h(?string $value): string {
  return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function isValidProfileName(string $value): bool {
  return $value === '' || preg_match("/^[\p{L}\s'’-]{1,60}$/u", $value) === 1;
}

function isValidPhone(string $value): bool {
  return $value === '' || preg_match('/^[0-9+()\s-]{6,20}$/', $value) === 1;
}

$email = (string)($user['email'] ?? '');
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


if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['profileScope'] ?? '') === 'professionista')) {
  header('Content-Type: application/json; charset=utf-8');

  if (!$dbAvailable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $dbError ?? 'Database non disponibile.']);
    exit;
  }

  $nomeInput = trim((string)($_POST['nome'] ?? ''));
  $cognomeInput = trim((string)($_POST['cognome'] ?? ''));
  $emailInput = trim((string)($_POST['email'] ?? ''));
  $telefonoInput = trim((string)($_POST['telefono'] ?? ''));

  if (!isValidProfileName($nomeInput) || !isValidProfileName($cognomeInput)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Nome o cognome non validi.']);
    exit;
  }

  if (!isValidPhone($telefonoInput)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Telefono non valido.']);
    exit;
  }

  if ($emailInput === '' || !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Email non valida.']);
    exit;
  }

  try {
    Database::exec(
      'UPDATE Utenti
       SET nome = ?, cognome = ?, email = ?, aggiornatoIl = NOW()
       WHERE idUtente = ?',
      [$nomeInput, $cognomeInput, $emailInput, $userId]
    );

    $_SESSION['user']['nome'] = $nomeInput;
    $_SESSION['user']['cognome'] = $cognomeInput;
    $_SESSION['user']['email'] = $emailInput;

    echo json_encode(['ok' => true, 'message' => 'Profilo aggiornato nel database.']);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Salvataggio non riuscito. Riprova.']);
    exit;
  }
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


$professionistaProfileForm = [
  'nome' => trim((string)($user['nome'] ?? '')),
  'cognome' => trim((string)($user['cognome'] ?? '')),
  'email' => (string)($user['email'] ?? ''),
  'telefono' => '',
  'specializzazione' => '',
  'bio' => '',
];

if ($dbAvailable) {
  try {
    $row = Database::exec(
      'SELECT nome, cognome, email FROM Utenti WHERE idUtente = ? LIMIT 1',
      [$userId]
    )->fetch();

    if ($row) {
      $professionistaProfileForm['nome'] = trim((string)($row['nome'] ?? $professionistaProfileForm['nome']));
      $professionistaProfileForm['cognome'] = trim((string)($row['cognome'] ?? $professionistaProfileForm['cognome']));
      $professionistaProfileForm['email'] = (string)($row['email'] ?? $professionistaProfileForm['email']);
    }
  } catch (Throwable $e) {
    // Fallback ai dati sessione quando il DB non è disponibile.
  }
}

$email = h($professionistaProfileForm['email']);

function renderStart(string $title, string $activeTab, string $email, string $roleBadge, bool $isPt, bool $isNutrizionista): void {
  global $professionistaProfileForm;

  $tabs = [
    'overview' => ['label' => 'Overview', 'href' => 'overview.php', 'visible' => true],
    'clienti' => ['label' => 'Gestione clienti', 'href' => 'clienti.php', 'visible' => true],
    'idkey' => ['label' => 'Gestione ID-Key', 'href' => 'idkey.php', 'visible' => true],
    'allenamenti' => ['label' => 'Allenamenti (PT)', 'href' => 'allenamenti.php', 'visible' => $isPt],
    'nutrizione' => ['label' => 'Nutrizione (Nutrizionista)', 'href' => 'nutrizione.php', 'visible' => $isNutrizionista],
    'accessi' => ['label' => 'Accessi incrociati', 'href' => 'accessi.php', 'visible' => true],
    'report' => ['label' => 'Monitoraggio & Report', 'href' => 'report.php', 'visible' => true],
  ];

  echo '<!doctype html><html lang="it"><head><meta charset="utf-8" /><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover" />';
  echo '<title>' . h($title) . ' — AuraFit</title><meta name="theme-color" content="#0B0F19" /><meta name="color-scheme" content="dark" /><meta name="apple-mobile-web-app-capable" content="yes" /><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" /><meta name="apple-mobile-web-app-title" content="AuraFit" /><link rel="apple-touch-icon" href="/media/apple-touch-icon.png" /><link rel="manifest" href="/manifest.json" />';
  echo '<style>';
  echo ':root{--bg:#0b1220;--text:#EAF0FF;--muted:rgba(234,240,255,.68);--line:rgba(234,240,255,.12);--brand1:#6D5EF3;--brand2:#2EE1A5;--brand3:#4CC9F0;--danger:#ff6f89;--warn:#ffd166;--ok:#2EE1A5;--max:1200px;--radius:18px;--sans:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;}';
  echo '*{box-sizing:border-box}html{background-color:var(--bg);color-scheme:dark;min-height:100%;width:100%;overflow-x:hidden}html,body{width:100%;max-width:100%;overflow-x:hidden}body{margin:0;color:var(--text);font-family:var(--sans);background-color:var(--bg);min-height:100vh;position:relative;padding-top:env(safe-area-inset-top);padding-right:env(safe-area-inset-right);padding-bottom:env(safe-area-inset-bottom);padding-left:env(safe-area-inset-left)}';
  echo 'body::before{content:none}';
  echo '.container{max-width:var(--max);margin:0 auto;padding:0 18px}.topbar{position:sticky;top:0;z-index:30;border-bottom:1px solid rgba(255,255,255,.08);backdrop-filter:blur(16px);background:rgba(7,10,18,.66)}';
  echo '.nav{display:flex;align-items:center;justify-content:space-between;min-height:74px;gap:12px}.brand{display:flex;align-items:center;gap:10px;font-weight:700}.logo{width:34px;height:34px;border-radius:10px;object-fit:cover;box-shadow:0 10px 30px rgba(109,94,243,.2)}';
  echo '.nav-actions{display:flex;gap:8px;align-items:center}.pill{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;border:1px solid var(--line);color:var(--muted);background:rgba(255,255,255,.04);font-size:12px}.role-btn{cursor:pointer;font:inherit}.role-btn:hover{color:var(--text);border-color:rgba(255,255,255,.24);background:rgba(255,255,255,.08)}.profile-modal{position:fixed;inset:0;z-index:80;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(2,4,10,.7)}.profile-modal.open{display:flex}.profile-modal-card{width:min(680px,100%);max-height:min(88vh,820px);overflow:auto;padding:18px;border-radius:20px;background:linear-gradient(180deg,rgba(22,29,46,.95),rgba(13,17,29,.97));border:1px solid rgba(255,255,255,.1);box-shadow:0 24px 48px rgba(0,0,0,.5)}.profile-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px}.profile-modal-close{border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.06);color:var(--text);border-radius:10px;padding:6px 10px;cursor:pointer}.profile-modal-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.profile-modal-grid .field.full{grid-column:1/-1}.profile-feedback{display:none;margin-top:10px}.profile-feedback.visible{display:block}';
  echo '.btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:10px 14px;text-decoration:none;color:var(--text);background:rgba(255,255,255,.05);font-weight:650;cursor:pointer}.btn.primary{background:linear-gradient(135deg, rgba(109,94,243,.92), rgba(76,201,240,.72));color:#061018;border-color:rgba(109,94,243,.55)}.btn.warn{border-color:rgba(255,209,102,.4)}.btn.danger{border-color:rgba(255,111,137,.45)}';
  echo '.layout{display:grid;grid-template-columns:260px 1fr;gap:16px;padding:20px 0 34px}.side,.card{background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04));border-radius:var(--radius);box-shadow:0 10px 40px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.05)}';
  echo '.side{padding:14px;height:fit-content;position:sticky;top:90px}.menu{display:grid;gap:8px}.menu a{color:var(--muted);text-decoration:none;border:1px solid transparent;padding:10px 12px;border-radius:12px;font-size:14px}.menu a:hover,.menu a.active{color:var(--text);background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.08)}';
  echo '.main{display:grid;gap:14px}.card{padding:18px}.hero{padding:22px}h1{margin:8px 0 10px;font-size:clamp(30px,4vw,44px);letter-spacing:-.02em}.lead{margin:0;color:var(--muted)}';
  echo '.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}.span-3{grid-column:span 3}.span-4{grid-column:span 4}.span-6{grid-column:span 6}.span-8{grid-column:span 8}.span-12{grid-column:span 12}.kpi{font-size:32px;font-weight:800;margin:6px 0 4px}.muted{color:var(--muted)}.section-title{margin:0 0 10px;font-size:20px}.list{margin:10px 0 0;padding-left:16px;color:var(--muted);line-height:1.6}';
  echo 'table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:10px 8px;text-align:left;border-bottom:1px solid rgba(255,255,255,.08)}th{color:var(--muted);font-weight:600}.status{display:inline-flex;align-items:center;border-radius:999px;border:1px solid var(--line);padding:4px 9px;font-size:12px}.status.ok{color:var(--ok)}.status.warn{color:var(--warn)}.status.danger{color:var(--danger)}';
  echo '.toolbar{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px}.field{display:grid;gap:6px}.field input,.field textarea,.field select{width:100%;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.03);color:var(--text);border-radius:12px;padding:10px 12px;font:inherit}.field select{appearance:none;color:var(--text)}.field select option{background:#0b1220;color:var(--text)}.two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.note{font-size:12px;color:var(--muted);margin-top:6px}.divider{height:1px;background:rgba(255,255,255,.08);margin:14px 0}.chart-wrap{height:280px}.alert{border:1px solid rgba(255,127,151,.45);background:rgba(255,127,151,.12);padding:10px 12px;border-radius:12px;color:#ffd7e1}.okbox{border:1px solid rgba(99,230,184,.45);background:rgba(99,230,184,.12);padding:10px 12px;border-radius:12px;color:#d8ffef}.mobile-tabs{display:none}';
  echo '@media (max-width:1050px){.layout{grid-template-columns:1fr}.side{position:static}.span-3,.span-4,.span-6,.span-8{grid-column:span 12}.two,.three{grid-template-columns:1fr}}';
  echo '@media (max-width:820px){body{background:#0b1220}body::before{content:none}.topbar{background:rgba(8,10,16,.92);border-bottom-color:rgba(255,255,255,.08)}.container{padding:0 14px}.nav{min-height:64px}.brand{font-size:17px;letter-spacing:.01em}.logo{width:28px;height:28px;border-radius:8px}.nav-actions{gap:6px}.nav-actions .pill{color:rgba(234,240,255,.9);background:rgba(255,255,255,.06)}.nav-actions .btn{padding:8px 11px;font-size:12px;border-radius:11px;background:rgba(255,255,255,.08);color:#F4F7FF}.layout{display:block;padding:10px 0 calc(104px + env(safe-area-inset-bottom))}.side{display:none}.main{gap:12px;min-width:0}.main>*{min-width:0}.card{min-width:0;max-width:100%;overflow-x:hidden;padding:15px;border-radius:20px;background:linear-gradient(180deg,rgba(21,27,43,.9),rgba(12,16,28,.92));box-shadow:0 16px 30px rgba(0,0,0,.45),inset 0 0 0 1px rgba(255,255,255,.07)}.card h1,.card h2,.card h3{margin:0 0 8px;font-size:clamp(24px,7.2vw,34px);line-height:1.2;overflow-wrap:anywhere}.section-title{overflow-wrap:anywhere;word-break:break-word}.hero{padding:18px;background:linear-gradient(145deg,rgba(78,86,182,.52),rgba(54,123,184,.38) 58%,rgba(12,17,29,.95))}.hero h1{font-size:clamp(27px,8.4vw,34px);margin:6px 0;color:#FFFFFF;overflow-wrap:anywhere}.hero .lead{font-size:14px;line-height:1.45;color:rgba(234,240,255,.88)}.muted,.note{color:rgba(234,240,255,.76)}.kpi{color:#ffffff}.pill{padding:6px 10px;font-size:11px;color:rgba(234,240,255,.88);background:rgba(8,12,20,.35)}.grid{gap:10px;min-width:0}.grid>*{min-width:0}th{color:rgba(234,240,255,.82)}th,td{padding:10px 4px;font-size:13px;border-bottom:1px solid rgba(255,255,255,.12)}table{display:block;overflow-x:auto;white-space:nowrap;max-width:100%;width:100%}.toolbar{margin-bottom:8px;flex-direction:column;align-items:stretch}.toolbar .btn{width:100%}.mobile-tabs{position:fixed;left:10px;right:10px;bottom:calc(8px + env(safe-area-inset-bottom));z-index:50;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:6px;padding:8px;border-radius:22px;background:rgba(18,21,32,.96);border:1px solid rgba(255,255,255,.14);box-shadow:0 10px 30px rgba(0,0,0,.55),inset 0 0 0 1px rgba(255,255,255,.06);backdrop-filter:blur(16px)}.mobile-tabs a{display:grid;justify-items:center;gap:5px;text-decoration:none;color:rgba(234,240,255,.82);font-size:10px;font-weight:600;letter-spacing:.02em;padding:7px 2px;border-radius:14px}.mobile-tabs a::before{font-size:16px;line-height:1}.mobile-tabs a[data-tab="overview"]::before{content:"⌂"}.mobile-tabs a[data-tab="clienti"]::before{content:"👥"}.mobile-tabs a[data-tab="idkey"]::before{content:"🔑"}.mobile-tabs a[data-tab="allenamenti"]::before{content:"🏋️"}.mobile-tabs a[data-tab="nutrizione"]::before{content:"🥗"}.mobile-tabs a[data-tab="allenamenti_nutrizione"]::before{content:"🏋️🥗"}.mobile-tabs a[data-tab="accessi"]::before{content:"🔀"}.mobile-tabs a.active{color:#fff;background:linear-gradient(135deg,rgba(93,95,232,.95),rgba(56,167,221,.86));box-shadow:0 10px 20px rgba(90,100,255,.42)}.mobile-tabs a.active::before{transform:translateY(-1px)}.profile-modal{padding:10px}.profile-modal-card{padding:14px;border-radius:16px}.profile-modal-grid{grid-template-columns:1fr}}';
  echo '</style></head><body>';

  echo '<header class="topbar"><div class="container nav"><div class="brand"><img src="../media/logo.png" alt="AuraFit" class="logo" />AuraFit Professionista</div><div class="nav-actions"><button type="button" class="pill role-btn" data-profile-modal-open>' . h($roleBadge) . '</button><a class="btn" href="../logout.php" data-logout-trigger>Logout</a></div></div></header><div class="profile-modal" data-profile-modal aria-hidden="true"><div class="profile-modal-card" role="dialog" aria-modal="true" aria-labelledby="profile-title"><div class="profile-modal-head"><div><h2 id="profile-title" class="section-title">Modifica profilo professionista</h2><p class="muted" style="margin:0">Aggiorna i tuoi dati visibili ai clienti.</p></div><button type="button" class="profile-modal-close" data-profile-modal-close>Chiudi</button></div><form data-profile-form><div class="profile-modal-grid"><label class="field"><span>Nome</span><input name="nome" type="text" autocomplete="given-name" pattern="[A-Za-zÀ-ÖØ-öø-ÿ -]{1,60}" title="Usa solo lettere, spazi e trattini." value="' . h($professionistaProfileForm['nome']) . '" /></label><label class="field"><span>Cognome</span><input name="cognome" type="text" autocomplete="family-name" pattern="[A-Za-zÀ-ÖØ-öø-ÿ -]{1,60}" title="Usa solo lettere, spazi e trattini." value="' . h($professionistaProfileForm['cognome']) . '" /></label><label class="field full"><span>Email</span><input name="email" type="email" value="' . h($professionistaProfileForm['email']) . '" required autocomplete="email" /></label><label class="field"><span>Telefono</span><input name="telefono" type="tel" autocomplete="tel" inputmode="tel" pattern="[0-9+() -]{6,20}" title="Usa solo numeri e simboli telefonici (+, -, parentesi)." value="' . h($professionistaProfileForm['telefono']) . '" /></label><label class="field"><span>Specializzazione</span><input name="specializzazione" type="text" placeholder="Es. Ricomposizione corporea" value="' . h($professionistaProfileForm['specializzazione']) . '" /></label><label class="field full"><span>Biografia breve</span><textarea name="bio" rows="3" placeholder="Presentazione professionale">' . h($professionistaProfileForm['bio']) . '</textarea></label></div><div class="toolbar" style="margin-top:14px"><span class="note">Le modifiche vengono salvate sul browser corrente.</span><button class="btn primary" type="submit">Salva profilo</button></div><div class="okbox profile-feedback" data-profile-feedback>Profilo aggiornato con successo.</div></form></div></div><div class="profile-modal" data-logout-confirm-modal aria-hidden="true"><div class="profile-modal-card" role="dialog" aria-modal="true" aria-labelledby="logout-confirm-title" style="width:min(520px,100%);"><div class="profile-modal-head"><div><h3 id="logout-confirm-title" class="section-title" style="margin:0">Conferma uscita</h3><p class="muted" style="margin:8px 0 0">Confermi di voler uscire da AuraFit?</p></div></div><div class="toolbar" style="justify-content:flex-end;gap:10px;margin-top:14px"><button class="btn" type="button" data-logout-confirm-cancel>Annulla</button><button class="btn primary" type="button" data-logout-confirm-ok>Esci</button></div></div></div>';
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
  global $isPt, $isNutrizionista;

  $allenamentiNutrizioneVisible = $isPt || $isNutrizionista;
  $allenamentiNutrizioneHref = $isPt ? 'allenamenti.php' : 'nutrizione.php';

  $tabs = [
    'overview' => ['label' => 'Home', 'href' => 'overview.php', 'visible' => true],
    'idkey' => ['label' => 'ID-Key', 'href' => 'idkey.php', 'visible' => true],
    'clienti' => ['label' => 'Clienti', 'href' => 'clienti.php', 'visible' => true],
    'allenamenti_nutrizione' => ['label' => 'Allen./Nutr.', 'href' => $allenamentiNutrizioneHref, 'visible' => $allenamentiNutrizioneVisible],
    'accessi' => ['label' => 'Accessi', 'href' => 'accessi.php', 'visible' => true],
  ];

  $visibleTabs = array_filter($tabs, static function ($tab) {
    return !empty($tab['visible']);
  });
  if (count($visibleTabs) > 5) {
    $visibleTabs = [
      'overview' => $tabs['overview'],
      'idkey' => $tabs['idkey'],
      'clienti' => $tabs['clienti'],
      'allenamenti_nutrizione' => $tabs['allenamenti_nutrizione'],
      'accessi' => $tabs['accessi'],
    ];
  }

  $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');

  echo '</main></div><nav class="mobile-tabs" aria-label="Navigazione mobile">';
  foreach ($visibleTabs as $key => $tab) {
    $isActive = $current === $tab['href'] ? 'active' : '';
    echo '<a class="' . $isActive . '" data-tab="' . h((string)$key) . '" href="' . h($tab['href']) . '">' . h($tab['label']) . '</a>';
  }
  echo '</nav>';

  echo "<script>(function(){const body=document.body;const modal=document.querySelector('[data-profile-modal]');const openBtn=document.querySelector('[data-profile-modal-open]');const logoutTrigger=document.querySelector('[data-logout-trigger]');const logoutConfirmModal=document.querySelector('[data-logout-confirm-modal]');const logoutCancelBtn=document.querySelector('[data-logout-confirm-cancel]');const logoutOkBtn=document.querySelector('[data-logout-confirm-ok]');let pendingLogoutHref='';if(modal&&openBtn){const closeEls=modal.querySelectorAll('[data-profile-modal-close]');const form=modal.querySelector('[data-profile-form]');const feedback=modal.querySelector('[data-profile-feedback]');const storageKey='aurafit_professionista_profile';const fields=['nome','cognome','email','telefono','specializzazione','bio'];function save(payload){localStorage.setItem(storageKey,JSON.stringify(payload));}function sanitizeValue(name,value){const rules={nome:/[^A-Za-zÀ-ÖØ-öø-ÿ'’ -]/g,cognome:/[^A-Za-zÀ-ÖØ-öø-ÿ'’ -]/g,telefono:/[^0-9+() -]/g};const rule=rules[name];return rule?value.replace(rule,''):value;}function getData(){const payload={};fields.forEach((name)=>{const input=form.elements[name];if(input){const sanitized=sanitizeValue(name,String(input.value||''));if(sanitized!==String(input.value||'')){input.value=sanitized;}payload[name]=sanitized.trim();}});return payload;}fields.forEach((name)=>{const input=form.elements[name];if(!input){return;}input.addEventListener('input',()=>{const sanitized=sanitizeValue(name,String(input.value||''));if(sanitized!==String(input.value||'')){input.value=sanitized;}});});function openModal(){modal.classList.add('open');modal.setAttribute('aria-hidden','false');body.style.overflow='hidden';}function closeModal(){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');if(!logoutConfirmModal||!logoutConfirmModal.classList.contains('open')){body.style.overflow='';}}openBtn.addEventListener('click',openModal);closeEls.forEach((el)=>el.addEventListener('click',closeModal));modal.addEventListener('click',(event)=>{if(event.target===modal){closeModal();}});form.addEventListener('submit',async(event)=>{event.preventDefault();const data=getData();save(data);const formData=new FormData(form);formData.set('profileScope','professionista');try{const response=await fetch(window.location.pathname,{method:'POST',body:formData,headers:{'X-Requested-With':'XMLHttpRequest'}});const payload=await response.json();if(!response.ok||!payload.ok){throw new Error((payload&&payload.message)||'Errore salvataggio');}if(feedback){feedback.textContent=payload.message||'Profilo aggiornato con successo.';feedback.classList.add('visible');setTimeout(()=>feedback.classList.remove('visible'),2200);}closeModal();window.location.reload();}catch(error){if(feedback){feedback.textContent=error.message||'Errore durante il salvataggio.';feedback.classList.add('visible');setTimeout(()=>feedback.classList.remove('visible'),3000);}}});}function openLogoutConfirm(href){pendingLogoutHref=href;logoutConfirmModal?.classList.add('open');logoutConfirmModal?.setAttribute('aria-hidden','false');body.style.overflow='hidden';}function closeLogoutConfirm(){logoutConfirmModal?.classList.remove('open');logoutConfirmModal?.setAttribute('aria-hidden','true');pendingLogoutHref='';if(!modal||!modal.classList.contains('open')){body.style.overflow='';}}logoutTrigger?.addEventListener('click',(event)=>{event.preventDefault();openLogoutConfirm(logoutTrigger.getAttribute('href')||'../logout.php');});logoutCancelBtn?.addEventListener('click',closeLogoutConfirm);logoutConfirmModal?.addEventListener('click',(event)=>{if(event.target===logoutConfirmModal){closeLogoutConfirm();}});document.addEventListener('keydown',(event)=>{if(event.key==='Escape'){if(logoutConfirmModal?.classList.contains('open')){closeLogoutConfirm();return;}if(modal?.classList.contains('open')){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');if(!logoutConfirmModal||!logoutConfirmModal.classList.contains('open')){body.style.overflow='';}}}});logoutOkBtn?.addEventListener('click',()=>{if(!pendingLogoutHref){closeLogoutConfirm();return;}window.location.href=pendingLogoutHref;});})();</script>";
  if ($scripts !== '') {
    echo $scripts;
  }
  echo '</body></html>';
}
