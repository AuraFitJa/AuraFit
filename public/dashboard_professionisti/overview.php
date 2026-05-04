<?php
require __DIR__ . '/common.php';

function formatActivityTime(?string $dateTime): string {
  if (!$dateTime) {
    return 'Ora non disponibile';
  }
  try {
    $dt = new DateTimeImmutable($dateTime);
  } catch (Throwable $e) {
    return $dateTime;
  }
  $today = new DateTimeImmutable('today');
  $yesterday = $today->modify('-1 day');
  if ($dt->format('Y-m-d') === $today->format('Y-m-d')) {
    return 'Oggi, ' . $dt->format('H:i');
  }
  if ($dt->format('Y-m-d') === $yesterday->format('Y-m-d')) {
    return 'Ieri, ' . $dt->format('H:i');
  }
  return $dt->format('d/m/Y, H:i');
}

function overviewTableExists(string $table): bool {
  try {
    $row = Database::exec('SHOW TABLES LIKE ?', [$table])->fetch();
    return (bool)$row;
  } catch (Throwable $e) {
    return false;
  }
}

function overviewTableColumns(string $table): array {
  try {
    $rows = Database::exec('SHOW COLUMNS FROM ' . $table)->fetchAll();
    if (!is_array($rows)) {
      return [];
    }
    $out = [];
    foreach ($rows as $row) {
      $field = (string)($row['Field'] ?? '');
      if ($field !== '') {
        $out[] = $field;
      }
    }
    return $out;
  } catch (Throwable $e) {
    return [];
  }
}

function overviewPickColumn(array $columns, array $candidates): ?string {
  foreach ($candidates as $candidate) {
    if (in_array($candidate, $columns, true)) {
      return $candidate;
    }
  }
  return null;
}

if ($dbAvailable) {
  try {
    $professionistaId = getProfessionistaId($userId);
    if ($professionistaId) {
      $clientiCount = Database::exec(
        "SELECT COUNT(DISTINCT a.cliente) AS totale
         FROM Associazioni a
         WHERE a.professionista = ? AND a.attivaFlag = 1",
        [$professionistaId]
      )->fetch();
      $overview['clientiAttivi'] = (int)($clientiCount['totale'] ?? $overview['clientiAttivi']);

      $idKeyStats = Database::exec(
        "SELECT
            SUM(CASE WHEN stato = 'attiva' THEN 1 ELSE 0 END) AS disponibili,
            SUM(CASE WHEN stato <> 'attiva' THEN 1 ELSE 0 END) AS utilizzate,
            COUNT(*) AS totali
         FROM IdKey
         WHERE professionista = ?",
        [$professionistaId]
      )->fetch();

      $overview['idKeyDisponibili'] = (int)($idKeyStats['disponibili'] ?? $overview['idKeyDisponibili']);
      $idKeyUtilizzate = (int)($idKeyStats['utilizzate'] ?? 0);
      $overview['idKeyTotaliPiano'] = (int)($idKeyStats['totali'] ?? $overview['idKeyTotaliPiano']);

      $activityRows = [];

      if (overviewTableExists('QuestionarioCompilazioni') && overviewTableExists('Questionari')) {
        $questionariRows = Database::exec(
          "SELECT CONCAT(u.nome, ' ', LEFT(u.cognome, 1), '.') AS cliente,
                  'Questionario compilato' AS evento,
                  COALESCE(qc.inviatoIl, qc.aggiornatoIl, qc.iniziatoIl) AS eventoIl,
                  'info' AS tono
           FROM QuestionarioCompilazioni qc
           INNER JOIN Questionari q ON q.idQuestionario = qc.questionario
           INNER JOIN Clienti c ON c.idCliente = qc.cliente
           INNER JOIN Utenti u ON u.idUtente = c.idUtente
           WHERE q.professionista = ?
             AND qc.stato = 'inviato'
             AND COALESCE(qc.inviatoIl, qc.aggiornatoIl, qc.iniziatoIl) IS NOT NULL
           ORDER BY COALESCE(qc.inviatoIl, qc.aggiornatoIl, qc.iniziatoIl) DESC
           LIMIT 12",
          [$professionistaId]
        )->fetchAll();
        $activityRows = array_merge($activityRows, is_array($questionariRows) ? $questionariRows : []);
      }

      if (overviewTableExists('VociDiarioAlimentare')) {
        $cols = overviewTableColumns('VociDiarioAlimentare');
        $clienteCol = overviewPickColumn($cols, ['cliente', 'idCliente']);
        $timeCol = overviewPickColumn($cols, ['creatoIl', 'createdAt', 'inseritoIl', 'dataRiferimento', 'dataDiario', 'data']);
        if ($clienteCol && $timeCol) {
          $nutrizioneRows = Database::exec(
            "SELECT CONCAT(u.nome, ' ', LEFT(u.cognome, 1), '.') AS cliente,
                    'Diario nutrizionale aggiornato' AS evento,
                    v." . $timeCol . " AS eventoIl,
                    'success' AS tono
             FROM VociDiarioAlimentare v
             INNER JOIN Associazioni a ON a.cliente = v." . $clienteCol . " AND a.professionista = ? AND a.attivaFlag = 1
             INNER JOIN Clienti c ON c.idCliente = v." . $clienteCol . "
             INNER JOIN Utenti u ON u.idUtente = c.idUtente
             WHERE v." . $timeCol . " IS NOT NULL
             ORDER BY v." . $timeCol . " DESC
             LIMIT 12",
            [$professionistaId]
          )->fetchAll();
          $activityRows = array_merge($activityRows, is_array($nutrizioneRows) ? $nutrizioneRows : []);
        }
      }

      if (overviewTableExists('SessioniAllenamento')) {
        $allenamentoRows = Database::exec(
          "SELECT CONCAT(u.nome, ' ', LEFT(u.cognome, 1), '.') AS cliente,
                  'Allenamento registrato' AS evento,
                  s.svoltaIl AS eventoIl,
                  'default' AS tono
           FROM SessioniAllenamento s
           INNER JOIN Associazioni a ON a.cliente = s.cliente AND a.professionista = ? AND a.attivaFlag = 1
           INNER JOIN Clienti c ON c.idCliente = s.cliente
           INNER JOIN Utenti u ON u.idUtente = c.idUtente
           WHERE s.svoltaIl IS NOT NULL
           ORDER BY s.svoltaIl DESC
           LIMIT 12",
          [$professionistaId]
        )->fetchAll();
        $activityRows = array_merge($activityRows, is_array($allenamentoRows) ? $allenamentoRows : []);
      }

      usort($activityRows, static function (array $a, array $b): int {
        $at = strtotime((string)($a['eventoIl'] ?? '')) ?: 0;
        $bt = strtotime((string)($b['eventoIl'] ?? '')) ?: 0;
        return $bt <=> $at;
      });
      $activityRows = array_slice($activityRows, 0, 8);

      if ($activityRows) {
        $latestActivities = array_map(static function (array $row): array {
          return [
            'cliente' => (string)($row['cliente'] ?? 'Cliente'),
            'evento' => (string)($row['evento'] ?? 'Attività aggiornata'),
            'orario' => formatActivityTime((string)($row['eventoIl'] ?? '')),
            'tono' => (string)($row['tono'] ?? 'default'),
          ];
        }, $activityRows);
      }

      $overview['idKeyUtilizzate'] = $idKeyUtilizzate;
    }
  } catch (Throwable $e) {
    // fallback ai dati già presenti.
  }
}

$displayName = trim(($professionistaProfileForm['nome'] ?? '') . ' ' . ($professionistaProfileForm['cognome'] ?? ''));
if ($displayName === '') {
  $displayName = $email;
}

$kpiItems = [
  ['label' => 'Clienti attivi', 'value' => (string)$overview['clientiAttivi'], 'helper' => 'Clienti associati attivi', 'accent' => 'default', 'href' => 'clienti.php'],
  ['label' => 'ID-Key disponibili', 'value' => (string)$overview['idKeyDisponibili'], 'helper' => ($overview['idKeyUtilizzate'] ?? 0) . ' utilizzate su ' . $overview['idKeyTotaliPiano'], 'accent' => 'info', 'href' => 'idkey.php'],
  ['label' => 'Abbonamento', 'value' => (string)$overview['piano'], 'helper' => 'Stato: ' . $overview['pianoStato'], 'accent' => 'success', 'href' => null],
  ['label' => 'Rinnovo', 'value' => (string)$overview['rinnovo'], 'helper' => 'Fatturazione automatica attiva', 'accent' => 'warning', 'href' => null],
];

renderStart('Overview dashboard', 'overview', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<style>
.overview-shell{min-height:100vh;border-radius:30px;padding:10px}
.kpi-link{text-decoration:none;color:inherit;display:block}
/* existing styles */
.overview-grid{display:grid;gap:16px}.hero-redesign{border:1px solid rgba(255,255,255,.1);border-radius:30px;padding:30px;background:radial-gradient(circle at 85% 20%,rgba(56,189,248,.16),transparent 35%),radial-gradient(circle at 20% 20%,rgba(99,102,241,.2),transparent 40%),linear-gradient(145deg,rgba(15,23,42,.95),rgba(15,23,42,.82));box-shadow:0 22px 42px rgba(0,0,0,.35)}
.hero-layout{display:grid;grid-template-columns:1fr;gap:16px;align-items:stretch}.hero-title{margin:12px 0 0;font-size:clamp(34px,4vw,52px);line-height:1.02;letter-spacing:-.03em}
.kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.kpi-card{border:1px solid rgba(255,255,255,.1);border-radius:28px;padding:18px;background:linear-gradient(165deg,rgba(255,255,255,.05),rgba(255,255,255,.02));box-shadow:0 14px 30px rgba(0,0,0,.32)}
.kpi-link .kpi-card{transition:transform .15s ease,border-color .15s ease,background .2s ease}.kpi-link:hover .kpi-card{transform:translateY(-2px);border-color:rgba(103,232,249,.5);background:linear-gradient(165deg,rgba(255,255,255,.08),rgba(255,255,255,.03))}
.kpi-label{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8}.kpi-value{margin:8px 0 6px;font-size:clamp(30px,3.5vw,44px);font-weight:800;line-height:1.05}.kpi-helper{margin:0;color:#cbd5e1}.acc-default .kpi-value{color:#e2e8f0}.acc-info .kpi-value{color:#67e8f9}.acc-success .kpi-value{color:#6ee7b7}.acc-warning .kpi-value{color:#fcd34d}
.content-grid{display:grid;grid-template-columns:1.4fr .9fr;gap:14px}.card-redesign{border:1px solid rgba(255,255,255,.1);border-radius:28px;padding:20px;background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.02));box-shadow:0 16px 36px rgba(0,0,0,.34)}
.section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:12px}.section-head h3{margin:0;font-size:24px}.section-head p{margin:4px 0 0;color:#94a3b8}.activity-list{display:grid;gap:10px}.activity-item{display:flex;justify-content:space-between;gap:10px;padding:12px;border-radius:18px;border:1px solid rgba(255,255,255,.08);background:rgba(2,6,23,.5)}.activity-main{display:flex;gap:11px;align-items:flex-start}.tone-dot{width:10px;height:10px;border-radius:99px;margin-top:7px;box-shadow:0 0 0 4px rgba(255,255,255,.04)}.dot-default{background:#818cf8}.dot-info{background:#22d3ee}.dot-success{background:#34d399}.activity-client{margin:0;font-weight:700}.activity-action{margin:2px 0 0;color:#cbd5e1}.activity-time{font-size:13px;color:#94a3b8;white-space:nowrap}
.btn-lite{border:1px solid rgba(255,255,255,.13);padding:8px 12px;border-radius:999px;color:#e2e8f0;text-decoration:none;background:rgba(255,255,255,.04)}.notice-pill{font-size:12px;padding:5px 10px;border-radius:999px;border:1px solid rgba(56,189,248,.35);color:#67e8f9;background:rgba(34,211,238,.1)}.notice-box{border-radius:18px;padding:14px;border:1px dashed rgba(148,163,184,.35);color:#cbd5e1;background:rgba(15,23,42,.45)}.quick-card{margin-top:12px;border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:18px;background:radial-gradient(circle at 20% 10%,rgba(52,211,153,.18),transparent 45%),radial-gradient(circle at 80% 80%,rgba(99,102,241,.22),transparent 50%),rgba(15,23,42,.8)}.quick-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
@media(max-width:1050px){.hero-layout,.content-grid{grid-template-columns:1fr}.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.overview-shell{padding:0;background:transparent}.hero-redesign{padding:22px;border-radius:24px}.kpi-grid{grid-template-columns:1fr}.kpi-card,.card-redesign{border-radius:22px}.hero-title{font-size:36px}}
</style>
<section class="overview-shell"><div class="overview-grid">
<article class="hero-redesign"><div class="hero-layout"><div><span class="pill">Home dashboard</span><h1 class="hero-title">Ciao, <?= h($displayName) ?></h1></div></div></article>
<section class="kpi-grid"><?php foreach ($kpiItems as $kpi): ?><?php if (!empty($kpi['href'])): ?><a class="kpi-link" href="<?= h((string)$kpi['href']) ?>"><?php endif; ?><article class="kpi-card acc-<?= h($kpi['accent']) ?>"><p class="kpi-label"><?= h($kpi['label']) ?></p><p class="kpi-value"><?= h($kpi['value']) ?></p><p class="kpi-helper"><?= h($kpi['helper']) ?></p></article><?php if (!empty($kpi['href'])): ?></a><?php endif; ?><?php endforeach; ?></section>
<section class="content-grid"><article class="card-redesign"><div class="section-head"><div><h3>Ultime attività clienti</h3><p>Eventi più recenti in ordine operativo.</p></div><a class="btn-lite" href="report.php">Vedi tutto</a></div><div class="activity-list"><?php foreach ($latestActivities as $activity): ?><article class="activity-item"><div class="activity-main"><span class="tone-dot dot-<?= h((string)($activity['tono'] ?? 'default')) ?>"></span><div><p class="activity-client"><?= h($activity['cliente']) ?></p><p class="activity-action"><?= h($activity['evento']) ?></p></div></div><span class="activity-time"><?= h($activity['orario']) ?></span></article><?php endforeach; ?></div></article>
<aside><article class="card-redesign"><div class="section-head"><div><h3>Notifiche recenti</h3><p>Centro notifiche in aggiornamento.</p></div><span class="notice-pill">3 nuove</span></div><div class="notice-box">Le notifiche actionable saranno abilitate nella prossima iterazione della overview.</div></article><article class="quick-card"><h3 style="margin:0 0 6px">Quick overview</h3><p class="muted" style="margin:0">Accesso rapido alle aree con maggiore impatto operativo della giornata.</p><div class="quick-actions"><a class="btn primary" href="clienti.php">Apri clienti</a><a class="btn" href="report.php">Vai a reportistica</a></div></article></aside></section></div></section>
<?php renderEnd();
