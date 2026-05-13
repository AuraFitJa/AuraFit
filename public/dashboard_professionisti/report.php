<?php
require __DIR__ . '/common.php';

$clientiPeso = [];
$clientiError = null;
$misurazioniTableExists = false;

if ($dbAvailable) {
  try {
    $table = Database::exec("SHOW TABLES LIKE 'MisurazioniPeso'")->fetch();
    $misurazioniTableExists = (bool)$table;

    $professionista = getProfessionistaId($userId);
    if ($professionista) {
      $rowsClienti = Database::exec(
        'SELECT c.idCliente, u.nome, u.cognome
         FROM Associazioni a
         INNER JOIN Clienti c ON c.idCliente = a.cliente
         INNER JOIN Utenti u ON u.idUtente = c.idUtente
         WHERE a.professionista = ? AND a.attiva = 1
         ORDER BY u.cognome, u.nome',
        [$professionista]
      )->fetchAll();

      foreach ($rowsClienti as $rowCliente) {
        $idCliente = (int)$rowCliente['idCliente'];
        $nomeCompleto = trim((string)$rowCliente['nome'] . ' ' . (string)$rowCliente['cognome']);
        $clientiPeso[$idCliente] = ['nome' => $nomeCompleto, 'labels' => [], 'data' => []];
      }

      if ($misurazioniTableExists && $clientiPeso) {
        $rowsPeso = Database::exec(
          'SELECT idCliente, dataMisurazione, pesoKg
           FROM MisurazioniPeso
           WHERE idCliente IN (
             SELECT c.idCliente
             FROM Associazioni a
             INNER JOIN Clienti c ON c.idCliente = a.cliente
             WHERE a.professionista = ? AND a.attiva = 1
           )
           ORDER BY dataMisurazione',
          [$professionista]
        )->fetchAll();

        foreach ($rowsPeso as $rowPeso) {
          $idCliente = (int)$rowPeso['idCliente'];
          if (!isset($clientiPeso[$idCliente])) {
            continue;
          }
          $clientiPeso[$idCliente]['labels'][] = (string)$rowPeso['dataMisurazione'];
          $clientiPeso[$idCliente]['data'][] = (float)$rowPeso['pesoKg'];
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
  <?php elseif (!$misurazioniTableExists): ?>
    <p class="muted">Nessuna misurazione peso disponibile: la tabella MisurazioniPeso non è ancora presente nel database.</p>
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
