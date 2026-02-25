<?php
require __DIR__ . '/common.php';

renderStart('Progressi cliente', 'progressi', $email);
?>
<section class="card hero">
  <span class="pill">Progressi</span>
  <h1>Andamento ultimi 6 mesi</h1>
  <p class="lead">Monitoraggio rapido di peso e aderenza. I dati definitivi saranno collegati ai record DB nelle prossime iterazioni.</p>
</section>

<section class="grid">
  <article class="card span-6">
    <h3 class="section-title">Trend peso</h3>
    <ul class="list">
      <?php foreach ($mesi as $index => $mese): ?>
        <li><strong><?= h($mese) ?>:</strong> <?= number_format($pesoSerie[$index], 1, ',', '.') ?> kg</li>
      <?php endforeach; ?>
    </ul>
  </article>

  <article class="card span-6">
    <h3 class="section-title">Aderenza piano (%)</h3>
    <ul class="list">
      <?php foreach ($mesi as $index => $mese): ?>
        <li><strong><?= h($mese) ?>:</strong> <?= (int)$aderenzaSerie[$index] ?>%</li>
      <?php endforeach; ?>
    </ul>
  </article>
</section>
<?php
renderEnd();
