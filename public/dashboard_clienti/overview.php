<?php
require __DIR__ . '/common.php';

renderStart('Overview dashboard', 'overview', $email);

$sessionProgress = $overview['sessioniSettimana'] > 0
  ? (int)round(($overview['sessioniCompletate'] / $overview['sessioniSettimana']) * 100)
  : 0;

$mealTodayKcal = array_reduce($pastiOggi, static function (int $carry, array $meal): int {
  return $carry + (int)($meal['calorie'] ?? 0);
}, 0);
?>
<style>
  .overview-shell { display:grid; gap:14px; }
  .hero-redesign { position:relative; overflow:hidden; }
  .hero-redesign::after { content:''; position:absolute; inset:auto -40px -100px auto; width:260px; height:260px; border-radius:50%; background:radial-gradient(circle, rgba(46,225,165,.28), transparent 62%); pointer-events:none; }
  .hero-row { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; }
  .hero-title { margin:8px 0 6px; font-size:clamp(30px,4.2vw,46px); }
  .hero-sub { margin:0; color:var(--muted); max-width:720px; }
  .hero-stats { display:flex; gap:10px; flex-wrap:wrap; }
  .chip { border:1px solid var(--line); border-radius:999px; padding:8px 12px; font-size:12px; color:var(--muted); background:rgba(255,255,255,.03); }
  .stat-grid { display:grid; gap:14px; grid-template-columns:repeat(4,minmax(0,1fr)); }
  .stat-card .kpi { margin:2px 0; }
  .progress-track { width:100%; height:9px; border-radius:999px; background:rgba(255,255,255,.1); overflow:hidden; margin-top:10px; }
  .progress-fill { height:100%; background:linear-gradient(90deg,var(--brand2),var(--brand3)); }
  .section-grid { display:grid; gap:14px; grid-template-columns:repeat(2,minmax(0,1fr)); }
  .clean-list { list-style:none; padding:0; margin:0; display:grid; gap:10px; }
  .clean-list li { display:flex; justify-content:space-between; gap:10px; padding:10px 12px; border:1px solid rgba(255,255,255,.09); border-radius:12px; background:rgba(255,255,255,.025); }
  .clean-list strong { font-size:14px; }
  .cal { font-weight:700; color:#fff; white-space:nowrap; }
  .timeline { list-style:none; padding:0; margin:0; display:grid; gap:10px; }
  .timeline li { display:grid; grid-template-columns:auto 1fr; gap:10px; align-items:flex-start; }
  .dot { width:10px; height:10px; margin-top:5px; border-radius:50%; background:var(--brand3); box-shadow:0 0 0 5px rgba(76,201,240,.15); }
  .timeline p { margin:0; color:var(--muted); font-size:14px; }
  .badge-ok { color:var(--ok); }
  .badge-warn { color:var(--warn); }

  @media (max-width:1050px) {
    .stat-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .section-grid { grid-template-columns:1fr; }
  }
  @media (max-width:640px) {
    .hero-row { align-items:flex-start; }
    .stat-grid { grid-template-columns:1fr; }
    .clean-list li { flex-direction:column; }
  }
</style>

<div class="overview-shell">
  <section class="card hero hero-redesign">
    <span class="pill">Panoramica cliente</span>
    <div class="hero-row">
      <div>
        <h1 class="hero-title">Ciao, <?= $saluto ?></h1>
        <p class="hero-sub">Qui trovi i tuoi dati aggiornati: allenamenti, alimentazione e prossimo appuntamento con il coach, in un layout ottimizzato per desktop e mobile.</p>
      </div>
      <div class="hero-stats">
        <span class="chip">Coach: <?= h($coachAssegnato) ?></span>
        <span class="chip">Nutrizionista: <?= h($nutrizionistaAssegnato) ?></span>
      </div>
    </div>
  </section>

  <section class="stat-grid">
    <article class="card stat-card">
      <h3>Sessioni settimana</h3>
      <p class="kpi"><?= $overview['sessioniCompletate'] ?>/<?= $overview['sessioniSettimana'] ?></p>
      <p class="muted">Completamento: <?= $sessionProgress ?>%</p>
      <div class="progress-track"><div class="progress-fill" style="width: <?= $sessionProgress ?>%"></div></div>
    </article>
    <article class="card stat-card">
      <h3>Aderenza nutrizione</h3>
      <p class="kpi"><?= $overview['aderenzaNutrizione'] ?>%</p>
      <p class="muted">Ultimi 30 giorni registrati</p>
    </article>
    <article class="card stat-card">
      <h3>Peso attuale</h3>
      <p class="kpi"><?= number_format($overview['pesoAttuale'], 1, ',', '.') ?> kg</p>
      <p class="muted">Trend: in riduzione costante</p>
    </article>
    <article class="card stat-card">
      <h3>Prossimo check-in</h3>
      <p class="kpi" style="font-size:26px"><?= h($overview['prossimoCheckIn']) ?></p>
      <p class="muted">Conferma via chat supporto</p>
    </article>
  </section>

  <section class="section-grid">
    <article class="card">
      <h3 class="section-title">Allenamenti della settimana</h3>
      <table>
        <thead><tr><th>Giorno</th><th>Sessione</th><th>Stato</th></tr></thead>
        <tbody>
          <?php foreach ($allenamentiSettimanali as $allenamento): ?>
            <?php $statusClass = $allenamento['stato'] === 'Completato' ? 'ok' : 'warn'; ?>
            <tr>
              <td><?= h($allenamento['giorno']) ?></td>
              <td><?= h($allenamento['nome']) ?></td>
              <td><span class="status <?= $statusClass ?>"><?= h($allenamento['stato']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </article>

    <article class="card">
      <h3 class="section-title">Pasti di oggi</h3>
      <ul class="clean-list">
        <?php foreach ($pastiOggi as $pasto): ?>
          <li>
            <div>
              <strong><?= h($pasto['fascia']) ?></strong>
              <p class="muted" style="margin:2px 0 0;"><?= h($pasto['voce']) ?></p>
            </div>
            <span class="cal"><?= (int)$pasto['calorie'] ?> kcal</span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="note">Totale giornaliero registrato: <?= $mealTodayKcal ?> kcal.</p>
    </article>
  </section>

  <section class="section-grid">
    <article class="card">
      <h3 class="section-title">Notifiche operative</h3>
      <ul class="timeline">
        <?php foreach ($notifiche as $notifica): ?>
          <li><span class="dot"></span><p><?= h($notifica) ?></p></li>
        <?php endforeach; ?>
      </ul>
    </article>

    <article class="card">
      <h3 class="section-title">Stato rapido</h3>
      <ul class="clean-list">
        <li><strong>Workout completati</strong><span class="badge-ok"><?= $overview['sessioniCompletate'] ?> fatti</span></li>
        <li><strong>Workout programmati</strong><span class="badge-warn"><?= $overview['sessioniSettimana'] - $overview['sessioniCompletate'] ?> da completare</span></li>
        <li><strong>Aderenza nutrizionale</strong><span class="badge-ok"><?= $overview['aderenzaNutrizione'] ?>%</span></li>
      </ul>
    </article>
  </section>
</div>
<?php
renderEnd();
