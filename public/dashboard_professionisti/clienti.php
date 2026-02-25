<?php
require __DIR__ . '/common.php';

renderStart('Gestione Clienti', 'clienti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <h2 class="section-title">Gestione Clienti (RF-004, RF-013, RF-014)</h2>
  <div class="toolbar">
    <span class="muted">Alla cessazione associazione la chat viene bloccata automaticamente (RF-016). Lo storico resta visibile al cliente in caso cambio professionista (RF-015).</span>
  </div>

  <table>
    <thead><tr><th>Cliente</th><th>Stato associazione</th><th>Data associazione</th><th>Ultimo aggiornamento</th><th>Azioni</th></tr></thead>
    <tbody>
      <?php foreach ($clientiAttivi as $cliente): ?>
        <tr>
          <td><?= h($cliente['nome']) ?></td>
          <td><span class="status ok"><?= h($cliente['stato']) ?></span></td>
          <td><?= h($cliente['associazione']) ?></td>
          <td><?= h($cliente['ultimoUpdate']) ?></td>
          <td><button class="btn">Scheda cliente</button> <button class="btn danger">Termina associazione</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="divider"></div>

  <h3>Storico clienti terminati</h3>
  <table>
    <thead><tr><th>Cliente</th><th>Stato</th><th>Data chiusura</th><th>Nota</th></tr></thead>
    <tbody>
      <?php foreach ($clientiTerminati as $cliente): ?>
        <tr>
          <td><?= h($cliente['nome']) ?></td>
          <td><span class="status warn"><?= h($cliente['stato']) ?></span></td>
          <td><?= h($cliente['chiusura']) ?></td>
          <td><?= h($cliente['nota']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php
renderEnd();
