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
  header('Location: login.php');
  exit;
}

$roles = array_map('strtolower', (array)($user['roles'] ?? []));
$isPt = in_array('pt', $roles, true);
$isNutrizionista = in_array('nutrizionista', $roles, true);

if (!$isPt && !$isNutrizionista) {
  header('Location: login.php');
  exit;
}

$email = htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$roleBadge = $isPt && $isNutrizionista ? 'PT + Nutrizionista' : ($isPt ? 'Personal Trainer' : 'Nutrizionista');

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

$clientiAttivi = [
  ['nome' => 'Giulia Rinaldi', 'stato' => 'Attiva', 'associazione' => '2025-11-14', 'ultimoUpdate' => 'Peso aggiornato 2h fa'],
  ['nome' => 'Marco Testa', 'stato' => 'Attiva', 'associazione' => '2025-09-02', 'ultimoUpdate' => 'Workout completato oggi'],
  ['nome' => 'Silvia Martini', 'stato' => 'Attiva', 'associazione' => '2025-12-10', 'ultimoUpdate' => 'Diario alimentare ieri'],
];

$clientiTerminati = [
  ['nome' => 'Francesco L.', 'stato' => 'Terminata', 'chiusura' => '2026-01-20', 'nota' => 'Chat bloccata automaticamente (RF-016)'],
  ['nome' => 'Valeria G.', 'stato' => 'Terminata', 'chiusura' => '2025-12-08', 'nota' => 'Storico mantenuto per visibilità cliente (RF-015)'],
];

$idKeys = [
  ['key' => 'AF-PT-9821', 'stato' => 'Attiva', 'creata' => '2026-01-11'],
  ['key' => 'AF-PT-9822', 'stato' => 'Sospesa', 'creata' => '2026-01-12'],
  ['key' => 'AF-PT-9823', 'stato' => 'Eliminata', 'creata' => '2026-01-14'],
  ['key' => 'AF-PT-9824', 'stato' => 'Attiva', 'creata' => '2026-01-21'],
];

$canGenerateIdKey = $overview['clientiAttivi'] < $overview['idKeyTotaliPiano'];

$pesoSerie = [77.4, 76.9, 76.7, 76.1, 75.8, 75.5];
$performanceSerie = [62, 66, 68, 71, 74, 78];
$mesi = ['Ott', 'Nov', 'Dic', 'Gen', 'Feb', 'Mar'];
$reportMensili = [
  ['mese' => 'Gennaio 2026', 'stato' => 'Disponibile'],
  ['mese' => 'Febbraio 2026', 'stato' => 'Disponibile'],
  ['mese' => 'Marzo 2026', 'stato' => 'In elaborazione server-side'],
];
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dashboard Professionista — AuraFit</title>
  <meta name="theme-color" content="#0B0F19" />
  <style>
    :root{
      --bg:#070A12;
      --card:rgba(255,255,255,.06);
      --text:#EAF0FF;
      --muted:rgba(234,240,255,.68);
      --line:rgba(234,240,255,.12);
      --brand1:#6D5EF3;
      --brand2:#2EE1A5;
      --brand3:#4CC9F0;
      --danger:#ff6f89;
      --warn:#ffd166;
      --ok:#2EE1A5;
      --max:1200px;
      --radius:18px;
      --sans: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      color:var(--text);
      font-family:var(--sans);
      background:var(--bg);
      min-height:100vh;
      position:relative;
    }
    body::before{
      content:""; position:fixed; inset:0; z-index:-1;
      background:
        radial-gradient(1200px 800px at 18% -12%, rgba(109,94,243,.28), transparent 58%),
        radial-gradient(1100px 700px at 92% 12%, rgba(76,201,240,.16), transparent 58%),
        radial-gradient(900px 700px at 55% 98%, rgba(46,225,165,.12), transparent 60%);
    }
    .container{max-width:var(--max); margin:0 auto; padding:0 18px}
    .topbar{position:sticky; top:0; z-index:30; border-bottom:1px solid rgba(255,255,255,.08); backdrop-filter:blur(16px); background:rgba(7,10,18,.66)}
    .nav{display:flex; align-items:center; justify-content:space-between; min-height:74px; gap:12px}
    .brand{display:flex; align-items:center; gap:10px; font-weight:700}
    .logo{width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,var(--brand1),var(--brand2)); box-shadow:0 10px 30px rgba(109,94,243,.2)}
    .nav-actions{display:flex; gap:8px; align-items:center}
    .pill{display:inline-flex; align-items:center; padding:8px 12px; border-radius:999px; border:1px solid var(--line); color:var(--muted); background:rgba(255,255,255,.04); font-size:12px}
    .btn{display:inline-flex; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:10px 14px; text-decoration:none; color:var(--text); background:rgba(255,255,255,.05); font-weight:650; cursor:pointer}
    .btn.primary{background:linear-gradient(135deg, rgba(109,94,243,.92), rgba(76,201,240,.72)); color:#061018; border-color:rgba(109,94,243,.55)}
    .btn.warn{border-color:rgba(255,209,102,.4)}
    .btn.danger{border-color:rgba(255,111,137,.45)}
    .layout{display:grid; grid-template-columns:260px 1fr; gap:16px; padding:20px 0 34px}
    .side,.card{background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04)); border-radius:var(--radius); box-shadow:0 10px 40px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.05)}
    .side{padding:14px; height:fit-content; position:sticky; top:90px}
    .menu{display:grid; gap:8px}
    .menu a{color:var(--muted); text-decoration:none; border:1px solid transparent; padding:10px 12px; border-radius:12px; font-size:14px}
    .menu a:hover,.menu a.active{color:var(--text); background:rgba(255,255,255,.06); border-color:rgba(255,255,255,.08)}
    .main{display:grid; gap:14px}
    .hero{padding:22px}
    h1{margin:8px 0 10px; font-size:clamp(30px,4vw,44px); letter-spacing:-.02em}
    .lead{margin:0; color:var(--muted)}
    .grid{display:grid; grid-template-columns:repeat(12, 1fr); gap:14px}
    .card{padding:18px}
    .span-3{grid-column:span 3}.span-4{grid-column:span 4}.span-5{grid-column:span 5}.span-6{grid-column:span 6}.span-7{grid-column:span 7}.span-8{grid-column:span 8}.span-12{grid-column:span 12}
    .kpi{font-size:32px; font-weight:800; margin:6px 0 4px}
    .muted{color:var(--muted)}
    .section-title{margin:0 0 10px; font-size:20px}
    .list{margin:10px 0 0; padding-left:16px; color:var(--muted); line-height:1.6}
    table{width:100%; border-collapse:collapse; font-size:14px}
    th,td{padding:10px 8px; text-align:left; border-bottom:1px solid rgba(255,255,255,.08)}
    th{color:var(--muted); font-weight:600}
    .status{display:inline-flex; align-items:center; border-radius:999px; border:1px solid var(--line); padding:4px 9px; font-size:12px}
    .status.ok{color:var(--ok)} .status.warn{color:var(--warn)} .status.danger{color:var(--danger)}
    .toolbar{display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:10px}
    .field{display:grid; gap:6px}
    .field input,.field textarea,.field select{width:100%; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.03); color:var(--text); border-radius:12px; padding:10px 12px; font:inherit}
    .two{display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px}
    .three{display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px}
    .note{font-size:12px; color:var(--muted); margin-top:6px}
    .divider{height:1px; background:rgba(255,255,255,.08); margin:14px 0}
    .chart-wrap{height:260px}
    .tabs{display:flex; gap:8px; margin-bottom:10px; flex-wrap:wrap}
    .tab{padding:8px 10px; border:1px solid rgba(255,255,255,.12); border-radius:10px; background:rgba(255,255,255,.03); color:var(--muted)}
    .tab.active{color:var(--text); background:rgba(255,255,255,.08)}
    @media (max-width: 1050px){
      .layout{grid-template-columns:1fr}
      .side{position:static}
      .span-3,.span-4,.span-5,.span-6,.span-7,.span-8{grid-column:span 12}
      .two,.three{grid-template-columns:1fr}
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="container nav">
      <div class="brand"><div class="logo" aria-hidden="true"></div>AuraFit Professionista</div>
      <div class="nav-actions">
        <span class="pill"><?= htmlspecialchars($roleBadge, ENT_QUOTES, 'UTF-8') ?></span>
        <a class="btn" href="logout.php">Logout</a>
      </div>
    </div>
  </header>

  <div class="container layout">
    <aside class="side">
      <div class="menu">
        <a class="active" href="#overview">Overview</a>
        <a href="#clienti">Gestione clienti</a>
        <a href="#idkey">Gestione ID-Key</a>
        <?php if ($isPt): ?><a href="#allenamenti">Allenamenti (PT)</a><?php endif; ?>
        <?php if ($isNutrizionista): ?><a href="#nutrizione">Nutrizione (Nutrizionista)</a><?php endif; ?>
        <a href="#cross-access">Accessi incrociati</a>
        <a href="#report">Monitoraggio & Report</a>
      </div>
    </aside>

    <main class="main">
      <section id="overview" class="card hero">
        <span class="pill">Home dashboard</span>
        <h1>Ciao, <?= $email ?></h1>
        <p class="lead">Vista professionista completa e scalabile per gestione clienti, ID-Key, piani di allenamento/nutrizione, monitoraggio progressi e reportistica.</p>
      </section>

      <section class="grid">
        <article class="card span-3"><h3>Clienti attivi</h3><p class="kpi"><?= $overview['clientiAttivi'] ?></p><p class="muted">Associati e operativi</p></article>
        <article class="card span-3"><h3>ID-Key disponibili</h3><p class="kpi"><?= $overview['idKeyDisponibili'] ?></p><p class="muted">Su <?= $overview['idKeyTotaliPiano'] ?> totali piano</p></article>
        <article class="card span-3"><h3>Abbonamento</h3><p class="kpi" style="font-size:22px"><?= htmlspecialchars($overview['piano'], ENT_QUOTES, 'UTF-8') ?></p><p class="muted">Stato: <?= htmlspecialchars($overview['pianoStato'], ENT_QUOTES, 'UTF-8') ?></p></article>
        <article class="card span-3"><h3>Rinnovo</h3><p class="kpi" style="font-size:22px"><?= htmlspecialchars($overview['rinnovo'], ENT_QUOTES, 'UTF-8') ?></p><p class="muted">Fatturazione automatica</p></article>

        <article class="card span-6">
          <h3 class="section-title">Ultime attività clienti</h3>
          <ul class="list">
            <?php foreach ($latestActivities as $activity): ?>
              <li><strong><?= htmlspecialchars($activity['cliente'], ENT_QUOTES, 'UTF-8') ?></strong> — <?= htmlspecialchars($activity['evento'], ENT_QUOTES, 'UTF-8') ?> <span class="muted">(<?= htmlspecialchars($activity['orario'], ENT_QUOTES, 'UTF-8') ?>)</span></li>
            <?php endforeach; ?>
          </ul>
        </article>

        <article class="card span-6">
          <h3 class="section-title">Notifiche recenti</h3>
          <ul class="list">
            <?php foreach ($notifiche as $notifica): ?>
              <li><?= htmlspecialchars($notifica, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
          </ul>
        </article>

        <article class="card span-12 chart-wrap">
          <h3 class="section-title">Grafico riepilogativo progressi</h3>
          <canvas id="overviewChart" aria-label="Grafico progressi" role="img"></canvas>
        </article>
      </section>

      <section id="clienti" class="card">
        <h2 class="section-title">Gestione Clienti (RF-004, RF-013, RF-014)</h2>
        <div class="toolbar">
          <span class="muted">Regole: alla cessazione associazione, chat bloccata automaticamente (RF-016). Storico mantenuto lato cliente in caso cambio professionista (RF-015).</span>
        </div>
        <table>
          <thead><tr><th>Cliente</th><th>Stato associazione</th><th>Data associazione</th><th>Ultimo aggiornamento</th><th>Azioni</th></tr></thead>
          <tbody>
          <?php foreach ($clientiAttivi as $cliente): ?>
            <tr>
              <td><?= htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><span class="status ok"><?= htmlspecialchars($cliente['stato'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td><?= htmlspecialchars($cliente['associazione'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($cliente['ultimoUpdate'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <button class="btn">Scheda cliente</button>
                <button class="btn danger">Termina associazione</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="divider"></div>
        <h3>Storico clienti terminati</h3>
        <table>
          <thead><tr><th>Cliente</th><th>Stato</th><th>Data chiusura</th><th>Nota regola</th></tr></thead>
          <tbody>
          <?php foreach ($clientiTerminati as $cliente): ?>
            <tr>
              <td><?= htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><span class="status warn"><?= htmlspecialchars($cliente['stato'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td><?= htmlspecialchars($cliente['chiusura'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($cliente['nota'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </section>

      <section id="idkey" class="card">
        <h2 class="section-title">Gestione ID-Key (RF-020, RF-021, RF-018)</h2>
        <div class="toolbar">
          <?php if ($canGenerateIdKey): ?>
            <button class="btn primary">Genera nuova ID-Key</button>
            <span class="muted">Verifica limite piano superata: puoi generare nuove chiavi.</span>
          <?php else: ?>
            <button class="btn primary" disabled>Genera nuova ID-Key</button>
            <span class="muted">Blocco attivo: raggiunto limite clienti piano (RF-018).</span>
          <?php endif; ?>
        </div>
        <table>
          <thead><tr><th>ID-Key</th><th>Stato</th><th>Creata il</th><th>Azioni</th></tr></thead>
          <tbody>
          <?php foreach ($idKeys as $key): ?>
            <?php
              $statusClass = $key['stato'] === 'Attiva' ? 'ok' : ($key['stato'] === 'Sospesa' ? 'warn' : 'danger');
            ?>
            <tr>
              <td><code><?= htmlspecialchars($key['key'], ENT_QUOTES, 'UTF-8') ?></code></td>
              <td><span class="status <?= $statusClass ?>"><?= htmlspecialchars($key['stato'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td><?= htmlspecialchars($key['creata'], ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <button class="btn warn">Sospendi</button>
                <button class="btn">Riattiva</button>
                <button class="btn danger">Elimina</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </section>

      <?php if ($isPt): ?>
      <section id="allenamenti" class="card">
        <h2 class="section-title">Allenamenti (solo PT) — RF-005</h2>
        <div class="tabs">
          <span class="tab active">Nuovo programma</span><span class="tab">Modifica</span><span class="tab">Duplica</span>
        </div>
        <div class="two">
          <div class="field"><label>Nome programma</label><input type="text" value="Strength Base - 8 settimane" /></div>
          <div class="field"><label>Assegna a cliente associato</label><select><option>Giulia Rinaldi</option><option>Marco Testa</option></select></div>
        </div>
        <div class="divider"></div>
        <h3>Editor esercizi dinamico</h3>
        <div class="three">
          <div class="field"><label>Nome esercizio</label><input type="text" value="Squat bilanciere" /></div>
          <div class="field"><label>Serie</label><input type="number" value="4" /></div>
          <div class="field"><label>Ripetizioni</label><input type="text" value="6-8" /></div>
          <div class="field"><label>Carico</label><input type="text" value="80kg" /></div>
          <div class="field"><label>Recupero</label><input type="text" value="120s" /></div>
          <div class="field"><label>Media upload</label><input type="text" placeholder="Link video o media" /></div>
        </div>
        <div class="field" style="margin-top:10px"><label>Note</label><textarea rows="3">Controllare profondità e mantenere core attivo.</textarea></div>
        <div class="toolbar" style="margin-top:12px"><button class="btn primary">Salva su DB</button><button class="btn">Aggiungi esercizio</button><button class="btn">Duplica programma</button></div>
        <p class="note">I pulsanti rappresentano endpoint applicativi previsti per CRUD programmi e assegnazione cliente.</p>
      </section>
      <?php endif; ?>

      <?php if ($isNutrizionista): ?>
      <section id="nutrizione" class="card">
        <h2 class="section-title">Nutrizione (solo Nutrizionista) — RF-006</h2>
        <div class="two">
          <div class="field"><label>Nome piano alimentare</label><input type="text" value="Recomposition Primavera" /></div>
          <div class="field"><label>Cliente associato</label><select><option>Silvia Martini</option><option>Giulia Rinaldi</option></select></div>
        </div>
        <div class="divider"></div>
        <h3>Inserimento pasti e alimenti</h3>
        <div class="three">
          <div class="field"><label>Pasto</label><input type="text" value="Colazione" /></div>
          <div class="field"><label>Alimento</label><input type="text" value="Yogurt greco 0%" /></div>
          <div class="field"><label>Quantità (g)</label><input type="number" value="200" /></div>
          <div class="field"><label>Proteine</label><input type="number" value="20" /></div>
          <div class="field"><label>Carboidrati</label><input type="number" value="8" /></div>
          <div class="field"><label>Grassi</label><input type="number" value="2" /></div>
        </div>
        <div class="toolbar" style="margin-top:12px"><button class="btn primary">Salva piano</button><button class="btn">Aggiungi pasto</button><button class="btn">Visualizza diario cliente</button><button class="btn">Modifica piano</button></div>
        <p class="note">Calcolo macronutrienti predisposto lato server/client con aggregazioni per pasto e giorno.</p>
      </section>
      <?php endif; ?>

      <section id="cross-access" class="card">
        <h2 class="section-title">Accessi Incrociati (RF-011, RF-012)</h2>
        <?php if ($isPt): ?>
          <p class="muted">Ruolo PT: visualizzazione dati nutrizionali del cliente in <strong>sola lettura</strong>.</p>
          <table><thead><tr><th>Cliente</th><th>Dati nutrizionali visibili</th><th>Permessi</th></tr></thead><tbody><tr><td>Silvia Martini</td><td>Kcal, macro, compliance piano</td><td><span class="status warn">Read Only</span></td></tr></tbody></table>
        <?php endif; ?>
        <?php if ($isNutrizionista): ?>
          <div class="divider"></div>
          <p class="muted">Ruolo Nutrizionista: visualizzazione programmi allenamento in <strong>sola lettura</strong>.</p>
          <table><thead><tr><th>Cliente</th><th>Scheda allenamento visibile</th><th>Permessi</th></tr></thead><tbody><tr><td>Marco Testa</td><td>Split Upper/Lower + storico progressioni</td><td><span class="status warn">Read Only</span></td></tr></tbody></table>
        <?php endif; ?>
        <p class="note">Controllo ruolo implementato server-side all'ingresso pagina e nella renderizzazione condizionale delle sezioni.</p>
      </section>

      <section id="report" class="card">
        <h2 class="section-title">Monitoraggio e Report (RF-010)</h2>
        <div class="toolbar">
          <div class="two" style="min-width:380px">
            <div class="field"><label>Filtro periodo</label><select><option>Ultimi 30 giorni</option><option>Ultimi 3 mesi</option><option>Anno corrente</option></select></div>
            <div class="field"><label>Cliente</label><select><option>Tutti i clienti</option><option>Giulia Rinaldi</option><option>Silvia Martini</option></select></div>
          </div>
          <button class="btn primary">Genera report mensile automatico</button>
        </div>
        <div class="grid">
          <article class="card span-6 chart-wrap"><h3>Andamento peso</h3><canvas id="pesoChart" aria-label="Grafico peso" role="img"></canvas></article>
          <article class="card span-6 chart-wrap"><h3>Andamento performance</h3><canvas id="performanceChart" aria-label="Grafico performance" role="img"></canvas></article>
        </div>
        <div class="divider"></div>
        <h3>Report mensili automatici</h3>
        <table>
          <thead><tr><th>Mese</th><th>Stato elaborazione</th><th>Download</th></tr></thead>
          <tbody>
            <?php foreach ($reportMensili as $report): ?>
              <tr>
                <td><?= htmlspecialchars($report['mese'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($report['stato'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><button class="btn">Download PDF</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="note">L'elaborazione dei report è prevista lato server con dataset consolidati per periodo e cliente.</p>
      </section>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const labels = <?= json_encode($mesi, JSON_UNESCAPED_UNICODE) ?>;
    const pesoData = <?= json_encode($pesoSerie, JSON_UNESCAPED_UNICODE) ?>;
    const performanceData = <?= json_encode($performanceSerie, JSON_UNESCAPED_UNICODE) ?>;

    const axisColor = 'rgba(234,240,255,.55)';
    const gridColor = 'rgba(234,240,255,.12)';

    const baseOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { labels: { color: axisColor } } },
      scales: {
        x: { ticks: { color: axisColor }, grid: { color: gridColor } },
        y: { ticks: { color: axisColor }, grid: { color: gridColor } }
      }
    };

    new Chart(document.getElementById('overviewChart'), {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Peso medio (kg)', data: pesoData, borderColor: '#4CC9F0', backgroundColor: 'rgba(76,201,240,.18)', fill: true, tension: .35 },
          { label: 'Performance score', data: performanceData, borderColor: '#2EE1A5', backgroundColor: 'rgba(46,225,165,.12)', fill: true, tension: .35 }
        ]
      },
      options: baseOptions
    });

    new Chart(document.getElementById('pesoChart'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{ label: 'Peso (kg)', data: pesoData, backgroundColor: 'rgba(76,201,240,.65)', borderRadius: 8 }]
      },
      options: baseOptions
    });

    new Chart(document.getElementById('performanceChart'), {
      type: 'line',
      data: {
        labels,
        datasets: [{ label: 'Performance', data: performanceData, borderColor: '#6D5EF3', backgroundColor: 'rgba(109,94,243,.2)', fill: true, tension: .35 }]
      },
      options: baseOptions
    });
  </script>
</body>
</html>
