<?php
require __DIR__ . '/common.php';

renderStart('Nutrizione cliente', 'nutrizione', $email);
?>
<section class="card hero">
  <span class="pill">Nutrizione</span>
  <h1>Piano alimentare giornaliero</h1>
  <p class="lead">Controlla i pasti della giornata e mantieni alta l'aderenza al tuo piano.</p>
</section>

<section class="grid">
  <article class="card span-8">
    <h3 class="section-title">Pasti di oggi</h3>
    <table>
      <thead><tr><th>Fascia</th><th>Pasto</th><th>Kcal</th></tr></thead>
      <tbody>
      <?php $totale = 0; foreach ($pastiOggi as $pasto): $totale += (int)$pasto['calorie']; ?>
        <tr>
          <td><?= h($pasto['fascia']) ?></td>
          <td><?= h($pasto['voce']) ?></td>
          <td><?= (int)$pasto['calorie'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="note">Totale giornaliero registrato: <?= $totale ?> kcal.</p>
  </article>

  <article class="card span-4">
    <h3 class="section-title">Focus del giorno</h3>
    <ul class="list">
      <li>Proteine distribuite su 3-4 pasti.</li>
      <li>Verdure ad ogni pasto principale.</li>
      <li>Acqua: obiettivo 2,2 litri.</li>
    </ul>
    <p class="note">Nutrizionista di riferimento: <?= h($nutrizionistaAssegnato) ?></p>
  </article>
</section>
<?php
renderEnd();
