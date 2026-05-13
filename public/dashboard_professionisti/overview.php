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

$heroName = trim((string)$displayName);
if (strpos($heroName, '@') !== false) {
  $mailHead = trim((string)strtok($heroName, '@'));
  if ($mailHead !== '') {
    $heroName = $mailHead;
  }
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
*,:before,:after{box-sizing:border-box}.overview-shell{min-height:100vh;border-radius:30px;padding:10px;max-width:100%;min-width:0;overflow-x:hidden}.overview-grid{display:grid;gap:16px;max-width:100%;min-width:0}.kpi-link{text-decoration:none;color:inherit;display:block;height:100%;min-width:0}.hero-redesign{border:1px solid rgba(255,255,255,.1);border-radius:30px;padding:30px;max-width:100%;overflow:hidden;background:radial-gradient(circle at 85% 20%,rgba(56,189,248,.16),transparent 35%),radial-gradient(circle at 20% 20%,rgba(99,102,241,.2),transparent 40%),linear-gradient(145deg,rgba(15,23,42,.95),rgba(15,23,42,.82));box-shadow:0 22px 42px rgba(0,0,0,.35)}.hero-title{margin:12px 0 0;font-size:clamp(34px,4vw,52px);line-height:1.02;letter-spacing:-.03em}.hero-subtitle{margin:14px 0 0;color:#cbd5e1;max-width:70ch}.kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;grid-auto-rows:1fr;align-items:stretch;max-width:100%;min-width:0}.kpi-card{border:1px solid rgba(255,255,255,.1);border-radius:28px;padding:16px;background:linear-gradient(165deg,rgba(255,255,255,.05),rgba(255,255,255,.02));box-shadow:0 14px 30px rgba(0,0,0,.32);height:100%;display:flex;flex-direction:column;justify-content:space-between;gap:8px;min-width:0}.kpi-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:11px;background:rgba(15,23,42,.72);font-size:14px;margin-bottom:8px}.kpi-label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.kpi-value{margin:4px 0;font-size:clamp(28px,3.5vw,44px);font-weight:800;line-height:1.05;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.kpi-helper{margin:0;color:#cbd5e1;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.acc-default .kpi-value{color:#e2e8f0}.acc-info .kpi-value{color:#67e8f9}.acc-success .kpi-value{color:#6ee7b7}.acc-warning .kpi-value{color:#fcd34d}.content-grid{display:grid;grid-template-columns:1.4fr .9fr;gap:14px;max-width:100%;min-width:0}.card-redesign{border:1px solid rgba(255,255,255,.1);border-radius:28px;padding:20px;background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.02));box-shadow:0 16px 36px rgba(0,0,0,.34);min-width:0}.section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:12px}.section-head h3{margin:0;font-size:24px}.section-head p{margin:4px 0 0;color:#94a3b8}.activity-list{display:grid;gap:10px}.activity-item{display:flex;justify-content:space-between;gap:10px;padding:12px;border-radius:18px;border:1px solid rgba(255,255,255,.08);background:rgba(2,6,23,.5);min-width:0}.activity-main{display:flex;gap:11px;align-items:flex-start;min-width:0}.tone-dot{width:10px;height:10px;border-radius:99px;margin-top:7px;box-shadow:0 0 0 4px rgba(255,255,255,.04)}.dot-default{background:#818cf8}.dot-info{background:#22d3ee}.dot-success{background:#34d399}.activity-client,.activity-action{margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}.activity-client{font-weight:700}.activity-action{margin-top:2px;color:#cbd5e1}.activity-time{font-size:13px;color:#94a3b8;white-space:nowrap}.notice-list{display:grid;gap:10px}.notice-item{border-radius:14px;padding:12px;border:1px solid rgba(255,255,255,.08);font-size:14px}.notice-info{background:rgba(34,211,238,.1)}.notice-warning{background:rgba(251,191,36,.1)}.notice-default{background:rgba(148,163,184,.12)}.btn-lite{border:1px solid rgba(255,255,255,.13);padding:8px 12px;border-radius:999px;color:#e2e8f0;text-decoration:none;background:rgba(255,255,255,.04)}@media(max-width:1050px){.content-grid{grid-template-columns:1fr}.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:820px){html,body{overflow-x:hidden}.overview-shell{padding:0 0 96px;background:transparent}.overview-grid{gap:12px;padding:10px}.hero-redesign{padding:20px;border-radius:22px}.hero-title{font-size:clamp(38px,11vw,52px);line-height:1.03}.hero-title .break{display:block}.hero-subtitle{font-size:15px;line-height:1.4}.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.kpi-card,.card-redesign{border-radius:20px;padding:14px}.section-head h3{font-size:30px}.section-head p{display:none}.activity-item{border-radius:16px;padding:11px}.activity-time{font-size:12px}.quick-card,.desktop-only-quick{display:none!important}}
</style>
<section class="overview-shell"><div class="overview-grid">
<article class="hero-redesign"><span class="pill">Home dashboard</span><h1 class="hero-title">Ciao,<span class="break"><?= h($heroName) ?></span></h1><p class="hero-subtitle">Gestisci clienti, ID-Key, piani di allenamento e nutrizione da una vista mobile più rapida.</p></article>
<section><div class="kpi-grid"><?php foreach ($kpiItems as $kpi): ?><?php if (!empty($kpi['href'])): ?><a class="kpi-link" href="<?= h((string)$kpi['href']) ?>"><?php endif; ?><article class="kpi-card acc-<?= h($kpi['accent']) ?>"><span class="kpi-icon"><?= $kpi['accent'] === 'info' ? '🔑' : ($kpi['accent'] === 'success' ? '⭐' : ($kpi['accent'] === 'warning' ? '🗓️' : '👥')) ?></span><p class="kpi-label"><?= h($kpi['label']) ?></p><p class="kpi-value"><?= h($kpi['value']) ?></p><p class="kpi-helper"><?= h($kpi['helper']) ?></p></article><?php if (!empty($kpi['href'])): ?></a><?php endif; ?><?php endforeach; ?></div></section>
<section class="content-grid"><article class="card-redesign"><div class="section-head"><div><h3>Ultime attività</h3><p>Eventi più recenti in ordine operativo.</p></div><a class="btn-lite" href="report.php">Vedi tutto</a></div><div class="activity-list"><?php foreach ($latestActivities as $activity): ?><article class="activity-item"><div class="activity-main"><span class="tone-dot dot-<?= h((string)($activity['tono'] ?? 'default')) ?>"></span><div><p class="activity-client"><?= h($activity['cliente']) ?></p><p class="activity-action"><?= h($activity['evento']) ?></p></div></div><span class="activity-time"><?= h($activity['orario']) ?></span></article><?php endforeach; ?></div></article>
<aside><article class="card-redesign"><div class="section-head"><div><h3>Notifiche</h3><p>Aggiornamenti operativi.</p></div><a class="btn-lite" href="#">Apri</a></div><div class="notice-list"><div class="notice-item notice-warning">⚠️ Alcuni clienti non hanno aggiornato il diario nutrizionale.</div><div class="notice-item notice-info">ℹ️ Nuovi questionari inviati oggi.</div><div class="notice-item notice-default">💬 Sono disponibili nuovi feedback sui piani.</div></div></article></aside></section></div></section>
<?php renderEnd();
