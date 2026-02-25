<?php
require __DIR__ . '/common.php';

renderStart('Gestione ID-Key', 'idkey', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <h2 class="section-title">Gestione ID-Key (RF-020, RF-021, RF-018)</h2>
  <div class="toolbar">
    <?php if ($canGenerateIdKey): ?>
      <button class="btn primary">Genera nuova ID-Key</button>
      <span class="muted">Verifica limite piano OK: puoi generare nuove chiavi.</span>
    <?php else: ?>
      <button class="btn primary" disabled>Genera nuova ID-Key</button>
      <span class="muted">Blocco generazione: limite clienti raggiunto (RF-018).</span>
    <?php endif; ?>
  </div>

  <table>
    <thead><tr><th>ID-Key</th><th>Stato</th><th>Creata il</th><th>Azioni</th></tr></thead>
    <tbody>
      <?php foreach ($idKeys as $key): ?>
        <?php $statusClass = $key['stato'] === 'Attiva' ? 'ok' : ($key['stato'] === 'Sospesa' ? 'warn' : 'danger'); ?>
        <tr>
          <td><code><?= h($key['key']) ?></code></td>
          <td><span class="status <?= $statusClass ?>"><?= h($key['stato']) ?></span></td>
          <td><?= h($key['creata']) ?></td>
          <td><button class="btn warn">Sospendi</button> <button class="btn">Riattiva</button> <button class="btn danger">Elimina</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php
renderEnd();
