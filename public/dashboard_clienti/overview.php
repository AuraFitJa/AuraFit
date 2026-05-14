<?php
require __DIR__ . '/common.php';

if (!function_exists('ov_fetch_one')) {
  function ov_fetch_one(string $sql, array $params = []): ?array {
    $row = Database::exec($sql, $params)->fetch();
    return $row ?: null;
  }
}

if (!function_exists('ov_fetch_all')) {
  function ov_fetch_all(string $sql, array $params = []): array {
    $rows = Database::exec($sql, $params)->fetchAll();
    return is_array($rows) ? $rows : [];
  }
}

if (!function_exists('ov_int')) {
  function ov_int($value): int {
    return $value === null ? 0 : (int)$value;
  }
}

if (!function_exists('ov_float')) {
  function ov_float($value): float {
    return $value === null ? 0.0 : (float)$value;
  }
}

if (!function_exists('ov_date')) {
  function ov_date(?string $value, bool $withTime = false): string {
    if (!$value) {
      return '—';
    }

    try {
      $date = new DateTime($value);
      return $withTime ? $date->format('d/m/Y H:i') : $date->format('d/m/Y');
    } catch (Throwable $e) {
      return '—';
    }
  }
}

if (!function_exists('ov_short')) {
  function ov_short(?string $value, int $max = 120): string {
    $value = trim((string)$value);
    if ($value === '') {
      return '';
    }

    if (mb_strlen($value, 'UTF-8') <= $max) {
      return $value;
    }

    return mb_substr($value, 0, $max - 1, 'UTF-8') . '…';
  }
}

if (!function_exists('ov_num')) {
  function ov_num($value, int $decimals = 0): string {
    if ($value === null || $value === '') {
      return '—';
    }

    return number_format((float)$value, $decimals, ',', '.');
  }
}

if (!function_exists('ov_pct')) {
  function ov_pct(float $value): int {
    return max(0, min(100, (int)round($value)));
  }
}

$pageError = null;
$idCliente = null;

$profile = null;
$professionisti = [];
$coach = null;
$nutrizionista = null;

$activeProgram = null;
$workoutDays = [];
$recentSessions = [];

$activePlan = null;
$planMeals = [];

$todayDiary = [
  'entries' => 0,
  'calorie' => 0,
  'proteine' => 0,
  'carbo' => 0,
  'grassi' => 0,
];

$questionari = [
  'total' => 0,
  'pending' => 0,
  'completed' => 0,
];

$latestQuestionari = [];
$latestMeasurements = [];
$weightRows = [];
$latestMessages = [];

$weekSessions = 0;
$loggedNutritionDays = 0;
$reportsCount = 0;

try {
  if (!$dbAvailable || !class_exists('Database')) {
    throw new RuntimeException($dbError ?? 'Database non disponibile.');
  }

  $profile = ov_fetch_one(
    'SELECT
       u.idUtente,
       u.nome,
       u.cognome,
       u.email,
       c.idCliente,
       pc.altezzaCm,
       pc.pesoKg,
       pc.eta,
       pc.livelloAttivita,
       pc.condizioni,
       pc.aggiornatoIl AS profiloAggiornatoIl
     FROM Utenti u
     LEFT JOIN Clienti c ON c.idUtente = u.idUtente
     LEFT JOIN ProfiloCliente pc ON pc.idCliente = c.idCliente
     WHERE u.idUtente = ?
     LIMIT 1',
    [(int)$user['idUtente']]
  );

  if (!$profile || empty($profile['idCliente'])) {
    throw new RuntimeException('Profilo cliente non collegato all’utente.');
  }

  $idCliente = (int)$profile['idCliente'];

  $professionisti = ov_fetch_all(
    'SELECT
       a.tipoAssociazione,
       a.stato,
       a.iniziataIl,
       p.idProfessionista,
       u.nome,
       u.cognome,
       u.email
     FROM Associazioni a
     INNER JOIN Professionisti p ON p.idProfessionista = a.professionista
     INNER JOIN Utenti u ON u.idUtente = p.idUtente
     WHERE a.cliente = ?
       AND a.attivaFlag = 1
       AND a.stato IN ("attiva", "attivo")
     ORDER BY FIELD(a.tipoAssociazione, "pt", "nutrizionista"), a.iniziataIl DESC',
    [$idCliente]
  );

  foreach ($professionisti as $pro) {
    if (($pro['tipoAssociazione'] ?? '') === 'pt' && !$coach) {
      $coach = $pro;
    }

    if (($pro['tipoAssociazione'] ?? '') === 'nutrizionista' && !$nutrizionista) {
      $nutrizionista = $pro;
    }
  }

  $activeProgram = ov_fetch_one(
    'SELECT
       pa.idProgramma,
       pa.titolo,
       pa.descrizione,
       pa.stato,
       pa.aggiornatoIl,
       COUNT(DISTINCT ga.idGiorno) AS giorni,
       COUNT(DISTINCT eg.idEsercizioGiorno) AS esercizi,
       COUNT(DISTINCT sp.idSeriePrescritta) AS serie
     FROM ProgrammiAllenamento pa
     LEFT JOIN GiorniAllenamento ga ON ga.programma = pa.idProgramma
     LEFT JOIN EserciziGiorno eg ON eg.giorno = ga.idGiorno
     LEFT JOIN SeriePrescritte sp ON sp.esercizioGiorno = eg.idEsercizioGiorno
     WHERE (
       pa.cliente = ?
       OR EXISTS (
         SELECT 1
         FROM AssegnazioniProgramma ap
         WHERE ap.programma = pa.idProgramma
           AND ap.cliente = ?
           AND ap.stato = "attivo"
       )
     )
       AND pa.stato IN ("attivo", "pubblicato")
     GROUP BY
       pa.idProgramma,
       pa.titolo,
       pa.descrizione,
       pa.stato,
       pa.aggiornatoIl
     ORDER BY pa.aggiornatoIl DESC, pa.idProgramma DESC
     LIMIT 1',
    [$idCliente, $idCliente]
  );

  if ($activeProgram) {
    $workoutDays = ov_fetch_all(
      'SELECT
         ga.idGiorno,
         ga.nome,
         ga.ordine,
         ga.note,
         COUNT(DISTINCT eg.idEsercizioGiorno) AS esercizi,
         COUNT(DISTINCT sp.idSeriePrescritta) AS serie,
         GROUP_CONCAT(DISTINCT e.nome ORDER BY eg.ordine SEPARATOR ", ") AS eserciziNomi
       FROM GiorniAllenamento ga
       LEFT JOIN EserciziGiorno eg ON eg.giorno = ga.idGiorno
       LEFT JOIN Esercizi e ON e.idEsercizio = eg.esercizio
       LEFT JOIN SeriePrescritte sp ON sp.esercizioGiorno = eg.idEsercizioGiorno
       WHERE ga.programma = ?
       GROUP BY
         ga.idGiorno,
         ga.nome,
         ga.ordine,
         ga.note
       ORDER BY ga.ordine ASC
       LIMIT 6',
      [(int)$activeProgram['idProgramma']]
    );
  }

  $weekRow = ov_fetch_one(
    'SELECT COUNT(*) AS total
     FROM SessioniAllenamento
     WHERE cliente = ?
       AND YEARWEEK(svoltaIl, 1) = YEARWEEK(CURDATE(), 1)',
    [$idCliente]
  );

  $weekSessions = ov_int($weekRow['total'] ?? 0);

  $recentSessions = ov_fetch_all(
    'SELECT
       sa.svoltaIl,
       sa.durataMinuti,
       sa.noteSessione,
       ga.nome AS giornoNome,
       pa.titolo AS programmaTitolo
     FROM SessioniAllenamento sa
     LEFT JOIN GiorniAllenamento ga ON ga.idGiorno = sa.giorno
     LEFT JOIN ProgrammiAllenamento pa ON pa.idProgramma = sa.programma
     WHERE sa.cliente = ?
     ORDER BY sa.svoltaIl DESC
     LIMIT 4',
    [$idCliente]
  );

  $activePlan = ov_fetch_one(
    'SELECT
       pa.idPianoAlim,
       pa.titolo,
       pa.note,
       pa.stato,
       pa.aggiornatoIl,
       COUNT(DISTINCT pp.idPastoPiano) AS pasti,
       COUNT(DISTINCT al.idAlimentoPiano) AS alimenti,
       COALESCE(SUM(al.calorie), 0) AS calorie,
       COALESCE(SUM(al.proteine), 0) AS proteine,
       COALESCE(SUM(al.carboidrati), 0) AS carboidrati,
       COALESCE(SUM(al.grassi), 0) AS grassi
     FROM PianiAlimentari pa
     LEFT JOIN AssegnazioniPianoAlimentare apa
       ON apa.pianoAlim = pa.idPianoAlim
      AND apa.cliente = ?
      AND apa.stato = "attivo"
     LEFT JOIN PastiPiano pp ON pp.pianoAlim = pa.idPianoAlim
     LEFT JOIN AlimentiPiano al ON al.pastoPiano = pp.idPastoPiano
     WHERE (pa.cliente = ? OR apa.idAssegnazionePiano IS NOT NULL)
       AND pa.stato IN ("attivo", "pubblicato")
     GROUP BY
       pa.idPianoAlim,
       pa.titolo,
       pa.note,
       pa.stato,
       pa.aggiornatoIl
     ORDER BY pa.aggiornatoIl DESC, pa.idPianoAlim DESC
     LIMIT 1',
    [$idCliente, $idCliente]
  );

  if ($activePlan) {
    $planMeals = ov_fetch_all(
      'SELECT
         pp.idPastoPiano,
         pp.nomePasto,
         pp.ordine,
         pp.note,
         COUNT(al.idAlimentoPiano) AS alimenti,
         COALESCE(SUM(al.calorie), 0) AS calorie,
         COALESCE(SUM(al.proteine), 0) AS proteine,
         COALESCE(SUM(al.carboidrati), 0) AS carboidrati,
         COALESCE(SUM(al.grassi), 0) AS grassi
       FROM PastiPiano pp
       LEFT JOIN AlimentiPiano al ON al.pastoPiano = pp.idPastoPiano
       WHERE pp.pianoAlim = ?
       GROUP BY
         pp.idPastoPiano,
         pp.nomePasto,
         pp.ordine,
         pp.note
       ORDER BY pp.ordine ASC
       LIMIT 6',
      [(int)$activePlan['idPianoAlim']]
    );
  }

  $todayDiaryRow = ov_fetch_one(
    'SELECT
       COUNT(*) AS entries,
       COALESCE(SUM(calorieFinali), 0) AS calorie,
       COALESCE(SUM(proteineFinali), 0) AS proteine,
       COALESCE(SUM(carboFinali), 0) AS carbo,
       COALESCE(SUM(grassiFinali), 0) AS grassi
     FROM VociDiarioAlimentare
     WHERE cliente = ?
       AND DATE(consumatoIl) = CURDATE()',
    [$idCliente]
  );

  if ($todayDiaryRow) {
    $todayDiary = [
      'entries' => ov_int($todayDiaryRow['entries'] ?? 0),
      'calorie' => ov_float($todayDiaryRow['calorie'] ?? 0),
      'proteine' => ov_float($todayDiaryRow['proteine'] ?? 0),
      'carbo' => ov_float($todayDiaryRow['carbo'] ?? 0),
      'grassi' => ov_float($todayDiaryRow['grassi'] ?? 0),
    ];
  }

  $loggedDaysRow = ov_fetch_one(
    'SELECT COUNT(DISTINCT DATE(consumatoIl)) AS total
     FROM VociDiarioAlimentare
     WHERE cliente = ?
       AND consumatoIl >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)',
    [$idCliente]
  );

  $loggedNutritionDays = ov_int($loggedDaysRow['total'] ?? 0);

  $questionariRow = ov_fetch_one(
    'SELECT
       COUNT(*) AS total,
       COALESCE(SUM(
         CASE
           WHEN EXISTS (
             SELECT 1
             FROM QuestionarioCompilazioni qc
             WHERE qc.assegnazione = qa.idAssegnazioneQuestionario
               AND qc.stato IN ("inviato", "completato")
           )
           THEN 0
           ELSE 1
         END
       ), 0) AS pending,
       COALESCE(SUM(
         CASE
           WHEN EXISTS (
             SELECT 1
             FROM QuestionarioCompilazioni qc
             WHERE qc.assegnazione = qa.idAssegnazioneQuestionario
               AND qc.stato IN ("inviato", "completato")
           )
           THEN 1
           ELSE 0
         END
       ), 0) AS completed
     FROM QuestionarioAssegnazioni qa
     WHERE qa.cliente = ?
       AND qa.stato = "attivo"',
    [$idCliente]
  );

  if ($questionariRow) {
    $questionari = [
      'total' => ov_int($questionariRow['total'] ?? 0),
      'pending' => ov_int($questionariRow['pending'] ?? 0),
      'completed' => ov_int($questionariRow['completed'] ?? 0),
    ];
  }

  $latestQuestionari = ov_fetch_all(
    'SELECT
       q.titolo,
       q.categoria,
       qa.assegnatoIl,
       qa.stato,
       (
         SELECT MAX(qc.inviatoIl)
         FROM QuestionarioCompilazioni qc
         WHERE qc.assegnazione = qa.idAssegnazioneQuestionario
           AND qc.stato IN ("inviato", "completato")
       ) AS inviatoIl
     FROM QuestionarioAssegnazioni qa
     INNER JOIN Questionari q ON q.idQuestionario = qa.questionario
     WHERE qa.cliente = ?
     ORDER BY qa.assegnatoIl DESC
     LIMIT 4',
    [$idCliente]
  );

  $measurementRows = ov_fetch_all(
    'SELECT
       tipoMisura,
       valore,
       unita,
       misurataIl
     FROM Misurazioni
     WHERE cliente = ?
     ORDER BY misurataIl DESC
     LIMIT 30',
    [$idCliente]
  );

  foreach ($measurementRows as $measurement) {
    $tipo = (string)($measurement['tipoMisura'] ?? '');
    if ($tipo !== '' && !isset($latestMeasurements[$tipo])) {
      $latestMeasurements[$tipo] = $measurement;
    }
  }

  $weightRowsDesc = ov_fetch_all(
    'SELECT
       valore,
       unita,
       misurataIl
     FROM Misurazioni
     WHERE cliente = ?
       AND tipoMisura = "peso"
     ORDER BY misurataIl DESC
     LIMIT 6',
    [$idCliente]
  );

  $weightRows = array_reverse($weightRowsDesc);

  $latestMessages = ov_fetch_all(
    'SELECT
       m.contenuto,
       m.creatoIl,
       m.tipoMessaggio,
       a.tipoAssociazione,
       CONCAT(TRIM(u.nome), " ", TRIM(u.cognome)) AS mittente
     FROM Messaggi m
     INNER JOIN Chat ch ON ch.idChat = m.chat
     INNER JOIN Associazioni a ON a.idAssociazione = ch.associazione
     INNER JOIN Utenti u ON u.idUtente = m.mittenteUtente
     WHERE a.cliente = ?
     ORDER BY m.creatoIl DESC
     LIMIT 4',
    [$idCliente]
  );

  $reportsRow = ov_fetch_one(
    'SELECT COUNT(*) AS total
     FROM Report
     WHERE cliente = ?',
    [$idCliente]
  );

  $reportsCount = ov_int($reportsRow['total'] ?? 0);
} catch (Throwable $e) {
  $pageError = $e->getMessage();
}

$displayName = trim((string)($profile['nome'] ?? $clienteProfileForm['nome'] ?? ''));
$displaySurname = trim((string)($profile['cognome'] ?? $clienteProfileForm['cognome'] ?? ''));
$displayEmail = (string)($profile['email'] ?? $clienteProfileForm['email'] ?? $user['email'] ?? '');
$displayTitle = trim($displayName . ' ' . $displaySurname);
if ($displayTitle === '') {
  $displayTitle = $displayEmail !== '' ? $displayEmail : 'Cliente';
}

$plannedDays = ov_int($activeProgram['giorni'] ?? 0);
$workoutPercent = $plannedDays > 0 ? ov_pct(($weekSessions / max(1, $plannedDays)) * 100) : 0;

$nutritionPercent = ov_pct(($loggedNutritionDays / 7) * 100);

$planCalories = ov_float($activePlan['calorie'] ?? 0);
$todayCalories = ov_float($todayDiary['calorie'] ?? 0);
$caloriePercent = $planCalories > 0 ? ov_pct(($todayCalories / $planCalories) * 100) : 0;

$currentWeight = isset($latestMeasurements['peso']) ? ov_float($latestMeasurements['peso']['valore'] ?? 0) : null;
$previousWeight = count($weightRowsDesc) >= 2 ? ov_float($weightRowsDesc[1]['valore'] ?? 0) : null;
$weightDelta = $currentWeight !== null && $previousWeight !== null ? $currentWeight - $previousWeight : null;

$waist = $latestMeasurements['circonferenza_vita'] ?? null;
$activityLevel = trim((string)($profile['livelloAttivita'] ?? ''));

$coachName = $coach
  ? trim((string)$coach['nome'] . ' ' . (string)$coach['cognome'])
  : '';

$nutrizionistaName = $nutrizionista
  ? trim((string)$nutrizionista['nome'] . ' ' . (string)$nutrizionista['cognome'])
  : '';

$weightValues = array_map(static fn($row) => ov_float($row['valore'] ?? 0), $weightRows);
$weightMin = $weightValues ? min($weightValues) : 0;
$weightMax = $weightValues ? max($weightValues) : 0;
$weightRange = max(0.1, $weightMax - $weightMin);

renderStart('Overview cliente', 'overview', $email);
?>

<style>
  .ov-page {
    display: grid;
    gap: 16px;
  }

  .ov-hero {
    position: relative;
    overflow: hidden;
    padding: clamp(20px, 4vw, 34px);
    border-radius: 28px;
    background:
      radial-gradient(circle at 16% 10%, rgba(109,94,243,.42), transparent 30rem),
      radial-gradient(circle at 84% 6%, rgba(46,225,165,.22), transparent 24rem),
      linear-gradient(145deg, rgba(255,255,255,.10), rgba(255,255,255,.04));
    border: 1px solid rgba(255,255,255,.10);
  }

  .ov-hero::after {
    content: "";
    position: absolute;
    width: 340px;
    height: 340px;
    right: -120px;
    bottom: -150px;
    border-radius: 999px;
    background: linear-gradient(135deg, rgba(76,201,240,.24), rgba(46,225,165,.18));
    filter: blur(18px);
    pointer-events: none;
  }

  .ov-hero-inner {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(280px, .85fr);
    gap: 20px;
    align-items: end;
  }

  .ov-eyebrow {
    width: fit-content;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    padding: 8px 11px;
    border-radius: 999px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    color: rgba(234,240,255,.84);
    font-size: 12px;
    font-weight: 800;
  }

  .ov-eyebrow::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #2EE1A5;
    box-shadow: 0 0 0 6px rgba(46,225,165,.12);
  }

  .ov-hero h1 {
    margin: 0;
    max-width: 12ch;
    font-size: clamp(34px, 5vw, 64px);
    line-height: .9;
    letter-spacing: -.06em;
  }

  .ov-hero-text {
    max-width: 58ch;
    margin: 16px 0 0;
    color: rgba(234,240,255,.74);
    line-height: 1.65;
    font-size: 15px;
  }

  .ov-hero-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
  }

  .ov-hero-panel {
    display: grid;
    gap: 10px;
    padding: 16px;
    border-radius: 22px;
    background: rgba(7,10,18,.34);
    border: 1px solid rgba(255,255,255,.10);
    backdrop-filter: blur(12px);
  }

  .ov-person-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    border-radius: 16px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.08);
  }

  .ov-person-row strong {
    display: block;
    font-size: 14px;
    margin-bottom: 3px;
  }

  .ov-person-row span {
    display: block;
    color: rgba(234,240,255,.66);
    font-size: 12px;
  }

  .ov-avatar {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(109,94,243,.92), rgba(46,225,165,.72));
    color: #061018;
    font-weight: 900;
  }

  .ov-section-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 14px;
  }

  .ov-card {
    position: relative;
    overflow: hidden;
    padding: 18px;
    border-radius: 24px;
    background: linear-gradient(180deg, rgba(255,255,255,.075), rgba(255,255,255,.035));
    border: 1px solid rgba(255,255,255,.08);
    box-shadow: 0 14px 42px rgba(0,0,0,.26), inset 0 0 0 1px rgba(255,255,255,.025);
  }

  .ov-card-3 { grid-column: span 3; }
  .ov-card-4 { grid-column: span 4; }
  .ov-card-5 { grid-column: span 5; }
  .ov-card-6 { grid-column: span 6; }
  .ov-card-7 { grid-column: span 7; }
  .ov-card-8 { grid-column: span 8; }
  .ov-card-12 { grid-column: span 12; }

  .ov-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
  }

  .ov-card h2,
  .ov-card h3 {
    margin: 0;
    font-size: 18px;
    letter-spacing: -.03em;
  }

  .ov-card-subtitle {
    margin: 6px 0 0;
    color: rgba(234,240,255,.62);
    font-size: 13px;
    line-height: 1.45;
  }

  .ov-kpi {
    display: block;
    margin-top: 4px;
    font-size: 34px;
    line-height: 1;
    font-weight: 900;
    letter-spacing: -.06em;
  }

  .ov-kpi-small {
    display: block;
    margin-top: 5px;
    color: rgba(234,240,255,.62);
    font-size: 13px;
    line-height: 1.35;
  }

  .ov-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    padding: 7px 10px;
    border-radius: 999px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.10);
    color: rgba(234,240,255,.78);
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
  }

  .ov-tag.ok {
    color: #C9FFE9;
    background: rgba(46,225,165,.12);
    border-color: rgba(46,225,165,.28);
  }

  .ov-tag.warn {
    color: #FFE9B3;
    background: rgba(255,209,102,.12);
    border-color: rgba(255,209,102,.28);
  }

  .ov-ring-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .ov-ring {
    --pct: 0%;
    --accent: #2EE1A5;
    width: 72px;
    height: 72px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 999px;
    background:
      conic-gradient(var(--accent) var(--pct), rgba(255,255,255,.10) 0);
  }

  .ov-ring::before {
    content: attr(data-label);
    width: 52px;
    height: 52px;
    display: grid;
    place-items: center;
    border-radius: inherit;
    background: #0b1220;
    color: #fff;
    font-size: 13px;
    font-weight: 900;
  }

  .ov-action-list,
  .ov-list {
    display: grid;
    gap: 10px;
  }

  .ov-list-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 13px;
    border-radius: 17px;
    background: rgba(255,255,255,.045);
    border: 1px solid rgba(255,255,255,.075);
  }

  .ov-list-item strong {
    display: block;
    margin-bottom: 5px;
    font-size: 14px;
  }

  .ov-list-item span,
  .ov-list-item p {
    display: block;
    margin: 0;
    color: rgba(234,240,255,.64);
    font-size: 13px;
    line-height: 1.45;
  }

  .ov-list-item-meta {
    text-align: right;
    color: rgba(234,240,255,.58);
    font-size: 12px;
    white-space: nowrap;
  }

  .ov-empty {
    padding: 16px;
    border-radius: 18px;
    background: rgba(255,255,255,.04);
    border: 1px dashed rgba(255,255,255,.14);
    color: rgba(234,240,255,.66);
    line-height: 1.5;
    font-size: 14px;
  }

  .ov-macro-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
  }

  .ov-macro {
    padding: 12px;
    border-radius: 17px;
    background: rgba(255,255,255,.045);
    border: 1px solid rgba(255,255,255,.075);
  }

  .ov-macro strong {
    display: block;
    font-size: 20px;
    margin-bottom: 4px;
  }

  .ov-macro span {
    color: rgba(234,240,255,.62);
    font-size: 12px;
  }

  .ov-progress {
    height: 9px;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(255,255,255,.08);
    margin-top: 12px;
  }

  .ov-progress span {
    display: block;
    height: 100%;
    width: var(--pct);
    min-width: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, #6D5EF3, #2EE1A5);
  }

  .ov-chart {
    height: 180px;
    display: flex;
    align-items: end;
    gap: 9px;
    padding: 12px 6px 0;
  }

  .ov-bar {
    min-width: 0;
    flex: 1;
    display: grid;
    gap: 8px;
    align-items: end;
    justify-items: center;
  }

  .ov-bar span:first-child {
    width: 100%;
    min-height: 8px;
    border-radius: 999px 999px 6px 6px;
    background: linear-gradient(180deg, #4CC9F0, #6D5EF3);
    box-shadow: 0 10px 22px rgba(76,201,240,.18);
  }

  .ov-bar span:last-child {
    color: rgba(234,240,255,.58);
    font-size: 11px;
    white-space: nowrap;
  }

  .ov-measure-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }

  .ov-measure {
    padding: 13px;
    border-radius: 17px;
    background: rgba(255,255,255,.045);
    border: 1px solid rgba(255,255,255,.075);
  }

  .ov-measure span {
    display: block;
    color: rgba(234,240,255,.6);
    font-size: 12px;
    margin-bottom: 6px;
  }

  .ov-measure strong {
    display: block;
    font-size: 20px;
  }

  .ov-error {
    padding: 18px;
    border-radius: 22px;
    background: rgba(255,111,137,.12);
    border: 1px solid rgba(255,111,137,.35);
    color: #FFDCE4;
  }

  @media (max-width: 1100px) {
    .ov-hero-inner {
      grid-template-columns: 1fr;
    }

    .ov-card-3,
    .ov-card-4,
    .ov-card-5,
    .ov-card-6,
    .ov-card-7,
    .ov-card-8 {
      grid-column: span 12;
    }
  }

  @media (max-width: 760px) {
    .ov-page {
      gap: 12px;
    }

    .ov-hero {
      padding: 18px;
      border-radius: 24px;
    }

    .ov-hero h1 {
      max-width: 11ch;
      font-size: clamp(32px, 11vw, 46px);
    }

    .ov-hero-text {
      font-size: 14px;
      line-height: 1.55;
    }

    .ov-hero-panel {
      padding: 12px;
      border-radius: 18px;
    }

    .ov-person-row {
      align-items: flex-start;
    }

    .ov-section-grid {
      gap: 10px;
    }

    .ov-card {
      grid-column: span 12;
      padding: 15px;
      border-radius: 21px;
    }

    .ov-card-head {
      margin-bottom: 12px;
    }

    .ov-ring-wrap {
      align-items: flex-start;
    }

    .ov-ring {
      width: 64px;
      height: 64px;
    }

    .ov-ring::before {
      width: 48px;
      height: 48px;
    }

    .ov-kpi {
      font-size: 30px;
    }

    .ov-list-item {
      padding: 12px;
    }

    .ov-list-item-meta {
      white-space: normal;
      text-align: left;
    }

    .ov-macro-grid,
    .ov-measure-grid {
      grid-template-columns: 1fr;
    }

    .ov-chart {
      height: 150px;
      gap: 6px;
    }
  }
</style>

<div class="ov-page">
  <?php if ($pageError): ?>
    <section class="ov-error">
      <strong>Overview non disponibile.</strong>
      <p style="margin:8px 0 0"><?= h($pageError) ?></p>
    </section>
  <?php else: ?>
    <section class="ov-hero">
      <div class="ov-hero-inner">
        <div>
          <div class="ov-eyebrow">Area cliente</div>
          <h1>Ciao, <?= h($displayName !== '' ? $displayName : $displayTitle) ?></h1>
          <p class="ov-hero-text">
            Qui trovi il riepilogo aggiornato del tuo percorso: allenamenti,
            piano alimentare, questionari, progressi e ultimi messaggi ricevuti.
          </p>

          <div class="ov-hero-actions">
            <a class="btn primary" href="allenamenti.php">Vai agli allenamenti</a>
            <a class="btn" href="nutrizione.php">Apri nutrizione</a>
            <a class="btn" href="progressi.php">Vedi progressi</a>
          </div>
        </div>

        <div class="ov-hero-panel">
          <div class="ov-person-row">
            <div>
              <strong><?= h($displayTitle) ?></strong>
              <span><?= h($displayEmail) ?></span>
            </div>
            <div class="ov-avatar" aria-hidden="true">
              <?= h(mb_strtoupper(mb_substr($displayName !== '' ? $displayName : $displayTitle, 0, 1, 'UTF-8'), 'UTF-8')) ?>
            </div>
          </div>

          <div class="ov-person-row">
            <div>
              <strong>Coach</strong>
              <span><?= h($coachName !== '' ? $coachName : 'Nessun PT associato') ?></span>
            </div>
            <span class="ov-tag <?= $coachName !== '' ? 'ok' : 'warn' ?>">
              <?= $coachName !== '' ? 'Attivo' : 'Assente' ?>
            </span>
          </div>

          <div class="ov-person-row">
            <div>
              <strong>Nutrizionista</strong>
              <span><?= h($nutrizionistaName !== '' ? $nutrizionistaName : 'Nessun nutrizionista associato') ?></span>
            </div>
            <span class="ov-tag <?= $nutrizionistaName !== '' ? 'ok' : 'warn' ?>">
              <?= $nutrizionistaName !== '' ? 'Attivo' : 'Assente' ?>
            </span>
          </div>
        </div>
      </div>
    </section>

    <section class="ov-section-grid" aria-label="Indicatori principali">
      <article class="ov-card ov-card-3">
        <div class="ov-card-head">
          <div>
            <h3>Settimana</h3>
            <p class="ov-card-subtitle">Sessioni registrate</p>
          </div>
          <span class="ov-tag"><?= $plannedDays > 0 ? h($plannedDays . ' previste') : 'Scheda assente' ?></span>
        </div>
        <div class="ov-ring-wrap">
          <div class="ov-ring" style="--pct: <?= $workoutPercent ?>%; --accent:#4CC9F0" data-label="<?= $workoutPercent ?>%"></div>
          <div>
            <span class="ov-kpi"><?= h($weekSessions) ?>/<?= h($plannedDays) ?></span>
            <span class="ov-kpi-small">Completamento allenamenti</span>
          </div>
        </div>
      </article>

      <article class="ov-card ov-card-3">
        <div class="ov-card-head">
          <div>
            <h3>Nutrizione</h3>
            <p class="ov-card-subtitle">Giorni tracciati negli ultimi 7</p>
          </div>
          <span class="ov-tag"><?= h($loggedNutritionDays) ?>/7</span>
        </div>
        <div class="ov-ring-wrap">
          <div class="ov-ring" style="--pct: <?= $nutritionPercent ?>%; --accent:#2EE1A5" data-label="<?= $nutritionPercent ?>%"></div>
          <div>
            <span class="ov-kpi"><?= h($nutritionPercent) ?>%</span>
            <span class="ov-kpi-small">Diario alimentare</span>
          </div>
        </div>
      </article>

      <article class="ov-card ov-card-3">
        <div class="ov-card-head">
          <div>
            <h3>Peso</h3>
            <p class="ov-card-subtitle">Ultima misurazione</p>
          </div>
          <?php if ($weightDelta !== null): ?>
            <span class="ov-tag <?= $weightDelta <= 0 ? 'ok' : 'warn' ?>">
              <?= h(($weightDelta > 0 ? '+' : '') . ov_num($weightDelta, 1)) ?> kg
            </span>
          <?php endif; ?>
        </div>
        <span class="ov-kpi">
          <?= $currentWeight !== null ? h(ov_num($currentWeight, 1)) . ' kg' : '—' ?>
        </span>
        <span class="ov-kpi-small">
          <?= isset($latestMeasurements['peso']) ? 'Aggiornato il ' . h(ov_date($latestMeasurements['peso']['misurataIl'] ?? null)) : 'Nessuna misurazione registrata' ?>
        </span>
      </article>

      <article class="ov-card ov-card-3">
        <div class="ov-card-head">
          <div>
            <h3>Questionari</h3>
            <p class="ov-card-subtitle">Assegnazioni attive</p>
          </div>
          <span class="ov-tag <?= $questionari['pending'] > 0 ? 'warn' : 'ok' ?>">
            <?= $questionari['pending'] > 0 ? 'Da compilare' : 'In ordine' ?>
          </span>
        </div>
        <span class="ov-kpi"><?= h($questionari['pending']) ?></span>
        <span class="ov-kpi-small">
          <?= h($questionari['completed']) ?> completati su <?= h($questionari['total']) ?>
        </span>
      </article>
    </section>

    <section class="ov-section-grid">
      <article class="ov-card ov-card-7">
        <div class="ov-card-head">
          <div>
            <h2>Scheda attiva</h2>
            <p class="ov-card-subtitle">
              <?= $activeProgram ? h($activeProgram['titolo']) : 'Nessun programma attivo' ?>
            </p>
          </div>
          <a class="ov-tag" href="allenamenti.php">Apri</a>
        </div>

        <?php if (!$activeProgram): ?>
          <div class="ov-empty">
            Non è presente una scheda di allenamento attiva collegata al tuo profilo.
          </div>
        <?php else: ?>
          <div class="ov-macro-grid" style="margin-top:0;margin-bottom:12px">
            <div class="ov-macro">
              <strong><?= h(ov_int($activeProgram['giorni'] ?? 0)) ?></strong>
              <span>Giorni</span>
            </div>
            <div class="ov-macro">
              <strong><?= h(ov_int($activeProgram['esercizi'] ?? 0)) ?></strong>
              <span>Esercizi</span>
            </div>
            <div class="ov-macro">
              <strong><?= h(ov_int($activeProgram['serie'] ?? 0)) ?></strong>
              <span>Serie</span>
            </div>
          </div>

          <div class="ov-list">
            <?php foreach ($workoutDays as $day): ?>
              <div class="ov-list-item">
                <div>
                  <strong><?= h($day['nome'] ?? '') ?></strong>
                  <span>
                    <?= h(ov_int($day['esercizi'] ?? 0)) ?> esercizi ·
                    <?= h(ov_int($day['serie'] ?? 0)) ?> serie
                  </span>
                  <?php if (!empty($day['eserciziNomi'])): ?>
                    <p><?= h(ov_short((string)$day['eserciziNomi'], 96)) ?></p>
                  <?php endif; ?>
                </div>
                <div class="ov-list-item-meta">#<?= h($day['ordine'] ?? '') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>

      <article class="ov-card ov-card-5">
        <div class="ov-card-head">
          <div>
            <h2>Diario di oggi</h2>
            <p class="ov-card-subtitle">Totali registrati nella giornata</p>
          </div>
          <a class="ov-tag" href="nutrizione.php">Dettagli</a>
        </div>

        <span class="ov-kpi"><?= h(ov_num($todayCalories, 0)) ?> kcal</span>
        <span class="ov-kpi-small">
          <?= h(ov_int($todayDiary['entries'] ?? 0)) ?> voci alimentari registrate
        </span>

        <?php if ($planCalories > 0): ?>
          <div class="ov-progress" aria-label="Avanzamento calorie">
            <span style="--pct: <?= $caloriePercent ?>%"></span>
          </div>
          <p class="ov-card-subtitle">
            Obiettivo piano: <?= h(ov_num($planCalories, 0)) ?> kcal
          </p>
        <?php endif; ?>

        <div class="ov-macro-grid">
          <div class="ov-macro">
            <strong><?= h(ov_num($todayDiary['proteine'], 0)) ?>g</strong>
            <span>Proteine</span>
          </div>
          <div class="ov-macro">
            <strong><?= h(ov_num($todayDiary['carbo'], 0)) ?>g</strong>
            <span>Carboidrati</span>
          </div>
          <div class="ov-macro">
            <strong><?= h(ov_num($todayDiary['grassi'], 0)) ?>g</strong>
            <span>Grassi</span>
          </div>
        </div>
      </article>
    </section>

    <section class="ov-section-grid">
      <article class="ov-card ov-card-6">
        <div class="ov-card-head">
          <div>
            <h2>Piano alimentare</h2>
            <p class="ov-card-subtitle">
              <?= $activePlan ? h($activePlan['titolo']) : 'Nessun piano attivo' ?>
            </p>
          </div>
          <a class="ov-tag" href="nutrizione.php">Apri</a>
        </div>

        <?php if (!$activePlan): ?>
          <div class="ov-empty">
            Non è presente un piano alimentare attivo assegnato al tuo profilo.
          </div>
        <?php else: ?>
          <div class="ov-macro-grid" style="margin-top:0;margin-bottom:12px">
            <div class="ov-macro">
              <strong><?= h(ov_num($activePlan['calorie'] ?? 0, 0)) ?></strong>
              <span>kcal piano</span>
            </div>
            <div class="ov-macro">
              <strong><?= h(ov_int($activePlan['pasti'] ?? 0)) ?></strong>
              <span>Pasti</span>
            </div>
            <div class="ov-macro">
              <strong><?= h(ov_int($activePlan['alimenti'] ?? 0)) ?></strong>
              <span>Alimenti</span>
            </div>
          </div>

          <div class="ov-list">
            <?php foreach ($planMeals as $meal): ?>
              <div class="ov-list-item">
                <div>
                  <strong><?= h($meal['nomePasto'] ?? '') ?></strong>
                  <span>
                    <?= h(ov_int($meal['alimenti'] ?? 0)) ?> alimenti ·
                    <?= h(ov_num($meal['calorie'] ?? 0, 0)) ?> kcal
                  </span>
                </div>
                <div class="ov-list-item-meta">#<?= h($meal['ordine'] ?? '') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>

      <article class="ov-card ov-card-6">
        <div class="ov-card-head">
          <div>
            <h2>Progressi</h2>
            <p class="ov-card-subtitle">Andamento peso e ultime misurazioni</p>
          </div>
          <a class="ov-tag" href="progressi.php">Apri</a>
        </div>

        <?php if (!$weightRows): ?>
          <div class="ov-empty">
            Non sono ancora presenti misurazioni del peso.
          </div>
        <?php else: ?>
          <div class="ov-chart" aria-label="Grafico peso">
            <?php foreach ($weightRows as $row): ?>
              <?php
                $value = ov_float($row['valore'] ?? 0);
                $height = 22 + (($value - $weightMin) / $weightRange) * 78;
              ?>
              <div class="ov-bar" title="<?= h(ov_num($value, 1)) ?> kg">
                <span style="height: <?= h((string)round($height)) ?>%"></span>
                <span><?= h((new DateTime($row['misurataIl']))->format('d/m')) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="ov-measure-grid">
          <div class="ov-measure">
            <span>Altezza</span>
            <strong>
              <?= isset($profile['altezzaCm']) && $profile['altezzaCm'] !== null ? h(ov_num($profile['altezzaCm'], 0)) . ' cm' : '—' ?>
            </strong>
          </div>

          <div class="ov-measure">
            <span>Vita</span>
            <strong>
              <?= $waist ? h(ov_num($waist['valore'] ?? null, 1)) . ' ' . h($waist['unita'] ?? 'cm') : '—' ?>
            </strong>
          </div>

          <div class="ov-measure">
            <span>Età</span>
            <strong>
              <?= isset($profile['eta']) && $profile['eta'] !== null ? h((string)$profile['eta']) . ' anni' : '—' ?>
            </strong>
          </div>

          <div class="ov-measure">
            <span>Livello attività</span>
            <strong><?= h($activityLevel !== '' ? $activityLevel : '—') ?></strong>
          </div>
        </div>
      </article>
    </section>

    <section class="ov-section-grid">
      <article class="ov-card ov-card-4">
        <div class="ov-card-head">
          <div>
            <h2>Ultime sessioni</h2>
            <p class="ov-card-subtitle">Allenamenti registrati</p>
          </div>
        </div>

        <?php if (!$recentSessions): ?>
          <div class="ov-empty">Nessuna sessione registrata.</div>
        <?php else: ?>
          <div class="ov-list">
            <?php foreach ($recentSessions as $session): ?>
              <div class="ov-list-item">
                <div>
                  <strong><?= h($session['giornoNome'] ?: $session['programmaTitolo'] ?: 'Sessione') ?></strong>
                  <span>
                    <?= h(ov_date($session['svoltaIl'] ?? null, true)) ?>
                    <?php if (!empty($session['durataMinuti'])): ?>
                      · <?= h($session['durataMinuti']) ?> min
                    <?php endif; ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>

      <article class="ov-card ov-card-4">
        <div class="ov-card-head">
          <div>
            <h2>Questionari</h2>
            <p class="ov-card-subtitle">Stato compilazioni</p>
          </div>
          <a class="ov-tag" href="questionari.php">Apri</a>
        </div>

        <?php if (!$latestQuestionari): ?>
          <div class="ov-empty">Nessun questionario assegnato.</div>
        <?php else: ?>
          <div class="ov-list">
            <?php foreach ($latestQuestionari as $item): ?>
              <?php $completed = !empty($item['inviatoIl']); ?>
              <div class="ov-list-item">
                <div>
                  <strong><?= h($item['titolo'] ?? '') ?></strong>
                  <span>
                    Assegnato il <?= h(ov_date($item['assegnatoIl'] ?? null)) ?>
                  </span>
                </div>
                <div class="ov-list-item-meta">
                  <span class="ov-tag <?= $completed ? 'ok' : 'warn' ?>">
                    <?= $completed ? 'Completato' : 'Da compilare' ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>

      <article class="ov-card ov-card-4">
        <div class="ov-card-head">
          <div>
            <h2>Messaggi</h2>
            <p class="ov-card-subtitle">Ultime comunicazioni</p>
          </div>
          <a class="ov-tag" href="supporto.php">Apri</a>
        </div>

        <?php if (!$latestMessages): ?>
          <div class="ov-empty">Nessun messaggio disponibile.</div>
        <?php else: ?>
          <div class="ov-list">
            <?php foreach ($latestMessages as $message): ?>
              <div class="ov-list-item">
                <div>
                  <strong><?= h($message['mittente'] ?: 'Messaggio') ?></strong>
                  <span><?= h(ov_short($message['contenuto'] ?? '', 92)) ?></span>
                </div>
                <div class="ov-list-item-meta">
                  <?= h(ov_date($message['creatoIl'] ?? null, true)) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>
    </section>
  <?php endif; ?>
</div>

<?php
renderEnd();
?>
