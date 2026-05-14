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

renderStart('Report peso', 'report', $email, $roleBadge, $isPt, $isNutrizionista);
?>

<style>
  .report-page {
    display: grid;
    gap: 16px;
  }

  .report-hero {
    padding: clamp(22px, 4vw, 34px);
    border-radius: 28px;
    background:
      linear-gradient(145deg, rgba(255,255,255,.085), rgba(255,255,255,.035)),
      rgba(10, 16, 28, .84);
    border: 1px solid rgba(255,255,255,.10);
    box-shadow: 0 18px 50px rgba(0,0,0,.25);
  }

  .report-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    margin-bottom: 14px;
    padding: 8px 11px;
    border-radius: 999px;
    background: rgba(76,201,240,.10);
    border: 1px solid rgba(76,201,240,.18);
    color: rgba(234,240,255,.84);
    font-size: 12px;
    font-weight: 800;
  }

  .report-eyebrow::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #4CC9F0;
    box-shadow: 0 0 0 6px rgba(76,201,240,.12);
  }

  .report-hero h1 {
    margin: 0;
    font-size: clamp(34px, 5vw, 56px);
    line-height: .95;
    letter-spacing: -.06em;
  }

  .report-hero p {
    max-width: 56ch;
    margin: 14px 0 0;
    color: rgba(234,240,255,.72);
    font-size: 15px;
    line-height: 1.6;
  }

  .clients-list {
    display: grid;
    gap: 12px;
  }

  .weight-card {
    overflow: hidden;
    border-radius: 24px;
    background:
      linear-gradient(180deg, rgba(255,255,255,.07), rgba(255,255,255,.035));
    border: 1px solid rgba(255,255,255,.09);
    box-shadow: 0 16px 44px rgba(0,0,0,.24), inset 0 0 0 1px rgba(255,255,255,.025);
  }

  .weight-card[open] {
    border-color: rgba(76,201,240,.22);
    box-shadow: 0 20px 56px rgba(0,0,0,.30), 0 0 0 1px rgba(76,201,240,.08) inset;
  }

  .weight-summary {
    list-style: none;
    cursor: pointer;
    padding: 16px 18px;
  }

  .weight-summary::-webkit-details-marker {
    display: none;
  }

  .summary-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto auto;
    gap: 14px;
    align-items: center;
  }

  .client-title {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
  }

  .client-avatar {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 15px;
    background: linear-gradient(135deg, rgba(76,201,240,.95), rgba(46,225,165,.82));
    color: #061018;
    font-weight: 900;
  }

  .client-title h3 {
    margin: 0;
    overflow: hidden;
    color: #fff;
    font-size: 18px;
    line-height: 1.1;
    letter-spacing: -.035em;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .client-title p {
    margin: 5px 0 0;
    color: rgba(234,240,255,.58);
    font-size: 12px;
  }

  .summary-stat {
    min-width: 118px;
    padding: 10px 12px;
    border-radius: 16px;
    background: rgba(255,255,255,.045);
    border: 1px solid rgba(255,255,255,.075);
  }

  .summary-stat span {
    display: block;
    margin-bottom: 5px;
    color: rgba(234,240,255,.56);
    font-size: 11px;
    font-weight: 700;
  }

  .summary-stat strong {
    display: block;
    color: #fff;
    font-size: 17px;
    line-height: 1;
  }

  .trend-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 170px;
    padding: 9px 12px;
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

  .toggle-icon {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    background: rgba(255,255,255,.055);
    border: 1px solid rgba(255,255,255,.10);
    color: rgba(234,240,255,.78);
    font-size: 18px;
    transition: transform .18s ease, background .18s ease;
  }

  .weight-card[open] .toggle-icon {
    transform: rotate(180deg);
    background: rgba(76,201,240,.12);
    color: #fff;
  }

  .weight-detail {
    padding: 0 18px 18px;
  }

  .detail-divider {
    height: 1px;
    margin-bottom: 16px;
    background: rgba(255,255,255,.08);
  }

  .weight-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
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

  .chart-panel {
    position: relative;
    height: clamp(280px, 42vw, 430px);
    padding: 14px 14px 8px;
    border-radius: 22px;
    background:
      radial-gradient(circle at 18% 0%, rgba(76,201,240,.10), transparent 16rem),
      rgba(4, 8, 16, .24);
    border: 1px solid rgba(255,255,255,.075);
  }

  .chart-panel canvas {
    width: 100% !important;
    height: 100% !important;
  }

  .empty-weight {
    padding: 16px;
    border-radius: 18px;
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

  @media (max-width: 980px) {
    .summary-grid {
      grid-template-columns: minmax(0, 1fr) auto;
    }

    .summary-stat,
    .trend-pill {
      display: none;
    }

    .weight-stats {
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
      font-size: clamp(32px, 11vw, 44px);
    }

    .report-hero p {
      font-size: 14px;
      line-height: 1.55;
    }

    .weight-card {
      border-radius: 20px;
    }

    .weight-summary {
      padding: 14px;
    }

    .client-avatar {
      width: 38px;
      height: 38px;
      border-radius: 13px;
    }

    .client-title h3 {
      font-size: 16px;
    }

    .client-title p {
      font-size: 11px;
    }

    .weight-detail {
      padding: 0 14px 14px;
    }

    .weight-stats {
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }

    .weight-stat {
      padding: 11px;
      border-radius: 16px;
    }

    .weight-stat strong {
      font-size: 19px;
    }

    .chart-panel {
      height: 320px;
      padding: 10px 6px 6px;
      border-radius: 18px;
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
    <div class="report-eyebrow">Monitoraggio clienti</div>
    <h1>Report peso</h1>
    <p>Visualizza l’andamento del peso dei clienti associati</p>
  </section>

  <?php if (!$dbAvailable): ?>
    <section class="report-alert"><?= h($dbError ?? 'Database non disponibile.') ?></section>
  <?php elseif ($clientiError): ?>
    <section class="report-alert"><?= h($clientiError) ?></section>
  <?php elseif (!$clientiPeso): ?>
    <section class="card">
      <h2 class="section-title">Report peso</h2>
      <p class="muted">Nessun cliente associato trovato.</p>
    </section>
  <?php else: ?>
    <section class="clients-list" aria-label="Report peso clienti">
      <?php foreach ($clientiPeso as $idCliente => $item): ?>
        <?php
          $count = count($item['data']);
          $delta = $item['delta'];
          $trendClass = '';
          $trendLabel = 'Una sola misurazione';

          if ($count === 0) {
            $trendLabel = 'Nessun dato';
          } elseif ($delta !== null) {
            if ($delta < 0) {
              $trendClass = 'down';
              $trendLabel = '↓ ' . report_num(abs($delta), 1) . ' kg';
            } elseif ($delta > 0) {
              $trendClass = 'up';
              $trendLabel = '↑ ' . report_num($delta, 1) . ' kg';
            } else {
              $trendLabel = 'Stabile';
            }
          }

          $initial = mb_strtoupper(mb_substr((string)$item['nome'], 0, 1, 'UTF-8'), 'UTF-8');
        ?>

        <details class="weight-card" data-chart-details>
          <summary class="weight-summary">
            <div class="summary-grid">
              <div class="client-title">
                <div class="client-avatar" aria-hidden="true"><?= h($initial) ?></div>
                <div>
                  <h3><?= h($item['nome']) ?></h3>
                  <p>
                    <?php if ($count > 0): ?>
                      Dal <?= h(report_format_date($item['firstDate'])) ?> al <?= h(report_format_date($item['lastDate'])) ?>
                    <?php else: ?>
                      Nessuna misurazione disponibile
                    <?php endif; ?>
                  </p>
                </div>
              </div>

              <div class="summary-stat">
                <span>Ultimo peso</span>
                <strong><?= $count > 0 ? h(report_num($item['latest'], 1)) . ' kg' : '—' ?></strong>
              </div>

              <span class="trend-pill <?= h($trendClass) ?>"><?= h($trendLabel) ?></span>

              <span class="toggle-icon" aria-hidden="true">⌄</span>
            </div>
          </summary>

          <div class="weight-detail">
            <div class="detail-divider"></div>

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
          </div>
        </details>
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
    const chartInstances = new Map();

    function isMobileChart() {
      return window.matchMedia("(max-width: 820px)").matches;
    }

    function makeGradient(ctx, chartArea) {
      const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
      gradient.addColorStop(0, "rgba(76,201,240,.34)");
      gradient.addColorStop(.55, "rgba(76,201,240,.12)");
      gradient.addColorStop(1, "rgba(76,201,240,0)");
      return gradient;
    }

    function getPointRadius(dataLength) {
      if (isMobileChart()) {
        return dataLength <= 8 ? 4 : 2.5;
      }

      return dataLength <= 14 ? 4.5 : 3;
    }

    function renderWeightChart(item) {
      if (chartInstances.has(item.id)) {
        chartInstances.get(item.id).resize();
        return;
      }

      const el = document.getElementById(item.id);

      if (!el) {
        return;
      }

      const ctx = el.getContext("2d");

      const chart = new Chart(ctx, {
        type: "line",
        data: {
          labels: item.labels,
          datasets: [{
            label: "Peso",
            data: item.data,
            borderColor: "#4CC9F0",
            backgroundColor: (context) => {
              const chart = context.chart;
              const chartArea = chart.chartArea;

              if (!chartArea) {
                return "rgba(76,201,240,.18)";
              }

              return makeGradient(chart.ctx, chartArea);
            },
            pointBackgroundColor: "#2EE1A5",
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
                color: "rgba(234,240,255,.50)",
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
                color: "rgba(234,240,255,.10)",
                drawTicks: false
              },
              ticks: {
                color: "rgba(234,240,255,.68)",
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

      chartInstances.set(item.id, chart);
    }

    document.querySelectorAll("[data-chart-details]").forEach((details) => {
      details.addEventListener("toggle", () => {
        if (!details.open) {
          return;
        }

        const canvas = details.querySelector("canvas[id]");

        if (!canvas) {
          return;
        }

        const item = chartsPayload.find((entry) => entry.id === canvas.id);

        if (!item) {
          return;
        }

        requestAnimationFrame(() => renderWeightChart(item));
      });
    });

    window.addEventListener("resize", () => {
      chartInstances.forEach((chart) => chart.resize());
    });
  </script>';
}

renderEnd($scripts);
?>
