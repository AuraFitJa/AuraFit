<?php
require __DIR__ . '/common.php';

renderStart('Accessi incrociati', 'accessi', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <h2 class="section-title">Accessi Incrociati (RF-011, RF-012)</h2>

  <?php if ($isPt): ?>
    <p class="muted">Ruolo PT: visualizzazione dati nutrizionali cliente in sola lettura.</p>
    <table>
      <thead><tr><th>Cliente</th><th>Dati nutrizionali visibili</th><th>Permessi</th></tr></thead>
      <tbody><tr><td>Silvia Martini</td><td>Kcal, macro, compliance piano</td><td><span class="status warn">Read Only</span></td></tr></tbody>
    </table>
  <?php endif; ?>

  <?php if ($isNutrizionista): ?>
    <div class="divider"></div>
    <p class="muted">Ruolo Nutrizionista: visualizzazione programmi allenamento in sola lettura.</p>
    <table>
      <thead><tr><th>Cliente</th><th>Scheda allenamento visibile</th><th>Permessi</th></tr></thead>
      <tbody><tr><td>Marco Testa</td><td>Split Upper/Lower + storico progressioni</td><td><span class="status warn">Read Only</span></td></tr></tbody>
    </table>
  <?php endif; ?>
</section>
<?php
renderEnd();
