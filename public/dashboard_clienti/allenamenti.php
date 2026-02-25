<?php
require __DIR__ . '/common.php';

renderStart('Allenamenti cliente', 'allenamenti', $email);
?>
<section class="card hero">
  <span class="pill">Allenamenti</span>
  <h1>Programma settimanale</h1>
  <p class="lead">Vista rapida del tuo piano, con stato di completamento e attività da pianificare.</p>
</section>

<section class="grid">
  <article class="card span-8">
    <h3 class="section-title">Sessioni assegnate</h3>
    <table>
      <thead><tr><th>Giorno</th><th>Allenamento</th><th>Stato</th></tr></thead>
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

  <article class="card span-4">
    <h3 class="section-title">Checklist pre-workout</h3>
    <ul class="list">
      <li>Idratazione: almeno 500 ml prima della sessione.</li>
      <li>Riscaldamento: 8-10 minuti.</li>
      <li>Compila RPE a fine allenamento.</li>
    </ul>
    <div class="okbox" style="margin-top:12px">Suggerimento: apri il diario al termine per inviare feedback al coach.</div>
  </article>
</section>
<?php
renderEnd();
