<?php
require __DIR__ . '/common.php';

renderStart('Monitoraggio e Report', 'report', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <h2 class="section-title">Monitoraggio e Report (RF-010)</h2>

  <div class="toolbar">
    <div class="two" style="min-width:380px">
      <div class="field"><label>Filtro periodo</label><select><option>Ultimi 30 giorni</option><option>Ultimi 3 mesi</option><option>Anno corrente</option></select></div>
      <div class="field"><label>Cliente</label><select><option>Tutti i clienti</option><option>Giulia Rinaldi</option><option>Silvia Martini</option></select></div>
    </div>
    <button class="btn primary">Genera report mensile automatico</button>
  </div>

  <div class="grid">
    <article class="card span-6 chart-wrap"><h3>Andamento peso</h3><canvas id="pesoChart" aria-label="Grafico peso" role="img"></canvas></article>
    <article class="card span-6 chart-wrap"><h3>Andamento performance</h3><canvas id="performanceChart" aria-label="Grafico performance" role="img"></canvas></article>
  </div>

  <div class="divider"></div>

  <h3>Report mensili automatici</h3>
  <table>
    <thead><tr><th>Mese</th><th>Stato elaborazione</th><th>Download</th></tr></thead>
    <tbody>
      <?php foreach ($reportMensili as $report): ?>
        <tr>
          <td><?= h($report['mese']) ?></td>
          <td><?= h($report['stato']) ?></td>
          <td><button class="btn">Download PDF</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php
$scripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>' .
  'const labels=' . json_encode($mesi, JSON_UNESCAPED_UNICODE) . ';' .
  'const pesoData=' . json_encode($pesoSerie, JSON_UNESCAPED_UNICODE) . ';' .
  'const performanceData=' . json_encode($performanceSerie, JSON_UNESCAPED_UNICODE) . ';' .
  'const axisColor="rgba(234,240,255,.55)";const gridColor="rgba(234,240,255,.12)";' .
  'const baseOptions={responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:axisColor}}},scales:{x:{ticks:{color:axisColor},grid:{color:gridColor}},y:{ticks:{color:axisColor},grid:{color:gridColor}}}};' .
  'new Chart(document.getElementById("pesoChart"),{type:"bar",data:{labels,datasets:[{label:"Peso (kg)",data:pesoData,backgroundColor:"rgba(76,201,240,.65)",borderRadius:8}]},options:baseOptions});' .
  'new Chart(document.getElementById("performanceChart"),{type:"line",data:{labels,datasets:[{label:"Performance",data:performanceData,borderColor:"#6D5EF3",backgroundColor:"rgba(109,94,243,.2)",fill:true,tension:.35}]},options:baseOptions});' .
  '</script>';

renderEnd($scripts);
