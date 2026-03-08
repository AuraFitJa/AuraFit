<?php
require __DIR__ . '/common.php';

renderStart('Nutrizione', 'nutrizione', $email, $roleBadge, $isPt, $isNutrizionista);

if (!$isNutrizionista):
?>
  <section class="card">
    <h2 class="section-title">Nutrizione</h2>
    <p class="muted">Questa sezione è disponibile solo per professionisti con ruolo nutrizionista.</p>
  </section>
<?php
  renderEnd($scripts ?? '');
  exit;
endif;

$nutritionStats = [
  'clientiAttivi' => 14,
  'pianiCreati' => 29,
  'pianiAssegnatiAttivi' => 11,
  'calorieMedieRegistrate' => 2110,
  'aderenzaMedia' => 84,
];

$clientiNutrizione = [
  [
    'idCliente' => 101,
    'nome' => 'Giulia Rinaldi',
    'email' => 'giulia.rinaldi@email.it',
    'obiettivo' => 'Ricomp. corporea',
    'pianoAttivo' => 'Cut progressivo v3',
    'pesoKg' => 62.4,
    'ultimoAggiornamento' => '2026-04-03 09:18',
    'stato' => 'attivo',
    'haPiano' => true,
  ],
  [
    'idCliente' => 102,
    'nome' => 'Marco Bassi',
    'email' => 'marco.bassi@email.it',
    'obiettivo' => 'Massa pulita',
    'pianoAttivo' => 'Bulk ordinato v2',
    'pesoKg' => 78.9,
    'ultimoAggiornamento' => '2026-04-02 20:41',
    'stato' => 'attivo',
    'haPiano' => true,
  ],
  [
    'idCliente' => 103,
    'nome' => 'Sara Conti',
    'email' => 'sara.conti@email.it',
    'obiettivo' => 'Dimagrimento graduale',
    'pianoAttivo' => '—',
    'pesoKg' => 70.1,
    'ultimoAggiornamento' => '2026-03-29 17:03',
    'stato' => 'in_attesa',
    'haPiano' => false,
  ],
];

$pianiAlimentari = [
  [
    'idPianoAlim' => 501,
    'cliente' => 101,
    'clienteNome' => 'Giulia Rinaldi',
    'stato' => 'attivo',
    'titolo' => 'Cut progressivo',
    'note' => 'Rotazione carboidrati tra giorni ON/OFF. Idratazione minima 2,2L.',
    'versione' => 3,
    'creatoIl' => '2026-03-10 10:20',
    'aggiornatoIl' => '2026-04-01 08:12',
  ],
  [
    'idPianoAlim' => 502,
    'cliente' => 102,
    'clienteNome' => 'Marco Bassi',
    'stato' => 'bozza',
    'titolo' => 'Bulk ordinato',
    'note' => 'Incremento progressivo +100 kcal ogni 2 settimane in base al peso.',
    'versione' => 2,
    'creatoIl' => '2026-03-15 12:00',
    'aggiornatoIl' => '2026-03-31 19:45',
  ],
  [
    'idPianoAlim' => 503,
    'cliente' => 103,
    'clienteNome' => 'Sara Conti',
    'stato' => 'archiviato',
    'titolo' => 'Reset metabolico',
    'note' => 'Periodo di stabilizzazione pre nuovo blocco ipocalorico.',
    'versione' => 1,
    'creatoIl' => '2026-02-02 09:02',
    'aggiornatoIl' => '2026-03-20 15:35',
  ],
];

$pastiPiano = [
  ['idPastoPiano' => 9001, 'pianoAlim' => 501, 'nomePasto' => 'Colazione', 'ordine' => 1, 'note' => 'Pasto ad alta sazietà'],
  ['idPastoPiano' => 9002, 'pianoAlim' => 501, 'nomePasto' => 'Pranzo', 'ordine' => 2, 'note' => 'Verdure libere a basso amido'],
  ['idPastoPiano' => 9003, 'pianoAlim' => 501, 'nomePasto' => 'Cena', 'ordine' => 3, 'note' => 'Carboidrati ridotti'],
  ['idPastoPiano' => 9011, 'pianoAlim' => 502, 'nomePasto' => 'Colazione', 'ordine' => 1, 'note' => 'Preferire digestione leggera'],
  ['idPastoPiano' => 9012, 'pianoAlim' => 502, 'nomePasto' => 'Post-workout', 'ordine' => 2, 'note' => 'Timing carboidrati veloci'],
];

$alimentiPiano = [
  ['idAlimentoPiano' => 1, 'pastoPiano' => 9001, 'nomeAlimento' => 'Yogurt greco 0%', 'quantita' => 200, 'unita' => 'g', 'proteine' => 20, 'carboidrati' => 8, 'grassi' => 0.8, 'calorie' => 118],
  ['idAlimentoPiano' => 2, 'pastoPiano' => 9001, 'nomeAlimento' => 'Fiocchi d\'avena', 'quantita' => 50, 'unita' => 'g', 'proteine' => 7, 'carboidrati' => 30, 'grassi' => 3.5, 'calorie' => 198],
  ['idAlimentoPiano' => 3, 'pastoPiano' => 9002, 'nomeAlimento' => 'Riso basmati', 'quantita' => 90, 'unita' => 'g', 'proteine' => 7, 'carboidrati' => 70, 'grassi' => 0.7, 'calorie' => 322],
  ['idAlimentoPiano' => 4, 'pastoPiano' => 9002, 'nomeAlimento' => 'Petto di pollo', 'quantita' => 180, 'unita' => 'g', 'proteine' => 41, 'carboidrati' => 0, 'grassi' => 4, 'calorie' => 210],
  ['idAlimentoPiano' => 5, 'pastoPiano' => 9003, 'nomeAlimento' => 'Salmone', 'quantita' => 160, 'unita' => 'g', 'proteine' => 32, 'carboidrati' => 0, 'grassi' => 18, 'calorie' => 286],
  ['idAlimentoPiano' => 6, 'pastoPiano' => 9003, 'nomeAlimento' => 'Zucchine', 'quantita' => 250, 'unita' => 'g', 'proteine' => 3, 'carboidrati' => 7, 'grassi' => 0.5, 'calorie' => 45],
  ['idAlimentoPiano' => 7, 'pastoPiano' => 9011, 'nomeAlimento' => 'Pane integrale', 'quantita' => 90, 'unita' => 'g', 'proteine' => 9, 'carboidrati' => 43, 'grassi' => 1.7, 'calorie' => 228],
  ['idAlimentoPiano' => 8, 'pastoPiano' => 9012, 'nomeAlimento' => 'Banana', 'quantita' => 150, 'unita' => 'g', 'proteine' => 1.6, 'carboidrati' => 34, 'grassi' => 0.5, 'calorie' => 133],
  ['idAlimentoPiano' => 9, 'pastoPiano' => 9012, 'nomeAlimento' => 'Whey isolate', 'quantita' => 30, 'unita' => 'g', 'proteine' => 25, 'carboidrati' => 1, 'grassi' => 0.4, 'calorie' => 110],
];

$planTotals = [];
$pastiByPiano = [];
foreach ($pastiPiano as $pasto) {
  $pastiByPiano[(int)$pasto['pianoAlim']][] = $pasto;
}

$alimentiByPasto = [];
foreach ($alimentiPiano as $alimento) {
  $alimentiByPasto[(int)$alimento['pastoPiano']][] = $alimento;
}

foreach ($pianiAlimentari as $piano) {
  $pid = (int)$piano['idPianoAlim'];
  $planTotals[$pid] = [
    'pasti' => 0,
    'proteine' => 0.0,
    'carboidrati' => 0.0,
    'grassi' => 0.0,
    'calorie' => 0.0,
  ];

  foreach ($pastiByPiano[$pid] ?? [] as $pasto) {
    $planTotals[$pid]['pasti']++;
    foreach ($alimentiByPasto[(int)$pasto['idPastoPiano']] ?? [] as $alimento) {
      $planTotals[$pid]['proteine'] += (float)$alimento['proteine'];
      $planTotals[$pid]['carboidrati'] += (float)$alimento['carboidrati'];
      $planTotals[$pid]['grassi'] += (float)$alimento['grassi'];
      $planTotals[$pid]['calorie'] += (float)$alimento['calorie'];
    }
  }
}

$pianoSelezionatoId = (int)($_GET['piano'] ?? $pianiAlimentari[0]['idPianoAlim']);
$pianoSelezionato = $pianiAlimentari[0];
foreach ($pianiAlimentari as $piano) {
  if ((int)$piano['idPianoAlim'] === $pianoSelezionatoId) {
    $pianoSelezionato = $piano;
    break;
  }
}

$pastiPianoSelezionato = $pastiByPiano[(int)$pianoSelezionato['idPianoAlim']] ?? [];
usort($pastiPianoSelezionato, static function ($a, $b) {
  return (int)$a['ordine'] <=> (int)$b['ordine'];
});

$diarioSummary = [
  'calorieFinali' => 2055,
  'proteineFinali' => 132,
  'carboFinali' => 214,
  'grassiFinali' => 61,
  'deltaTarget' => '-145 kcal',
];

$vociDiario = [
  [
    'consumatoIl' => '2026-04-03 08:05',
    'tipoPasto' => 'Colazione',
    'note' => 'Buona sazietà, nessuna fame anticipata.',
    'calorieFinali' => 370,
    'proteineFinali' => 28,
    'carboFinali' => 40,
    'grassiFinali' => 9,
    'analisiAI' => [
      'calorieStimate' => 355,
      'proteineStimate' => 25,
      'carboStimati' => 38,
      'grassiStimati' => 8,
    ],
  ],
  [
    'consumatoIl' => '2026-04-03 13:12',
    'tipoPasto' => 'Pranzo',
    'note' => 'Sostituito il riso con patate dolci.',
    'calorieFinali' => 680,
    'proteineFinali' => 45,
    'carboFinali' => 72,
    'grassiFinali' => 18,
    'analisiAI' => null,
  ],
  [
    'consumatoIl' => '2026-04-03 20:30',
    'tipoPasto' => 'Cena',
    'note' => 'Aggiunta porzione extra di verdure.',
    'calorieFinali' => 560,
    'proteineFinali' => 37,
    'carboFinali' => 31,
    'grassiFinali' => 19,
    'analisiAI' => [
      'calorieStimate' => 548,
      'proteineStimate' => 34,
      'carboStimati' => 30,
      'grassiStimati' => 18,
    ],
  ],
];

$progressiCliente = [
  'cliente' => 'Giulia Rinaldi',
  'pesoAttuale' => 62.4,
  'variazionePeso' => -1.8,
  'ultimaMisurazione' => '2026-04-02',
  'livelloAttivita' => 'Moderato',
  'compliancePiano' => 87,
  'andamento' => [63.9, 63.5, 63.2, 62.9, 62.6, 62.4],
];

$reportNutrizione = [
  ['periodo' => '01/03/2026 - 31/03/2026', 'generatoIl' => '2026-04-01 07:40', 'riepilogo' => 'Aderenza 85%, riduzione peso costante e miglior controllo pasti serali.'],
  ['periodo' => '01/02/2026 - 28/02/2026', 'generatoIl' => '2026-03-01 07:45', 'riepilogo' => 'Aderenza 81%, migliorata distribuzione proteica nei giorni OFF.'],
];

$emptyClienti = [];
$emptyPiani = [];
$emptyDiario = [];
$emptyReport = [];
?>
<section class="card">
  <style>
    .nutri-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .nutri-actions{display:flex;gap:8px;flex-wrap:wrap}
    .nutri-kpi{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:10px}
    .kpi-card{padding:12px;border:1px solid rgba(255,255,255,.12);border-radius:14px;background:rgba(255,255,255,.02)}
    .kpi-value{display:block;font-size:22px;font-weight:700;color:#fff;margin-top:4px}
    .nutri-section{margin-top:14px}
    .nutri-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:12px}
    .plan-library{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px}
    .plan-card{border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:12px;background:rgba(255,255,255,.02)}
    .plan-card.active{outline:1px solid rgba(56,167,221,.8);background:rgba(56,167,221,.09)}
    .plan-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;font-size:12px;color:rgba(234,240,255,.78)}
    .status-chip{font-size:11px;padding:4px 8px;border-radius:999px;display:inline-block}
    .status-chip.attivo{background:rgba(67,202,145,.2);color:#9af0c9}
    .status-chip.bozza{background:rgba(255,193,75,.2);color:#ffd98e}
    .status-chip.archiviato{background:rgba(255,255,255,.12);color:rgba(234,240,255,.85)}
    .plan-new{display:grid;place-items:center;border:1px dashed rgba(255,255,255,.26);min-height:220px}
    .meal-card{border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:10px;margin-bottom:8px;background:rgba(255,255,255,.02)}
    .meal-head{display:flex;justify-content:space-between;align-items:center;gap:10px}
    .meal-body[hidden]{display:none}
    .macro-grid{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:8px}
    .mini-chart{display:flex;align-items:flex-end;gap:6px;height:100px;margin-top:10px}
    .mini-bar{flex:1;background:linear-gradient(180deg,rgba(93,95,232,.9),rgba(56,167,221,.85));border-radius:8px 8px 4px 4px}
    .modal-mask{position:fixed;inset:0;background:rgba(5,7,14,.8);display:none;place-items:center;padding:18px;z-index:70}
    .modal-mask.show{display:grid}
    .modal-card{width:min(720px,100%);background:#121926;border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:14px}
    .modal-title{margin:0 0 10px;font-size:18px}
    .empty-state{border:1px dashed rgba(255,255,255,.24);border-radius:14px;padding:18px;text-align:center;color:rgba(234,240,255,.74)}
    .table-filters{display:grid;grid-template-columns:1fr 170px 170px;gap:8px;margin-bottom:8px}
    .btn-row{display:flex;gap:6px;flex-wrap:wrap}
    @media (max-width:1100px){.nutri-kpi{grid-template-columns:repeat(2,minmax(0,1fr))}.nutri-grid{grid-template-columns:1fr}.macro-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.table-filters{grid-template-columns:1fr}}
  </style>

  <div class="nutri-head">
    <div>
      <h2 class="section-title" style="margin-bottom:6px">Nutrizione</h2>
      <p class="muted" style="margin:0">Home operativa per gestione piani, diario alimentare, progressi e report clienti.</p>
    </div>
    <div class="nutri-actions">
      <button class="btn primary" type="button" data-open-modal="nuovo-piano">Nuovo piano alimentare</button>
      <button class="btn" type="button" data-open-modal="assegna-piano">Assegna piano</button>
      <button class="btn" type="button">Apri diario cliente</button>
    </div>
  </div>

  <div class="divider"></div>

  <div class="nutri-kpi">
    <div class="kpi-card"><span class="muted">Clienti nutrizione attivi</span><span class="kpi-value"><?= (int)$nutritionStats['clientiAttivi'] ?></span></div>
    <div class="kpi-card"><span class="muted">Piani alimentari creati</span><span class="kpi-value"><?= (int)$nutritionStats['pianiCreati'] ?></span></div>
    <div class="kpi-card"><span class="muted">Piani assegnati attivi</span><span class="kpi-value"><?= (int)$nutritionStats['pianiAssegnatiAttivi'] ?></span></div>
    <div class="kpi-card"><span class="muted">Calorie medie registrate</span><span class="kpi-value"><?= (int)$nutritionStats['calorieMedieRegistrate'] ?> kcal</span></div>
    <div class="kpi-card"><span class="muted">Aderenza media</span><span class="kpi-value"><?= (int)$nutritionStats['aderenzaMedia'] ?>%</span></div>
  </div>
</section>

<section class="card nutri-section">
  <h3 class="section-title">Clienti nutrizione</h3>
  <div class="table-filters">
    <input type="text" class="filter-control" data-client-filter="search" placeholder="Cerca cliente o email..." />
    <select class="filter-control" data-client-filter="status"><option value="">Stato: tutti</option><option value="attivo">Attivo</option><option value="in_attesa">In attesa</option></select>
    <select class="filter-control" data-client-filter="piano"><option value="">Con/senza piano</option><option value="con">Con piano</option><option value="senza">Senza piano</option></select>
  </div>

  <?php if (!$clientiNutrizione): ?>
    <div class="empty-state">Nessun cliente nutrizione associato.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Cliente</th><th>Email</th><th>Obiettivo</th><th>Piano attivo</th><th>Peso attuale</th><th>Ultimo aggiornamento</th><th>Azioni</th></tr></thead>
      <tbody>
        <?php foreach ($clientiNutrizione as $cliente): ?>
          <tr data-cliente-row data-search="<?= h(strtolower($cliente['nome'] . ' ' . $cliente['email'])) ?>" data-status="<?= h($cliente['stato']) ?>" data-ha-piano="<?= $cliente['haPiano'] ? '1' : '0' ?>">
            <td><?= h($cliente['nome']) ?></td>
            <td><?= h($cliente['email']) ?></td>
            <td><?= h($cliente['obiettivo']) ?></td>
            <td><?= h($cliente['pianoAttivo']) ?></td>
            <td><?= h(number_format((float)$cliente['pesoKg'], 1, ',', '.')) ?> kg</td>
            <td><?= h($cliente['ultimoAggiornamento']) ?></td>
            <td>
              <div class="btn-row">
                <button class="btn" type="button" data-open-modal="dettaglio-cliente" data-cliente="<?= h($cliente['nome']) ?>">Apri profilo</button>
                <button class="btn" type="button">Diario</button>
                <button class="btn" type="button" data-open-modal="assegna-piano">Assegna piano</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="divider"></div>

  <h4 style="margin:0 0 10px">Empty state placeholder</h4>
  <?php if (!$emptyClienti): ?>
    <div class="empty-state">Nessun cliente (stato demo placeholder).</div>
  <?php endif; ?>
</section>

<section class="card nutri-section">
  <div class="nutri-grid">
    <div>
      <h3 class="section-title">Piani alimentari</h3>
      <?php if (!$pianiAlimentari): ?>
        <div class="empty-state">Nessun piano alimentare disponibile.</div>
      <?php else: ?>
        <div class="plan-library">
          <?php foreach ($pianiAlimentari as $piano): ?>
            <?php
            $pid = (int)$piano['idPianoAlim'];
            $tot = $planTotals[$pid] ?? ['pasti' => 0, 'proteine' => 0, 'carboidrati' => 0, 'grassi' => 0, 'calorie' => 0];
            $isActivePlan = $pid === (int)$pianoSelezionato['idPianoAlim'];
            ?>
            <article class="plan-card <?= $isActivePlan ? 'active' : '' ?>" data-plan-select="<?= $pid ?>">
              <div class="toolbar" style="margin-bottom:6px">
                <strong><?= h($piano['titolo']) ?></strong>
                <span class="status-chip <?= h($piano['stato']) ?>"><?= h(ucfirst($piano['stato'])) ?></span>
              </div>
              <p class="muted" style="margin:0 0 8px">Cliente: <?= h($piano['clienteNome']) ?> · v<?= (int)$piano['versione'] ?></p>
              <p class="note" style="margin:0 0 8px"><?= h($piano['note']) ?></p>
              <div class="plan-meta">
                <span>Pasti: <?= (int)$tot['pasti'] ?></span><span>kcal: <?= (int)$tot['calorie'] ?></span>
                <span>P: <?= (int)$tot['proteine'] ?> g</span><span>C: <?= (int)$tot['carboidrati'] ?> g</span>
                <span>F: <?= (int)$tot['grassi'] ?> g</span><span>Agg.: <?= h(substr($piano['aggiornatoIl'], 0, 10)) ?></span>
              </div>
              <div class="btn-row" style="margin-top:8px">
                <button type="button" class="btn">Modifica</button>
                <button type="button" class="btn">Duplica</button>
                <button type="button" class="btn" data-open-modal="assegna-piano">Assegna</button>
                <button type="button" class="btn danger">Elimina</button>
              </div>
            </article>
          <?php endforeach; ?>
          <button class="plan-card plan-new" type="button" data-open-modal="nuovo-piano"><span class="create-plus">＋</span><span class="muted">Nuovo piano alimentare</span></button>
        </div>
      <?php endif; ?>

      <?php if (!$emptyPiani): ?>
        <div class="empty-state" style="margin-top:10px">Nessun piano (stato demo placeholder).</div>
      <?php endif; ?>
    </div>

    <div>
      <h3 class="section-title">Preview piano alimentare</h3>
      <div class="plan-card">
        <div class="toolbar" style="margin-bottom:8px">
          <div>
            <strong><?= h($pianoSelezionato['titolo']) ?></strong>
            <p class="muted" style="margin:2px 0 0">Stato <?= h($pianoSelezionato['stato']) ?> · v<?= (int)$pianoSelezionato['versione'] ?> · Cliente <?= h($pianoSelezionato['clienteNome']) ?></p>
          </div>
        </div>
        <p class="note" style="margin:0 0 10px"><?= h($pianoSelezionato['note']) ?></p>

        <?php foreach ($pastiPianoSelezionato as $pasto): ?>
          <?php
          $alimenti = $alimentiByPasto[(int)$pasto['idPastoPiano']] ?? [];
          $mealKcal = 0;
          foreach ($alimenti as $a) {
            $mealKcal += (float)$a['calorie'];
          }
          ?>
          <article class="meal-card" data-meal-card>
            <div class="meal-head">
              <div><strong><?= h($pasto['nomePasto']) ?></strong><p class="muted" style="margin:2px 0 0"><?= h($pasto['note']) ?></p></div>
              <button class="btn" type="button" data-toggle-meal>Totale <?= (int)$mealKcal ?> kcal</button>
            </div>
            <div class="meal-body" hidden>
              <table>
                <thead><tr><th>Alimento</th><th>Quantità</th><th>P</th><th>C</th><th>F</th><th>Kcal</th></tr></thead>
                <tbody>
                <?php foreach ($alimenti as $alimento): ?>
                  <tr>
                    <td><?= h($alimento['nomeAlimento']) ?></td>
                    <td><?= h($alimento['quantita'] . ' ' . $alimento['unita']) ?></td>
                    <td><?= h((string)$alimento['proteine']) ?></td>
                    <td><?= h((string)$alimento['carboidrati']) ?></td>
                    <td><?= h((string)$alimento['grassi']) ?></td>
                    <td><?= h((string)$alimento['calorie']) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>
        <?php endforeach; ?>

        <?php $totPiano = $planTotals[(int)$pianoSelezionato['idPianoAlim']] ?? ['proteine' => 0, 'carboidrati' => 0, 'grassi' => 0, 'calorie' => 0]; ?>
        <div class="macro-grid">
          <div class="kpi-card"><span class="muted">Proteine</span><span class="kpi-value"><?= (int)$totPiano['proteine'] ?> g</span></div>
          <div class="kpi-card"><span class="muted">Carboidrati</span><span class="kpi-value"><?= (int)$totPiano['carboidrati'] ?> g</span></div>
          <div class="kpi-card"><span class="muted">Grassi</span><span class="kpi-value"><?= (int)$totPiano['grassi'] ?> g</span></div>
          <div class="kpi-card"><span class="muted">Calorie</span><span class="kpi-value"><?= (int)$totPiano['calorie'] ?> kcal</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="card nutri-section">
  <h3 class="section-title">Diario alimentare</h3>
  <div class="table-filters">
    <select><option>Cliente: Giulia Rinaldi</option><option>Cliente: Marco Bassi</option></select>
    <input type="date" value="2026-04-01" />
    <input type="date" value="2026-04-03" />
  </div>

  <div class="macro-grid" style="margin-bottom:10px">
    <div class="kpi-card"><span class="muted">Calorie finali</span><span class="kpi-value"><?= (int)$diarioSummary['calorieFinali'] ?></span></div>
    <div class="kpi-card"><span class="muted">Proteine finali</span><span class="kpi-value"><?= (int)$diarioSummary['proteineFinali'] ?> g</span></div>
    <div class="kpi-card"><span class="muted">Carbo finali</span><span class="kpi-value"><?= (int)$diarioSummary['carboFinali'] ?> g</span></div>
    <div class="kpi-card"><span class="muted">Grassi finali</span><span class="kpi-value"><?= (int)$diarioSummary['grassiFinali'] ?> g</span></div>
    <div class="kpi-card"><span class="muted">Differenza target</span><span class="kpi-value"><?= h($diarioSummary['deltaTarget']) ?></span></div>
  </div>

  <?php if (!$vociDiario): ?>
    <div class="empty-state">Nessuna voce diario disponibile.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Consumato il</th><th>Tipo pasto</th><th>Note</th><th>kcal</th><th>P</th><th>C</th><th>F</th><th>Analisi AI</th><th>Azioni</th></tr></thead>
      <tbody>
      <?php foreach ($vociDiario as $voce): ?>
        <tr>
          <td><?= h($voce['consumatoIl']) ?></td>
          <td><?= h($voce['tipoPasto']) ?></td>
          <td><?= h($voce['note']) ?></td>
          <td><?= (int)$voce['calorieFinali'] ?></td>
          <td><?= (int)$voce['proteineFinali'] ?></td>
          <td><?= (int)$voce['carboFinali'] ?></td>
          <td><?= (int)$voce['grassiFinali'] ?></td>
          <td>
            <?php if ($voce['analisiAI']): ?>
              <span class="status ok">Analizzata</span>
              <div class="note" style="margin-top:4px">AI <?= (int)$voce['analisiAI']['calorieStimate'] ?> kcal · P <?= (int)$voce['analisiAI']['proteineStimate'] ?> · C <?= (int)$voce['analisiAI']['carboStimati'] ?> · F <?= (int)$voce['analisiAI']['grassiStimati'] ?></div>
            <?php else: ?>
              <span class="status warn">Non disponibile</span>
            <?php endif; ?>
          </td>
          <td><button class="btn" type="button">Apri dettaglio diario</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (!$emptyDiario): ?>
    <div class="empty-state" style="margin-top:10px">Nessuna voce diario (stato demo placeholder).</div>
  <?php endif; ?>
</section>

<section class="card nutri-section">
  <h3 class="section-title">Progressi nutrizione</h3>
  <div class="macro-grid">
    <div class="kpi-card"><span class="muted">Peso attuale</span><span class="kpi-value"><?= h(number_format((float)$progressiCliente['pesoAttuale'], 1, ',', '.')) ?> kg</span></div>
    <div class="kpi-card"><span class="muted">Variazione peso</span><span class="kpi-value"><?= h((string)$progressiCliente['variazionePeso']) ?> kg</span></div>
    <div class="kpi-card"><span class="muted">Ultima misurazione</span><span class="kpi-value"><?= h($progressiCliente['ultimaMisurazione']) ?></span></div>
    <div class="kpi-card"><span class="muted">Livello attività</span><span class="kpi-value"><?= h($progressiCliente['livelloAttivita']) ?></span></div>
    <div class="kpi-card"><span class="muted">Compliance piano</span><span class="kpi-value"><?= (int)$progressiCliente['compliancePiano'] ?>%</span></div>
  </div>
  <div class="plan-card" style="margin-top:10px">
    <strong>Andamento semplificato peso (ultime 6 rilevazioni)</strong>
    <div class="mini-chart">
      <?php foreach ($progressiCliente['andamento'] as $val): ?>
        <span class="mini-bar" style="height:<?= (int)max(25, ($val - 60) * 10) ?>px" title="<?= h((string)$val) ?> kg"></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="card nutri-section">
  <div class="toolbar">
    <h3 class="section-title" style="margin:0">Report</h3>
    <div class="btn-row"><button class="btn primary" type="button">Genera report</button><button class="btn" type="button">Visualizza storico</button></div>
  </div>

  <?php if (!$reportNutrizione): ?>
    <div class="empty-state">Nessun report disponibile.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Periodo</th><th>Generato il</th><th>Riepilogo</th><th>Azioni</th></tr></thead>
      <tbody>
      <?php foreach ($reportNutrizione as $report): ?>
        <tr>
          <td><?= h($report['periodo']) ?></td>
          <td><?= h($report['generatoIl']) ?></td>
          <td><?= h($report['riepilogo']) ?></td>
          <td><div class="btn-row"><button class="btn" type="button">Apri</button><button class="btn" type="button">Esporta</button></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (!$emptyReport): ?>
    <div class="empty-state" style="margin-top:10px">Nessun report (stato demo placeholder).</div>
  <?php endif; ?>
</section>

<div class="modal-mask" data-modal="nuovo-piano" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true">
    <div class="toolbar" style="margin-bottom:8px"><h4 class="modal-title">Nuovo piano alimentare</h4><button class="btn" type="button" data-close-modal>Chiudi</button></div>
    <form>
      <div class="two">
        <div class="field"><label>Cliente</label><select><option>Giulia Rinaldi</option><option>Marco Bassi</option><option>Sara Conti</option></select></div>
        <div class="field"><label>Titolo</label><input type="text" value="Nuovo piano aprile" /></div>
        <div class="field"><label>Stato</label><select><option>bozza</option><option>attivo</option><option>archiviato</option></select></div>
        <div class="field"><label>Versione iniziale</label><input type="number" min="1" value="1" /></div>
        <div class="field" style="grid-column:1/-1"><label>Note</label><textarea rows="4">Note operative del piano alimentare.</textarea></div>
      </div>
      <div class="toolbar" style="margin-top:10px"><span class="note">Target macro visuali lato UI: P 130g · C 220g · F 60g</span><button class="btn primary" type="button">Crea piano</button></div>
    </form>
  </div>
</div>

<div class="modal-mask" data-modal="assegna-piano" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true">
    <div class="toolbar" style="margin-bottom:8px"><h4 class="modal-title">Assegna piano alimentare</h4><button class="btn" type="button" data-close-modal>Chiudi</button></div>
    <form>
      <div class="two">
        <div class="field"><label>Piano alimentare (pianoAlim)</label><select><option>Cut progressivo v3</option><option>Bulk ordinato v2</option></select></div>
        <div class="field"><label>Cliente</label><select><option>Giulia Rinaldi</option><option>Marco Bassi</option></select></div>
        <div class="field"><label>Assegnato il (assegnatoIl)</label><input type="datetime-local" value="2026-04-03T10:30" /></div>
        <div class="field"><label>Stato</label><select><option>attiva</option><option>sospesa</option><option>terminata</option></select></div>
      </div>
      <div class="toolbar" style="margin-top:10px"><button class="btn primary" type="button">Conferma assegnazione</button></div>
    </form>
  </div>
</div>

<div class="modal-mask" data-modal="dettaglio-cliente" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true">
    <div class="toolbar" style="margin-bottom:8px"><h4 class="modal-title">Dettaglio cliente rapido</h4><button class="btn" type="button" data-close-modal>Chiudi</button></div>
    <p class="muted" style="margin:0 0 6px">Cliente selezionato: <strong data-quick-client-name>—</strong></p>
    <div class="macro-grid">
      <div class="kpi-card"><span class="muted">Peso attuale</span><span class="kpi-value">62,4 kg</span></div>
      <div class="kpi-card"><span class="muted">Piano attivo</span><span class="kpi-value" style="font-size:15px">Cut progressivo v3</span></div>
      <div class="kpi-card"><span class="muted">Ultimo diario</span><span class="kpi-value" style="font-size:15px">03/04/2026</span></div>
    </div>
  </div>
</div>

<script>
  document.querySelectorAll('[data-open-modal]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const modal = document.querySelector('[data-modal="' + trigger.getAttribute('data-open-modal') + '"]');
      if (modal) {
        if (trigger.dataset.cliente) {
          const target = modal.querySelector('[data-quick-client-name]');
          if (target) target.textContent = trigger.dataset.cliente;
        }
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
      }
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('[data-modal]');
      if (modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
      }
    });
  });

  document.querySelectorAll('[data-modal]').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
      }
    });
  });

  document.querySelectorAll('[data-toggle-meal]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const body = btn.closest('[data-meal-card]')?.querySelector('.meal-body');
      if (body) body.hidden = !body.hidden;
    });
  });

  const searchInput = document.querySelector('[data-client-filter="search"]');
  const statusSelect = document.querySelector('[data-client-filter="status"]');
  const pianoSelect = document.querySelector('[data-client-filter="piano"]');
  const clientRows = document.querySelectorAll('[data-cliente-row]');

  const applyClientFilters = () => {
    const search = (searchInput?.value || '').trim().toLowerCase();
    const status = statusSelect?.value || '';
    const piano = pianoSelect?.value || '';

    clientRows.forEach((row) => {
      const matchesSearch = !search || row.dataset.search.includes(search);
      const matchesStatus = !status || row.dataset.status === status;
      const hasPiano = row.dataset.haPiano === '1';
      const matchesPiano = !piano || (piano === 'con' ? hasPiano : !hasPiano);
      row.style.display = matchesSearch && matchesStatus && matchesPiano ? '' : 'none';
    });
  };

  [searchInput, statusSelect, pianoSelect].forEach((el) => {
    el?.addEventListener('input', applyClientFilters);
    el?.addEventListener('change', applyClientFilters);
  });

  document.querySelectorAll('[data-plan-select]').forEach((card) => {
    card.addEventListener('click', () => {
      const id = card.getAttribute('data-plan-select');
      if (!id) return;
      const url = new URL(window.location.href);
      url.searchParams.set('piano', id);
      window.location.href = url.toString();
    });
  });
</script>
<?php
renderEnd($scripts ?? '');
