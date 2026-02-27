<?php
require __DIR__ . '/common.php';

renderStart('Overview dashboard', 'overview', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<style>
@media (max-width: 900px) {
  .mobile-order-idkey {
    order: 1;
  }

  .mobile-order-clienti {
    order: 2;
  }
}
</style>

<section class="card hero">
  <span class="pill">Home dashboard</span>
  <h1>Ciao, <?= $email ?></h1>
  <p class="lead">Vista professionista completa e scalabile per gestione clienti, ID-Key, piani di allenamento/nutrizione, monitoraggio progressi e reportistica.</p>
</section>

<section class="grid">
  <article class="card span-3 mobile-order-clienti"><h3>Clienti attivi</h3><p class="kpi"><?= $overview['clientiAttivi'] ?></p><p class="muted">Associati e operativi</p></article>
  <article class="card span-3 mobile-order-idkey"><h3>ID-Key disponibili</h3><p class="kpi"><?= $overview['idKeyDisponibili'] ?></p><p class="muted">Su <?= $overview['idKeyTotaliPiano'] ?> totali piano</p></article>
  <article class="card span-3"><h3>Abbonamento</h3><p class="kpi" style="font-size:40px"><?= h($overview['piano']) ?></p><p class="muted">Stato: <?= h($overview['pianoStato']) ?></p></article>
  <article class="card span-3"><h3>Rinnovo</h3><p class="kpi" style="font-size:40px"><?= h($overview['rinnovo']) ?></p><p class="muted">Fatturazione automatica</p></article>

  <article class="card span-6">
    <h3 class="section-title">Ultime attività clienti</h3>
    <ul class="list">
      <?php foreach ($latestActivities as $activity): ?>
        <li><strong><?= h($activity['cliente']) ?></strong> — <?= h($activity['evento']) ?> (<?= h($activity['orario']) ?>)</li>
      <?php endforeach; ?>
    </ul>
  </article>

  <article class="card span-6">
    <h3 class="section-title">Notifiche recenti</h3>
    <ul class="list">
      <?php foreach ($notifiche as $notifica): ?>
        <li><?= h($notifica) ?></li>
      <?php endforeach; ?>
    </ul>
  </article>
</section>
<?php
renderEnd();
