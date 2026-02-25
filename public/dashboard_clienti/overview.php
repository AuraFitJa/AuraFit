<?php
require __DIR__ . '/common.php';

renderStart('Overview dashboard', 'overview', $email);
?>
<section class="card hero">
  <span class="pill">Home cliente</span>
  <h1>Ciao, <?= $saluto ?></h1>
  <p class="lead">Questa dashboard cliente riprende il design professionista e mette in evidenza allenamenti, nutrizione e progressi in un unico posto.</p>
</section>

<section class="grid">
  <article class="card span-3"><h3>Sessioni settimana</h3><p class="kpi"><?= $overview['sessioniCompletate'] ?>/<?= $overview['sessioniSettimana'] ?></p><p class="muted">Completamento programma</p></article>
  <article class="card span-3"><h3>Aderenza nutrizione</h3><p class="kpi"><?= $overview['aderenzaNutrizione'] ?>%</p><p class="muted">Ultimi 30 giorni</p></article>
  <article class="card span-3"><h3>Peso attuale</h3><p class="kpi"><?= number_format($overview['pesoAttuale'], 1, ',', '.') ?> kg</p><p class="muted">Trend in miglioramento</p></article>
  <article class="card span-3"><h3>Prossimo check-in</h3><p class="kpi" style="font-size:28px"><?= h($overview['prossimoCheckIn']) ?></p><p class="muted">Con <?= h($coachAssegnato) ?></p></article>

  <article class="card span-6">
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

  <article class="card span-6">
    <h3 class="section-title">Notifiche</h3>
    <ul class="list">
      <?php foreach ($notifiche as $notifica): ?>
        <li><?= h($notifica) ?></li>
      <?php endforeach; ?>
    </ul>
    <p class="note">Coach assegnato: <?= h($coachAssegnato) ?> · Nutrizionista: <?= h($nutrizionistaAssegnato) ?></p>
  </article>
</section>
<?php
renderEnd();
