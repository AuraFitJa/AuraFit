<?php
require __DIR__ . '/common.php';

$clientiPeso = [];
$clientiError = null;

if ($dbAvailable) {
  try {
    Database::exec(
      'CREATE TABLE IF NOT EXISTS MisurazioniPeso (
        idMisurazione BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        idCliente BIGINT UNSIGNED NOT NULL,
        pesoKg DECIMAL(5,2) NOT NULL,
        dataMisurazione DATE NOT NULL,
        creatoIl TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        aggiornatoIl TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_cliente_data (idCliente, dataMisurazione),
        KEY idx_cliente_data (idCliente, dataMisurazione),
        CONSTRAINT fk_misurazionipeso_cliente FOREIGN KEY (idCliente) REFERENCES Clienti(idCliente) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $professionista = getProfessionistaId($userId);
    if ($professionista) {
      $rows = Database::exec(
        'SELECT c.idCliente, u.nome, u.cognome, mp.dataMisurazione, mp.pesoKg
         FROM Associazioni a
         INNER JOIN Clienti c ON c.idCliente = a.cliente
         INNER JOIN Utenti u ON u.idUtente = c.idUtente
         LEFT JOIN MisurazioniPeso mp ON mp.idCliente = c.idCliente
         WHERE a.professionista = ? AND a.attiva = 1
         ORDER BY u.cognome, u.nome, mp.dataMisurazione',
        [$professionista]
      )->fetchAll();

      foreach ($rows as $row) {
        $idCliente = (int)$row['idCliente'];
        if (!isset($clientiPeso[$idCliente])) {
          $nomeCompleto = trim((string)$row['nome'] . ' ' . (string)$row['cognome']);
          $clientiPeso[$idCliente] = ['nome' => $nomeCompleto, 'labels' => [], 'data' => []];
        }
        if (!empty($row['dataMisurazione']) && $row['pesoKg'] !== null) {
          $clientiPeso[$idCliente]['labels'][] = (string)$row['dataMisurazione'];
          $clientiPeso[$idCliente]['data'][] = (float)$row['pesoKg'];
        }
      }
    }
  } catch (Throwable $e) {
    $clientiError = 'Errore nel caricamento dei pesi cliente.';
  }
}

renderStart('Monitoraggio e Report', 'report', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <h2 class="section-title">Monitoraggio peso clienti</h2>
  <p class="muted">Visualizza solo dati reali inseriti dai clienti nella dashboard desktop.</p>

  <?php if (!$dbAvailable): ?>
    <div class="alert"><?= h($dbError ?? 'Database non disponibile.') ?></div>
  <?php elseif ($clientiError): ?>
    <div class="alert"><?= h($clientiError) ?></div>
  <?php elseif (!$clientiPeso): ?>
    <p class="muted">Nessun cliente associato trovato.</p>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($clientiPeso as $idCliente => $item): ?>
        <article class="card span-6 chart-wrap" style="height:320px">
          <h3><?= h($item['nome']) ?></h3>
          <?php if (!$item['data']): ?>
            <p class="muted">Nessuna misurazione peso disponibile.</p>
          <?php else: ?>
            <canvas id="pesoChartCliente<?= (int)$idCliente ?>" aria-label="Grafico peso cliente" role="img"></canvas>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php
$chartsPayload = [];
foreach ($clientiPeso as $idCliente => $item) {
  if (!$item['data']) {
    continue;
  }
  $chartsPayload[] = [
    'id' => 'pesoChartCliente' . (int)$idCliente,
    'label' => $item['nome'],
    'labels' => $item['labels'],
    'data' => $item['data'],
  ];
}

$scripts = '';
if ($chartsPayload) {
  $scripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>' .
    'const chartsPayload=' . json_encode($chartsPayload, JSON_UNESCAPED_UNICODE) . ';' .
    'const axisColor="rgba(234,240,255,.55)";const gridColor="rgba(234,240,255,.12)";' .
    'const baseOptions={responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:axisColor}}},scales:{x:{ticks:{color:axisColor},grid:{color:gridColor}},y:{ticks:{color:axisColor},grid:{color:gridColor}}}};' .
    'chartsPayload.forEach((item)=>{const el=document.getElementById(item.id);if(!el){return;}new Chart(el,{type:"line",data:{labels:item.labels,datasets:[{label:"Peso (kg)",data:item.data,borderColor:"#4CC9F0",backgroundColor:"rgba(76,201,240,.2)",fill:true,tension:.3}]},options:baseOptions});});' .
    '</script>';
}

renderEnd($scripts);
