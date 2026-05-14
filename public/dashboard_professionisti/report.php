<?php
require __DIR__ . '/common.php';

$clientiPeso = [];
$clientiError = null;

function report_format_date(?string $value, string $format = 'd/m/Y'): string {
  if (!$value) {
    return '—';
  }

  $timestamp = strtotime($value);
  if (!$timestamp) {
    return '—';
  }

  return date($format, $timestamp);
}

function report_num($value, int $decimals = 1): string {
  if ($value === null || $value === '') {
    return '—';
  }

  return number_format((float)$value, $decimals, ',', '.');
}

if ($dbAvailable) {
  try {
    $professionista = getProfessionistaId($userId);

    if ($professionista) {
      $rowsClienti = Database::exec(
        "SELECT c.idCliente, u.nome, u.cognome
         FROM Associazioni a
         INNER JOIN Clienti c ON c.idCliente = a.cliente
         INNER JOIN Utenti u ON u.idUtente = c.idUtente
         WHERE a.professionista = ?
           AND a.attivaFlag = 1
           AND (a.stato = 'attiva' OR a.stato = 'attivo')
         ORDER BY u.cognome, u.nome",
        [$professionista]
      )->fetchAll();

      foreach ($rowsClienti as $rowCliente) {
        $idCliente = (int)$rowCliente['idCliente'];
        $nomeCompleto = trim((string)$rowCliente['nome'] . ' ' . (string)$rowCliente['cognome']);

        $clientiPeso[$idCliente] = [
          'nome' => $nomeCompleto !== '' ? $nomeCompleto : 'Cliente',
          'labels' => [],
          'tooltipLabels' => [],
          'data' => [],
          'dateRaw' => [],
          'latest' => null,
          'previous' => null,
          'min' => null,
          'max' => null,
          'delta' => null,
          'deltaPercent' => null,
          'firstDate' => null,
          'lastDate' => null,
        ];
      }

      if ($clientiPeso) {
        $rowsPeso = Database::exec(
          "SELECT m.cliente AS idCliente, m.misurataIl, m.valore
           FROM Misurazioni m
           WHERE LOWER(m.tipoMisura) = 'peso'
             AND m.cliente IN (
               SELECT c.idCliente
               FROM Associazioni a
               INNER JOIN Clienti c ON c.idCliente = a.cliente
               WHERE a.professionista = ?
                 AND a.attivaFlag = 1
                 AND (a.stato = 'attiva' OR a.stato = 'attivo')
             )
           ORDER BY m.misurataIl ASC",
          [$professionista]
        )->fetchAll();

        foreach ($rowsPeso as $rowPeso) {
          $idCliente = (int)$rowPeso['idCliente'];

          if (!isset($clientiPeso[$idCliente])) {
            continue;
          }

          $dateValue = (string)$rowPeso['misurataIl'];
          $weightValue = (float)$rowPeso['valore'];

          $clientiPeso[$idCliente]['labels'][] = report_format_date($dateValue, 'd/m');
          $clientiPeso[$idCliente]['tooltipLabels'][] = report_format_date($dateValue, 'd/m/Y H:i');
          $clientiPeso[$idCliente]['dateRaw'][] = $dateValue;
          $clientiPeso[$idCliente]['data'][] = $weightValue;
        }

        foreach ($clientiPeso as $idCliente => $item) {
          $values = $item['data'];
          $count = count($values);

          if ($count === 0) {
            continue;
          }

          $latest = $values[$count - 1];
          $previous = $count >= 2 ? $values[$count - 2] : null;
          $delta = $previous !== null ? $latest - $previous : null;
          $deltaPercent = ($previous !== null && $previous > 0) ? (($latest - $previous) / $previous) * 100 : null;

          $clientiPeso[$idCliente]['latest'] = $latest;
          $clientiPeso[$idCliente]['previous'] = $previous;
          $clientiPeso[$idCliente]['min'] = min($values);
          $clientiPeso[$idCliente]['max'] = max($values);
          $clientiPeso[$idCliente]['delta'] = $delta;
          $clientiPeso[$idCliente]['deltaPercent'] = $deltaPercent;
          $clientiPeso[$idCliente]['firstDate'] = $item['dateRaw'][0] ?? null;
          $clientiPeso[$idCliente]['lastDate'] = $item['dateRaw'][$count - 1] ?? null;
        }
      }
    }
  } catch (Throwable $e) {
    $clientiError = 'Errore nel caricamento dei pesi cliente.';
  }
}

renderStart('Monitoraggio e Report', 'report', $email, $roleBadge, $isPt, $isNutrizionista);
?>

<style>
  .report-page {
    display: grid;
    gap: 16px;
  }

  .report-hero {
    position: relative;
    overflow: hidden;
    padding: clamp(20px, 4vw, 34px);
    border-radius: 28px;
    background:
      radial-gradient(circle at 18% 10%, rgba(76, 201, 240, .28), transparent 28rem),
      radial-gradient(circle at 86% 8%, rgba(46, 225, 165, .16), transparent 24rem),
      linear-gradient(145deg, rgba(255,255,255,.09), rgba(255,255,255,.035));
    border: 1px solid rgba(255,255,255,.1);
    box-shadow: 0 18px 50px rgba(0,0,0,.28);
  }

  .report-hero::after {
    content: "";
    position: absolute;
    right: -120px;
    bottom: -150px;
    width: 340px;
    height: 340px;
    border-radius: 999px;
    background: linear-gradient(135deg, rgba(109,94,243,.22), rgba(76,201,240,.18));
    filter: blur(18px);
    pointer-events: none;
  }

  .report-hero-content {
    position: relative;
    z-index: 1;
    max-width: 760px;
  }

  .report-eyebrow {
    width: fit-content;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    padding: 8px 11px;
    border-radius: 999px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    color: rgba(234,240,255,.82);
    font-size: 12px;
    font-weight: 800;
  }

  .report-eyebrow::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #4CC9F0;
    box-shadow: 0 0 0 6px rgba(76,201,240,.13);
  }

  .report-hero h1 {
    margin: 0;
    max-width: 12ch;
    font-size: clamp(34px, 5vw, 58px);
    line-height: .92;
    letter-spacing: -.06em;
  }

  .report-hero p {
    max-width: 62ch;
    margin: 16px 0 0;
    color: rgba(234,240,255,.72);
    font-size: 15px;
    line-height: 1.65;
  }

  .report-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 14px;
  }

  .weight-card {
    grid-column: span 12;
    position: relative;
    overflow: hidden;
    padding: 18px;
    border-radius: 26px;
    background:
      linear-gradient(180deg, rgba(255,255,255,.075), rgba(255,255,255,.035));
    border: 1px solid rgba(255,255,255,.09);
    box-shadow: 0 16px 44px rgba(0,0,0,.28), inset 0 0 0 1px rgba(255,255,255,.025);
  }

  .weight-card-header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 16px;
    align-items: start;
    margin-bottom: 18px;
  }

  .client-title {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
  }

  .client-avatar {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(76,201,240,.95), rgba(46,225,165,.82));
    color: #061018;
    font-weight: 900;
    box-shadow: 0 14px 30px rgba(76,201,240,.18);
  }

  .client-title h3 {
    margin: 0;
    font-size: 22px;
    line-height: 1.08;
    letter-spacing: -.035em;
  }

  .client-title p {
    margin: 5px 0 0;
    color: rgba(234,240,255,.62);
    font-size: 13px;
  }

  .weight-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 16px;
  }

  .weight-stat {
    padding: 13px;
    border-radius: 18px;
    background: rgba(255,255,255,.045);
    border: 1px solid rgba(255,255,255,.075);
  }

  .weight-stat span {
    display: block;
    margin-bottom: 7px;
    color: rgba(234,240,255,.58);
    font-size: 12px;
    font-weight: 700;
  }

  .weight-stat strong {
    display: block;
    color: #fff;
    font-size: 22px;
    line-height: 1;
    letter-spacing: -.04em;
  }

  .trend-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    width: fit-content;
    padding: 8px 11px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,.12);
    background: rgba(255,255,255,.055);
    color: rgba(234,240,255,.76);
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
  }

  .trend-pill.down {
    color: #C7FFE8;
    background: rgba(46,225,165,.12);
    border-color: rgba(46,225,165,.28);
  }

  .trend-pill.up {
    color: #FFE7AE;
    background: rgba(255,209,102,.12);
    border-color: rgba(255,209,102,.28);
  }

  .chart-panel {
    position: relative;
    height: clamp(280px, 42vw, 430px);
    padding: 14px 14px 8px;
    border-radius: 22px;
    background:
      radial-gradient(circle at 18% 0%, rgba(76,201,240,.12), transparent 16rem),
      rgba(4, 8, 16, .24);
    border: 1px solid rgba(255,255,255,.075);
  }

  .chart-panel canvas {
    width: 100% !important;
    height: 100% !important;
  }

  .empty-weight {
    padding: 18px;
    border-radius: 22px;
    background: rgba(255,255,255,.04);
    border: 1px dashed rgba(255,255,255,.14);
    color: rgba(234,240,255,.68);
    line-height: 1.55;
  }

  .report-alert {
    padding: 16px;
    border-radius: 20px;
    background: rgba(255,111,137,.12);
    border: 1px solid rgba(255,111,137,.34);
    color: #FFDCE4;
  }

  @media (min-width: 1180px) {
    .weight-card.compact-card {
      grid-column: span 6;
    }

    .weight-card.compact-card .chart-panel {
      height: 340px;
    }

    .weight-card.compact-card .weight-stats {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 820px) {
    .report-page {
      gap: 12px;
    }

    .report-hero {
      padding: 18px;
      border-radius: 24px;
    }

    .report-hero h1 {
      max-width: 10ch;
      font-size: clamp(32px, 11vw, 46px);
    }

    .report-hero p {
      font-size: 14px;
      line-height: 1.55;
    }

    .report-grid {
      gap: 10px;
    }

    .weight-card {
      padding: 14px;
      border-radius: 22px;
    }

    .weight-card-header {
      grid-template-columns: 1fr;
      gap: 12px;
      margin-bottom: 14px;
    }

    .client-avatar {
      width: 40px;
      height: 40px;
      border-radius: 14px;
    }

    .client-title h3 {
      font-size: 19px;
    }

    .weight-stats {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
      margin-bottom: 12px;
    }

    .weight-stat {
      padding: 11px;
      border-radius: 16px;
    }

    .weight-stat strong {
      font-size: 19px;
    }

    .chart-panel {
      height: 330px;
      padding: 10px 6px 6px;
      border-radius: 18px;
      margin-left: -2px;
      margin-right: -2px;
    }

    .trend-pill {
      white-space: normal;
    }
  }

  @media (max-width: 420px) {
    .weight-stats {
      grid-template-columns: 1fr;
    }

    .chart-panel {
      height: 300px;
    }
  }
</style>

<div class="report-page">
  <section class="report-hero">
    <div class="report-hero-content">
      <div class="report-eyebrow">Monitoraggio clienti</div>
      <h1>Report peso più leggibili.</h1>
      <p>
        Visualizza l’andamento del peso dei clienti associati usando solo misurazioni reali
        registrate nel database. Il grafico si adatta a desktop e mobile.
      </p>
    </div>
  </section>

  <?php if (!$dbAvailable): ?>
    <section class="report-alert"><?= h($dbError ?? 'Database non disponibile.') ?></section>
  <?php elseif ($clientiError): ?>
    <section class="report-alert"><?= h($clientiError) ?></section>
  <?php elseif (!$clientiPeso): ?>
    <section class="card">
      <h2 class="section-title">Monitoraggio peso clienti</h2>
      <p class="muted">Nessun cliente associato trovato.</p>
    </section>
  <?php else: ?>
    <section class="report-grid" aria-label="Grafici peso clienti">
      <?php foreach ($clientiPeso as $idCliente => $item): ?>
        <?php
          $count = count($item['data']);
          $delta = $item['delta'];
          $trendClass = '';
          $trendLabel = 'Una sola misurazione';

          if ($delta !== null) {
            if ($delta < 0) {
              $trendClass = 'down';
              $trendLabel = '↓ ' . report_num(abs($delta), 1) . ' kg dall’ultima rilevazione';
            } elseif ($delta > 0) {
              $trendClass = 'up';
              $trendLabel = '↑ ' . report_num($delta, 1) . ' kg dall’ultima rilevazione';
            } else {
              $trendLabel = 'Stabile dall’ultima rilevazione';
            }
          }

          $initial = mb_strtoupper(mb_substr((string)$item['nome'], 0, 1, 'UTF-8'), 'UTF-8');
          $cardClass = $count > 0 && count($clientiPeso) > 1 ? 'compact-card' : '';
        ?>

        <article class="weight-card <?= h($cardClass) ?>">
          <header class="weight-card-header">
            <div class="client-title">
              <div class="client-avatar" aria-hidden="true"><?= h($initial) ?></div>
              <div>
                <h3><?= h($item['nome']) ?></h3>
                <p>
                  <?php if ($count > 0): ?>
                    Dal <?= h(report_format_date($item['firstDate'])) ?> al <?= h(report_format_date($item['lastDate'])) ?>
                  <?php else: ?>
                    Nessun dato peso disponibile
                  <?php endif; ?>
                </p>
              </div>
            </div>

            <?php if ($count > 0): ?>
              <span class="trend-pill <?= h($trendClass) ?>"><?= h($trendLabel) ?></span>
            <?php endif; ?>
          </header>

          <?php if (!$item['data']): ?>
            <div class="empty-weight">
              Nessuna misurazione peso disponibile per questo cliente.
            </div>
          <?php else: ?>
            <div class="weight-stats">
              <div class="weight-stat">
                <span>Ultimo peso</span>
                <strong><?= h(report_num($item['latest'], 1)) ?> kg</strong>
              </div>

              <div class="weight-stat">
                <span>Minimo</span>
                <strong><?= h(report_num($item['min'], 1)) ?> kg</strong>
              </div>

              <div class="weight-stat">
                <span>Massimo</span>
                <strong><?= h(report_num($item['max'], 1)) ?> kg</strong>
              </div>

              <div class="weight-stat">
                <span>Misurazioni</span>
                <strong><?= h((string)$count) ?></strong>
              </div>
            </div>

            <div class="chart-panel">
              <canvas
                id="pesoChartCliente<?= (int)$idCliente ?>"
                aria-label="Grafico peso cliente <?= h($item['nome']) ?>"
                role="img"
              ></canvas>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>

<?php
$chartsPayload = [];

foreach ($clientiPeso as $idCliente => $item) {
  if (!$item['data']) {
    continue;
  }

  $min = (float)$item['min'];
  $max = (float)$item['max'];
  $range = max(0.8, $max - $min);
  $padding = max(0.4, $range * 0.22);

  $chartsPayload[] = [
    'id' => 'pesoChartCliente' . (int)$idCliente,
    'label' => $item['nome'],
    'labels' => $item['labels'],
    'tooltipLabels' => $item['tooltipLabels'],
    'data' => $item['data'],
    'suggestedMin' => round($min - $padding, 1),
    'suggestedMax' => round($max + $padding, 1),
  ];
}

$scripts = '';

if ($chartsPayload) {
  $scripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>
    const chartsPayload = ' . json_encode($chartsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';

    const css = getComputedStyle(document.documentElement);
    const axisColor = "rgba(234,240,255,.68)";
    const axisColorSoft = "rgba(234,240,255,.46)";
    const gridColor = "rgba(234,240,255,.10)";
    const borderColor = "#4CC9F0";
    const pointColor = "#2EE1A5";

    function isMobileChart() {
      return window.matchMedia("(max-width: 820px)").matches;
    }

    function makeGradient(ctx, chartArea) {
      const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
      gradient.addColorStop(0, "rgba(76,201,240,.36)");
      gradient.addColorStop(.55, "rgba(76,201,240,.13)");
      gradient.addColorStop(1, "rgba(76,201,240,0)");
      return gradient;
    }

    function getPointRadius(dataLength) {
      if (isMobileChart()) {
        return dataLength <= 8 ? 4 : 2.5;
      }

      return dataLength <= 14 ? 4.5 : 3;
    }

    chartsPayload.forEach((item) => {
      const el = document.getElementById(item.id);

      if (!el) {
        return;
      }

      const ctx = el.getContext("2d");

      new Chart(ctx, {
        type: "line",
        data: {
          labels: item.labels,
          datasets: [{
            label: "Peso",
            data: item.data,
            borderColor: borderColor,
            backgroundColor: (context) => {
              const chart = context.chart;
              const chartArea = chart.chartArea;

              if (!chartArea) {
                return "rgba(76,201,240,.18)";
              }

              return makeGradient(chart.ctx, chartArea);
            },
            pointBackgroundColor: pointColor,
            pointBorderColor: "#071018",
            pointBorderWidth: 2,
            pointRadius: getPointRadius(item.data.length),
            pointHoverRadius: 7,
            borderWidth: isMobileChart() ? 3 : 3.5,
            fill: true,
            tension: .38,
            cubicInterpolationMode: "monotone"
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: "index",
            intersect: false
          },
          layout: {
            padding: {
              top: 8,
              right: isMobileChart() ? 4 : 14,
              bottom: 0,
              left: isMobileChart() ? 0 : 8
            }
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              enabled: true,
              backgroundColor: "rgba(7,10,18,.94)",
              titleColor: "#FFFFFF",
              bodyColor: "rgba(234,240,255,.86)",
              borderColor: "rgba(255,255,255,.14)",
              borderWidth: 1,
              padding: 12,
              cornerRadius: 14,
              displayColors: false,
              callbacks: {
                title: (items) => {
                  const index = items[0]?.dataIndex ?? 0;
                  return item.tooltipLabels[index] || item.labels[index] || "";
                },
                label: (context) => {
                  const value = Number(context.raw || 0);
                  return "Peso: " + value.toLocaleString("it-IT", {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1
                  }) + " kg";
                },
                afterLabel: (context) => {
                  const index = context.dataIndex;

                  if (index <= 0) {
                    return "";
                  }

                  const current = Number(item.data[index]);
                  const previous = Number(item.data[index - 1]);
                  const delta = current - previous;

                  if (!Number.isFinite(delta)) {
                    return "";
                  }

                  if (delta === 0) {
                    return "Variazione: stabile";
                  }

                  const sign = delta > 0 ? "+" : "";
                  return "Variazione: " + sign + delta.toLocaleString("it-IT", {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1
                  }) + " kg";
                }
              }
            }
          },
          scales: {
            x: {
              border: {
                color: "rgba(234,240,255,.16)"
              },
              grid: {
                display: false
              },
              ticks: {
                color: axisColorSoft,
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: isMobileChart() ? 4 : 8,
                font: {
                  size: isMobileChart() ? 10 : 12,
                  weight: "600"
                }
              }
            },
            y: {
              suggestedMin: item.suggestedMin,
              suggestedMax: item.suggestedMax,
              border: {
                color: "rgba(234,240,255,.16)"
              },
              grid: {
                color: gridColor,
                drawTicks: false
              },
              ticks: {
                color: axisColor,
                padding: 8,
                maxTicksLimit: isMobileChart() ? 5 : 7,
                font: {
                  size: isMobileChart() ? 10 : 12,
                  weight: "600"
                },
                callback: (value) => Number(value).toLocaleString("it-IT", {
                  minimumFractionDigits: 1,
                  maximumFractionDigits: 1
                })
              }
            }
          }
        }
      });
    });
  </script>';
}

renderEnd($scripts);
?>
