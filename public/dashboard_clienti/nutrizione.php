<?php
require __DIR__ . '/common.php';

if (!function_exists('tableExistsClientNutrition')) {
  function tableExistsClientNutrition(string $table): bool {
    try {
      $row = Database::exec('SHOW TABLES LIKE ?', [$table])->fetch();
      return (bool)$row;
    } catch (Throwable $e) {
      return false;
    }
  }
}

if (!function_exists('safeFetchOneClientNutrition')) {
  function safeFetchOneClientNutrition(string $sql, array $params = []): ?array {
    try {
      $row = Database::exec($sql, $params)->fetch();
      return is_array($row) ? $row : null;
    } catch (Throwable $e) {
      return null;
    }
  }
}

if (!function_exists('safeFetchAllClientNutrition')) {
  function safeFetchAllClientNutrition(string $sql, array $params = []): array {
    try {
      $rows = Database::exec($sql, $params)->fetchAll();
      return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
      return [];
    }
  }
}

if (!function_exists('safeExecClientNutrition')) {
  function safeExecClientNutrition(string $sql, array $params = []): bool {
    try {
      Database::exec($sql, $params);
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }
}

if (!function_exists('fetchTableColumnsClientNutrition')) {
  function fetchTableColumnsClientNutrition(string $table): array {
    try {
      $rows = Database::exec('SHOW COLUMNS FROM ' . $table)->fetchAll();
      if (!is_array($rows)) {
        return [];
      }
      $cols = [];
      foreach ($rows as $row) {
        $field = (string)($row['Field'] ?? '');
        if ($field !== '') {
          $cols[] = $field;
        }
      }
      return $cols;
    } catch (Throwable $e) {
      return [];
    }
  }
}

if (!function_exists('pickFirstExistingColumn')) {
  function pickFirstExistingColumn(array $columns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
      if (in_array($candidate, $columns, true)) {
        return $candidate;
      }
    }
    return null;
  }
}

if (!function_exists('redirectNutritionPage')) {
  function redirectNutritionPage(): void {
    header('Location: nutrizione.php');
    exit;
  }
}

$flash = $_SESSION['nutrizione_flash'] ?? null;
unset($_SESSION['nutrizione_flash']);

$mealSlots = [
  'colazione' => ['label' => 'Colazione', 'emoji' => '🌅'],
  'pranzo' => ['label' => 'Pranzo', 'emoji' => '🍽️'],
  'cena' => ['label' => 'Cena', 'emoji' => '🌙'],
  'spuntini' => ['label' => 'Spuntini', 'emoji' => '🥜'],
];

$todayDate = new DateTimeImmutable('now');
$todaySql = $todayDate->format('Y-m-d');
$todayHuman = $todayDate->format('d/m/Y');
$todayLong = ucfirst((string)strftime('%A %d %B'));

$clienteId = 0;
$nutritionistName = $nutrizionistaAssegnato;
$nutritionistId = 0;
$assignedPlan = null;
$assignedPlanMeals = [];
$assignedPlanFoodsByMeal = [];
$diaryEntries = [];
$diaryBySlot = [
  'colazione' => [],
  'pranzo' => [],
  'cena' => [],
  'spuntini' => [],
];
$dbNotice = null;

$fallbackTargets = [
  'kcal' => 2100,
  'proteine' => 130,
  'carbo' => 230,
  'grassi' => 70,
];

$todayTotals = [
  'kcal' => 0.0,
  'proteine' => 0.0,
  'carbo' => 0.0,
  'grassi' => 0.0,
];

$planTotals = [
  'kcal' => 0.0,
  'proteine' => 0.0,
  'carbo' => 0.0,
  'grassi' => 0.0,
];

$diaryColumns = [];
$diaryMap = [
  'id' => null,
  'client' => 'idCliente',
  'mealType' => null,
  'time' => null,
  'description' => null,
  'kcal' => null,
  'proteine' => null,
  'carbo' => null,
  'grassi' => null,
  'date' => null,
  'created' => null,
];

if ($dbAvailable) {
  try {
    $clienteRow = safeFetchOneClientNutrition(
      'SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1',
      [(int)$user['idUtente']]
    );

    if ($clienteRow) {
      $clienteId = (int)$clienteRow['idCliente'];
    }

    if ($clienteId > 0) {
      $nutritionistRow = null;
      if (tableExistsClientNutrition('Associazioni') && tableExistsClientNutrition('Professionisti')) {
        $nutritionistRow = safeFetchOneClientNutrition(
          "SELECT p.idProfessionista, u.nome, u.cognome
           FROM Associazioni a
           INNER JOIN Professionisti p ON p.idProfessionista = a.professionista
           INNER JOIN Utenti u ON u.idUtente = p.idUtente
           WHERE a.cliente = ?
             AND LOWER(a.tipoAssociazione) = 'nutrizionista'
             AND a.attivaFlag = 1
           ORDER BY a.iniziataIl DESC
           LIMIT 1",
          [$clienteId]
        );
      }

      if ($nutritionistRow) {
        $nutritionistId = (int)$nutritionistRow['idProfessionista'];
        $nutritionistName = trim((string)$nutritionistRow['nome'] . ' ' . (string)$nutritionistRow['cognome']);
      }

      if (tableExistsClientNutrition('PianiAlimentari')) {
        $assignedPlan = safeFetchOneClientNutrition(
          "SELECT p.idPianoAlim, p.titolo, p.note, p.versione, p.aggiornatoIl, p.creatoIl, p.cliente,
                  creator.nome AS nutrNome, creator.cognome AS nutrCognome
           FROM PianiAlimentari p
           LEFT JOIN Professionisti pr ON pr.idUtente = p.creatoreUtente
           LEFT JOIN Utenti creator ON creator.idUtente = pr.idUtente
           WHERE p.cliente = ?
           ORDER BY COALESCE(p.aggiornatoIl, p.creatoIl) DESC, p.idPianoAlim DESC
           LIMIT 1",
          [$clienteId]
        );
      }

      if ($assignedPlan) {
        $assignedPlanMeals = tableExistsClientNutrition('PastiPiano')
          ? safeFetchAllClientNutrition(
            'SELECT idPastoPiano, nomePasto, ordine, note
             FROM PastiPiano
             WHERE pianoAlim = ?
             ORDER BY ordine ASC, idPastoPiano ASC',
            [(int)$assignedPlan['idPianoAlim']]
          )
          : [];

        $foods = (tableExistsClientNutrition('AlimentiPiano') && tableExistsClientNutrition('PastiPiano'))
          ? safeFetchAllClientNutrition(
            'SELECT idAlimentoPiano, pastoPiano, nomeAlimento, quantita, unita, proteine, carboidrati, grassi, calorie
             FROM AlimentiPiano
             WHERE pastoPiano IN (
               SELECT idPastoPiano FROM PastiPiano WHERE pianoAlim = ?
             )
             ORDER BY idAlimentoPiano ASC',
            [(int)$assignedPlan['idPianoAlim']]
          )
          : [];

        foreach ($foods as $food) {
          $mealId = (int)$food['pastoPiano'];
          $assignedPlanFoodsByMeal[$mealId][] = $food;
          $planTotals['kcal'] += (float)($food['calorie'] ?? 0);
          $planTotals['proteine'] += (float)($food['proteine'] ?? 0);
          $planTotals['carbo'] += (float)($food['carboidrati'] ?? 0);
          $planTotals['grassi'] += (float)($food['grassi'] ?? 0);
        }
      }

      if (tableExistsClientNutrition('VociDiarioAlimentare')) {
        $diaryColumns = fetchTableColumnsClientNutrition('VociDiarioAlimentare');
        $diaryMap['id'] = pickFirstExistingColumn($diaryColumns, ['idVoceDiarioAlimentare', 'idVoceDiario', 'idVoce', 'id']);
        $diaryMap['mealType'] = pickFirstExistingColumn($diaryColumns, ['tipologiaPasto', 'tipoPasto', 'slotPasto', 'pasto', 'tipologia']);
        $diaryMap['time'] = pickFirstExistingColumn($diaryColumns, ['orario', 'oraPasto', 'orarioPasto']);
        $diaryMap['description'] = pickFirstExistingColumn($diaryColumns, ['descrizione', 'voce', 'nomeVoce', 'alimento']);
        $diaryMap['kcal'] = pickFirstExistingColumn($diaryColumns, ['calorieFinali', 'calorie', 'kcal']);
        $diaryMap['proteine'] = pickFirstExistingColumn($diaryColumns, ['proteineFinali', 'proteine']);
        $diaryMap['carbo'] = pickFirstExistingColumn($diaryColumns, ['carboFinali', 'carboidratiFinali', 'carboidrati']);
        $diaryMap['grassi'] = pickFirstExistingColumn($diaryColumns, ['grassiFinali', 'grassi']);
        $diaryMap['date'] = pickFirstExistingColumn($diaryColumns, ['dataDiario', 'dataRiferimento', 'data', 'giorno']);
        $diaryMap['created'] = pickFirstExistingColumn($diaryColumns, ['creatoIl', 'createdAt', 'inseritoIl']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $clienteId > 0) {
          $action = (string)($_POST['action'] ?? '');

          if ($action === 'add_diary_entry') {
            $mealTypeInput = strtolower(trim((string)($_POST['meal_type'] ?? '')));
            $timeInput = trim((string)($_POST['entry_time'] ?? ''));
            $kcalInput = (float)($_POST['entry_kcal'] ?? 0);
            $descInput = trim((string)($_POST['entry_description'] ?? ''));
            $proteineInput = (float)($_POST['entry_proteine'] ?? 0);
            $carboInput = (float)($_POST['entry_carbo'] ?? 0);
            $grassiInput = (float)($_POST['entry_grassi'] ?? 0);

            if (!isset($mealSlots[$mealTypeInput])) {
              $_SESSION['nutrizione_flash'] = ['type' => 'error', 'message' => 'Tipologia pasto non valida.'];
              redirectNutritionPage();
            }

            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $timeInput)) {
              $_SESSION['nutrizione_flash'] = ['type' => 'error', 'message' => 'Orario non valido (formato HH:MM).'];
              redirectNutritionPage();
            }

            if ($kcalInput <= 0 || $kcalInput > 5000) {
              $_SESSION['nutrizione_flash'] = ['type' => 'error', 'message' => 'Le calorie devono essere comprese tra 1 e 5000.'];
              redirectNutritionPage();
            }

            if ($descInput === '' || mb_strlen($descInput) > 140) {
              $_SESSION['nutrizione_flash'] = ['type' => 'error', 'message' => 'Descrizione obbligatoria (max 140 caratteri).'];
              redirectNutritionPage();
            }

            $insertCols = ['idCliente'];
            $insertValues = [$clienteId];

            if ($diaryMap['mealType']) {
              $insertCols[] = $diaryMap['mealType'];
              $insertValues[] = $mealTypeInput;
            }
            if ($diaryMap['time']) {
              $insertCols[] = $diaryMap['time'];
              $insertValues[] = $timeInput;
            }
            if ($diaryMap['description']) {
              $insertCols[] = $diaryMap['description'];
              $insertValues[] = $descInput;
            }
            if ($diaryMap['kcal']) {
              $insertCols[] = $diaryMap['kcal'];
              $insertValues[] = $kcalInput;
            }
            if ($diaryMap['proteine']) {
              $insertCols[] = $diaryMap['proteine'];
              $insertValues[] = $proteineInput;
            }
            if ($diaryMap['carbo']) {
              $insertCols[] = $diaryMap['carbo'];
              $insertValues[] = $carboInput;
            }
            if ($diaryMap['grassi']) {
              $insertCols[] = $diaryMap['grassi'];
              $insertValues[] = $grassiInput;
            }
            if ($diaryMap['date']) {
              $insertCols[] = $diaryMap['date'];
              $insertValues[] = $todaySql;
            }
            if ($diaryMap['created']) {
              $insertCols[] = $diaryMap['created'];
              $insertValues[] = date('Y-m-d H:i:s');
            }

            if (count($insertCols) < 5) {
              $_SESSION['nutrizione_flash'] = ['type' => 'error', 'message' => 'Schema diario alimentare incompleto.'];
              redirectNutritionPage();
            }

            $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
            $inserted = safeExecClientNutrition(
              'INSERT INTO VociDiarioAlimentare (' . implode(',', $insertCols) . ') VALUES (' . $placeholders . ')',
              $insertValues
            );
            if (!$inserted) {
              $_SESSION['nutrizione_flash'] = ['type' => 'error', 'message' => 'Salvataggio non riuscito. Verifica lo schema del diario alimentare.'];
              redirectNutritionPage();
            }

            $_SESSION['nutrizione_flash'] = ['type' => 'ok', 'message' => 'Voce alimentare aggiunta con successo.'];
            redirectNutritionPage();
          }

          if ($action === 'delete_diary_entry') {
            $entryId = (int)($_POST['entry_id'] ?? 0);
            if ($entryId <= 0 || !$diaryMap['id']) {
              $_SESSION['nutrizione_flash'] = ['type' => 'error', 'message' => 'Voce non valida.'];
              redirectNutritionPage();
            }

            $owned = safeFetchOneClientNutrition(
              'SELECT ' . $diaryMap['id'] . ' AS id
               FROM VociDiarioAlimentare
               WHERE ' . $diaryMap['id'] . ' = ? AND idCliente = ?
               LIMIT 1',
              [$entryId, $clienteId]
            );

            if (!$owned) {
              $_SESSION['nutrizione_flash'] = ['type' => 'error', 'message' => 'Non puoi eliminare questa voce.'];
              redirectNutritionPage();
            }

            $deleted = safeExecClientNutrition(
              'DELETE FROM VociDiarioAlimentare
               WHERE ' . $diaryMap['id'] . ' = ? AND idCliente = ?
               LIMIT 1',
              [$entryId, $clienteId]
            );
            if (!$deleted) {
              $_SESSION['nutrizione_flash'] = ['type' => 'error', 'message' => 'Eliminazione non riuscita.'];
              redirectNutritionPage();
            }

            $_SESSION['nutrizione_flash'] = ['type' => 'ok', 'message' => 'Voce rimossa dal diario di oggi.'];
            redirectNutritionPage();
          }
        }

        $selectCols = ['idCliente'];
        if ($diaryMap['id']) { $selectCols[] = $diaryMap['id'] . ' AS entry_id'; }
        if ($diaryMap['mealType']) { $selectCols[] = $diaryMap['mealType'] . ' AS meal_type'; }
        if ($diaryMap['time']) { $selectCols[] = $diaryMap['time'] . ' AS entry_time'; }
        if ($diaryMap['description']) { $selectCols[] = $diaryMap['description'] . ' AS entry_description'; }
        if ($diaryMap['kcal']) { $selectCols[] = $diaryMap['kcal'] . ' AS entry_kcal'; }
        if ($diaryMap['proteine']) { $selectCols[] = $diaryMap['proteine'] . ' AS entry_proteine'; }
        if ($diaryMap['carbo']) { $selectCols[] = $diaryMap['carbo'] . ' AS entry_carbo'; }
        if ($diaryMap['grassi']) { $selectCols[] = $diaryMap['grassi'] . ' AS entry_grassi'; }
        if ($diaryMap['date']) { $selectCols[] = $diaryMap['date'] . ' AS entry_date'; }
        if ($diaryMap['created']) { $selectCols[] = $diaryMap['created'] . ' AS entry_created'; }

        $whereDateSql = '';
        $params = [$clienteId];
        if ($diaryMap['date']) {
          $whereDateSql = ' AND ' . $diaryMap['date'] . ' = ?';
          $params[] = $todaySql;
        } elseif ($diaryMap['created']) {
          $whereDateSql = ' AND DATE(' . $diaryMap['created'] . ') = ?';
          $params[] = $todaySql;
        }

        $diaryEntries = safeFetchAllClientNutrition(
          'SELECT ' . implode(',', $selectCols) . '
           FROM VociDiarioAlimentare
           WHERE idCliente = ?' . $whereDateSql . '
           ORDER BY COALESCE(' . ($diaryMap['time'] ?? "'00:00'") . ', "00:00") ASC, ' . ($diaryMap['id'] ?? 'idCliente') . ' ASC',
          $params
        );

        foreach ($diaryEntries as $entry) {
          $slot = strtolower((string)($entry['meal_type'] ?? 'spuntini'));
          if (!isset($mealSlots[$slot])) {
            $slot = 'spuntini';
          }

          $kcal = (float)($entry['entry_kcal'] ?? 0);
          $pro = (float)($entry['entry_proteine'] ?? 0);
          $carb = (float)($entry['entry_carbo'] ?? 0);
          $fat = (float)($entry['entry_grassi'] ?? 0);

          $todayTotals['kcal'] += $kcal;
          $todayTotals['proteine'] += $pro;
          $todayTotals['carbo'] += $carb;
          $todayTotals['grassi'] += $fat;

          $diaryBySlot[$slot][] = $entry;
        }
      } else {
        $dbNotice = 'Tabella VociDiarioAlimentare non disponibile nel database corrente.';
      }
    }
  } catch (Throwable $e) {
    $dbNotice = null;
  }
} else {
  $dbNotice = $dbError ?? 'Database non disponibile.';
}

$kcalTarget = max(1200, (int)round($assignedPlan ? $planTotals['kcal'] : $fallbackTargets['kcal']));
$proteinTarget = max(50, (float)($assignedPlan ? $planTotals['proteine'] : $fallbackTargets['proteine']));
$carbTarget = max(80, (float)($assignedPlan ? $planTotals['carbo'] : $fallbackTargets['carbo']));
$fatTarget = max(25, (float)($assignedPlan ? $planTotals['grassi'] : $fallbackTargets['grassi']));

$kcalConsumed = (int)round($todayTotals['kcal']);
$kcalLeft = max(0, $kcalTarget - $kcalConsumed);
$kcalProgress = $kcalTarget > 0 ? min(100, (int)round(($kcalConsumed / $kcalTarget) * 100)) : 0;

$macroProgress = [
  'proteine' => $proteinTarget > 0 ? min(100, (int)round(($todayTotals['proteine'] / $proteinTarget) * 100)) : 0,
  'carbo' => $carbTarget > 0 ? min(100, (int)round(($todayTotals['carbo'] / $carbTarget) * 100)) : 0,
  'grassi' => $fatTarget > 0 ? min(100, (int)round(($todayTotals['grassi'] / $fatTarget) * 100)) : 0,
];

$kcalBySlot = [
  'colazione' => 0,
  'pranzo' => 0,
  'cena' => 0,
  'spuntini' => 0,
];
foreach ($diaryBySlot as $slot => $entries) {
  foreach ($entries as $entry) {
    $kcalBySlot[$slot] += (int)round((float)($entry['entry_kcal'] ?? 0));
  }
}

renderStart('Nutrizione cliente', 'nutrizione', $email);
?>
<style>
.nutrizione-v2{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px}
.nutrizione-main{display:grid;gap:16px}
.nutrizione-side{display:grid;gap:14px;height:fit-content;position:sticky;top:96px}
.n-card{background:linear-gradient(145deg,rgba(19,27,46,.86),rgba(10,15,27,.9));border:1px solid rgba(142,162,255,.2);border-radius:22px;padding:18px;box-shadow:0 18px 45px rgba(2,8,20,.45),inset 0 1px 0 rgba(255,255,255,.06)}
.n-hero{padding:20px 22px;background:radial-gradient(130% 180% at 10% 10%,rgba(72,95,255,.35),rgba(22,29,48,.96) 45%),linear-gradient(160deg,rgba(43,201,255,.14),rgba(10,14,24,.95));}
.n-badge{display:inline-flex;padding:7px 11px;border:1px solid rgba(151,198,255,.33);border-radius:999px;font-size:12px;color:#d8e9ff;background:rgba(33,55,99,.4)}
.n-hero h1{margin:10px 0 4px;font-size:clamp(30px,6vw,42px)}
.n-hero p{margin:0;color:rgba(232,239,255,.82)}
.n-date-pill{display:inline-flex;margin-top:12px;padding:7px 12px;border-radius:999px;background:rgba(111,147,255,.16);border:1px solid rgba(111,147,255,.42);font-size:12px;color:#cde2ff}
.cal-grid{display:grid;grid-template-columns:220px minmax(0,1fr);gap:18px;align-items:center}
.ring-wrap{position:relative;width:180px;height:180px;margin:0 auto}
.ring-wrap svg{width:180px;height:180px;transform:rotate(-90deg)}
.ring-bg,.ring-progress{fill:none;stroke-width:10}
.ring-bg{stroke:rgba(175,188,255,.18)}
.ring-progress{stroke:url(#kcalGradient);stroke-linecap:round;stroke-dasharray:471;stroke-dashoffset:calc(471 - (471 * var(--progress))/100);transition:.45s ease}
.ring-center{position:absolute;inset:0;display:grid;place-items:center;text-align:center}
.ring-center strong{font-size:34px;line-height:1}
.ring-center span{font-size:12px;color:rgba(232,239,255,.72)}
.stat-line{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.stat-chip{padding:12px;border-radius:14px;background:rgba(11,18,34,.66);border:1px solid rgba(164,189,255,.2)}
.stat-chip b{display:block;font-size:22px}
.stat-chip small{color:rgba(230,238,255,.75)}
.macro-stack{display:grid;gap:10px;margin-top:12px}
.macro-item{display:grid;gap:5px}
.macro-label{display:flex;justify-content:space-between;font-size:12px;color:rgba(235,242,255,.86)}
.macro-bar{height:8px;border-radius:999px;background:rgba(174,189,227,.17);overflow:hidden}
.macro-fill{height:100%;border-radius:inherit;background:linear-gradient(90deg,#6d5ef3,#4cc9f0)}
.meal-quick-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.meal-quick{padding:14px;border-radius:16px;border:1px solid rgba(158,180,255,.22);background:rgba(12,20,37,.8);display:flex;align-items:center;justify-content:space-between;gap:8px}
.meal-title{font-size:14px;font-weight:700}
.meal-kcal{font-size:12px;color:rgba(235,242,255,.74)}
.add-btn{width:34px;height:34px;border-radius:50%;border:1px solid rgba(153,194,255,.52);background:linear-gradient(135deg,#5972ff,#3dd4ff);color:#061025;font-size:21px;line-height:1;cursor:pointer}
.section-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px}
.section-head h3{margin:0}
.plan-card{display:grid;gap:12px}
.meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.meta-item{padding:10px;border-radius:12px;background:rgba(9,15,27,.62);border:1px solid rgba(152,175,250,.18);font-size:13px}
.meal-plan-grid{display:grid;gap:10px}
.plan-meal-card{padding:13px;border-radius:14px;background:rgba(8,14,26,.68);border:1px solid rgba(138,163,231,.22)}
.food-list{margin:8px 0 0;padding:0;list-style:none;display:grid;gap:6px}
.food-list li{display:flex;justify-content:space-between;gap:8px;color:rgba(232,238,253,.85);font-size:13px}
.empty-state{text-align:center;padding:24px 14px;color:rgba(232,238,253,.75)}
.empty-state .icon{font-size:30px}
.diary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.diary-slot{padding:12px;border-radius:16px;background:rgba(12,18,31,.8);border:1px solid rgba(144,169,233,.23);display:grid;gap:8px}
.entry-item{padding:10px;border-radius:12px;background:rgba(6,11,21,.68);border:1px solid rgba(132,157,219,.22);display:grid;gap:6px}
.entry-top{display:flex;justify-content:space-between;align-items:center;gap:8px}
.entry-time{font-size:12px;color:rgba(221,230,255,.65)}
.entry-kcal{font-weight:700;color:#b8d5ff}
.entry-desc{font-size:13px}
.entry-delete{border:1px solid rgba(255,138,162,.45);color:#ff9eb3;background:rgba(255,78,116,.08);padding:5px 8px;border-radius:10px;font-size:11px;cursor:pointer}
.kpi-list{display:grid;gap:8px}.kpi-row{display:flex;justify-content:space-between;color:rgba(230,238,255,.82);font-size:14px}
.cta-col{display:grid;gap:8px}.cta-btn{display:inline-flex;justify-content:center;align-items:center;padding:10px 14px;border-radius:12px;text-decoration:none;color:#021328;font-weight:700;border:none;cursor:pointer;background:linear-gradient(135deg,#64c8ff,#8f6cff)}
.cta-btn.secondary{color:#dbe8ff;background:rgba(78,103,166,.23);border:1px solid rgba(149,176,242,.3)}
.flash{padding:11px 13px;border-radius:12px;font-size:14px;margin-bottom:4px}
.flash.ok{background:rgba(44,224,166,.14);border:1px solid rgba(44,224,166,.4);color:#c8ffed}
.flash.error{background:rgba(255,102,140,.14);border:1px solid rgba(255,102,140,.38);color:#ffd4df}
.modal{position:fixed;inset:0;display:none;align-items:flex-end;justify-content:center;background:rgba(2,7,15,.7);padding:12px;z-index:90}
.modal.open{display:flex}.modal-panel{width:min(620px,100%);max-height:92vh;overflow:auto;background:linear-gradient(180deg,rgba(19,27,46,.98),rgba(11,16,29,.98));border:1px solid rgba(146,171,243,.35);border-radius:18px;padding:16px}
.modal-head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px}
.modal-close{border:1px solid rgba(158,180,255,.38);background:rgba(31,46,80,.45);color:#dce9ff;border-radius:10px;padding:6px 10px;cursor:pointer}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.form-grid .full{grid-column:1/-1}
.field span{font-size:12px;color:rgba(230,237,255,.75)}
.field input,.field select,.field textarea{margin-top:5px}
.off-search-row{display:flex;gap:8px;flex-wrap:wrap}
.off-search-row input{flex:1}
.off-results{display:grid;gap:8px;margin-top:10px}
.off-product-card{display:grid;grid-template-columns:52px minmax(0,1fr) auto;gap:8px;align-items:center;padding:8px;border:1px solid rgba(152,175,250,.2);border-radius:12px;background:rgba(9,15,27,.62)}
.off-product-card img{width:52px;height:52px;object-fit:cover;border-radius:10px;background:#0f1729}
.off-product-card .name{font-weight:700}
.off-meta{font-size:12px;color:rgba(230,237,255,.7)}
.off-preview{margin-top:10px;padding:10px;border:1px solid rgba(111,147,255,.42);border-radius:12px;background:rgba(24,38,66,.5)}
@media (max-width:1050px){.nutrizione-v2{grid-template-columns:1fr}.nutrizione-side{position:static}.diary-grid,.meal-quick-grid,.meta-grid,.cal-grid{grid-template-columns:1fr}.ring-wrap{width:162px;height:162px}.ring-wrap svg{width:162px;height:162px}}
</style>

<?php if ($flash): ?>
  <div class="flash <?= ($flash['type'] ?? '') === 'ok' ? 'ok' : 'error' ?>"><?= h((string)($flash['message'] ?? '')) ?></div>
<?php endif; ?>
<?php if ($dbNotice): ?>
  <div class="flash error"><?= h($dbNotice) ?></div>
<?php endif; ?>

<section class="nutrizione-v2">
  <div class="nutrizione-main">
    <article class="n-card n-hero">
      <span class="n-badge">Nutrizione</span>
      <h1>Oggi</h1>
      <p>Monitora calorie e macro in tempo reale, anche senza piano assegnato.</p>
      <span class="n-date-pill"><?= h($todayLong) ?> · <?= h($todayHuman) ?></span>
    </article>

    <article class="n-card">
      <div class="cal-grid">
        <div class="ring-wrap" style="--progress:<?= (int)$kcalProgress ?>">
          <svg viewBox="0 0 180 180" aria-hidden="true">
            <defs>
              <linearGradient id="kcalGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#7b7cff" />
                <stop offset="100%" stop-color="#43d7ff" />
              </linearGradient>
            </defs>
            <circle class="ring-bg" cx="90" cy="90" r="75"></circle>
            <circle class="ring-progress" cx="90" cy="90" r="75"></circle>
          </svg>
          <div class="ring-center">
            <div>
              <strong><?= (int)$kcalLeft ?></strong>
              <span>kcal rimanenti</span>
            </div>
          </div>
        </div>
        <div>
          <div class="stat-line">
            <div class="stat-chip"><small>Consumate</small><b><?= (int)$kcalConsumed ?></b></div>
            <div class="stat-chip"><small>Target</small><b><?= (int)$kcalTarget ?></b></div>
            <div class="stat-chip"><small>Progresso</small><b><?= (int)$kcalProgress ?>%</b></div>
          </div>
          <div class="macro-stack">
            <div class="macro-item">
              <div class="macro-label"><span>Carboidrati</span><span><?= (int)$todayTotals['carbo'] ?>/<?= (int)$carbTarget ?> g</span></div>
              <div class="macro-bar"><div class="macro-fill" style="width:<?= (int)$macroProgress['carbo'] ?>%"></div></div>
            </div>
            <div class="macro-item">
              <div class="macro-label"><span>Proteine</span><span><?= (int)$todayTotals['proteine'] ?>/<?= (int)$proteinTarget ?> g</span></div>
              <div class="macro-bar"><div class="macro-fill" style="width:<?= (int)$macroProgress['proteine'] ?>%"></div></div>
            </div>
            <div class="macro-item">
              <div class="macro-label"><span>Grassi</span><span><?= (int)$todayTotals['grassi'] ?>/<?= (int)$fatTarget ?> g</span></div>
              <div class="macro-bar"><div class="macro-fill" style="width:<?= (int)$macroProgress['grassi'] ?>%"></div></div>
            </div>
          </div>
        </div>
      </div>
    </article>

    <article class="n-card">
      <div class="section-head"><h3>Pasti rapidi</h3></div>
      <div class="meal-quick-grid">
        <?php foreach ($mealSlots as $slotKey => $slot): ?>
          <div class="meal-quick">
            <div>
              <div class="meal-title"><?= h($slot['emoji']) ?> <?= h($slot['label']) ?></div>
              <div class="meal-kcal"><?= (int)$kcalBySlot[$slotKey] ?> kcal oggi</div>
            </div>
            <button class="add-btn" type="button" data-open-add-modal data-slot="<?= h($slotKey) ?>" aria-label="Aggiungi <?= h($slot['label']) ?>">+</button>
          </div>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="n-card">
      <div class="section-head"><h3>Piano nutrizionale assegnato</h3></div>
      <?php if ($assignedPlan): ?>
        <div class="plan-card">
          <div>
            <h4 style="margin:0 0 6px;font-size:22px"><?= h((string)($assignedPlan['titolo'] ?: 'Piano alimentare')) ?></h4>
            <p style="margin:0;color:rgba(232,240,255,.76)"><?= h((string)($assignedPlan['note'] ?: 'Nessuna nota disponibile per questo piano.')) ?></p>
          </div>
          <div class="meta-grid">
            <div class="meta-item"><strong>Versione</strong><br>v<?= h((string)($assignedPlan['versione'] ?? '1')) ?></div>
            <div class="meta-item"><strong>Aggiornato</strong><br><?= h((string)date('d/m/Y', strtotime((string)($assignedPlan['aggiornatoIl'] ?? $assignedPlan['creatoIl'] ?? 'now')))) ?></div>
            <div class="meta-item"><strong>Nutrizionista</strong><br><?= h($nutritionistName ?: trim((string)($assignedPlan['nutrNome'] ?? '') . ' ' . (string)($assignedPlan['nutrCognome'] ?? ''))) ?></div>
            <div class="meta-item"><strong>Target kcal</strong><br><?= (int)$kcalTarget ?> kcal</div>
          </div>
          <div class="meal-plan-grid">
            <?php foreach ($assignedPlanMeals as $meal):
              $mealFoods = $assignedPlanFoodsByMeal[(int)$meal['idPastoPiano']] ?? [];
              $mealKcal = 0.0; $mealPro = 0.0; $mealCarb = 0.0; $mealFat = 0.0;
              foreach ($mealFoods as $food) {
                $mealKcal += (float)($food['calorie'] ?? 0);
                $mealPro += (float)($food['proteine'] ?? 0);
                $mealCarb += (float)($food['carboidrati'] ?? 0);
                $mealFat += (float)($food['grassi'] ?? 0);
              }
            ?>
              <div class="plan-meal-card">
                <div class="section-head" style="margin:0 0 4px">
                  <strong><?= h((string)$meal['nomePasto']) ?></strong>
                  <span style="font-size:12px;color:rgba(231,238,255,.72)"><?= (int)$mealKcal ?> kcal</span>
                </div>
                <div style="font-size:12px;color:rgba(230,238,255,.8)">P <?= (int)$mealPro ?>g · C <?= (int)$mealCarb ?>g · G <?= (int)$mealFat ?>g</div>
                <ul class="food-list">
                  <?php if (!$mealFoods): ?>
                    <li><span>Nessun alimento definito</span><span>—</span></li>
                  <?php else: ?>
                    <?php foreach ($mealFoods as $food): ?>
                      <li>
                        <span><?= h((string)$food['nomeAlimento']) ?></span>
                        <span><?= h((string)$food['quantita']) ?> <?= h((string)$food['unita']) ?></span>
                      </li>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </ul>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <div class="icon">🥗</div>
          <h4 style="margin:8px 0 6px">Nessun piano nutrizionale assegnato</h4>
          <p style="margin:0">Puoi comunque usare il conta calorie, registrare i pasti di oggi e tenere monitorati i macro.</p>
        </div>
      <?php endif; ?>
    </article>

    <article class="n-card" id="diario-oggi">
      <div class="section-head">
        <h3>Diario di oggi</h3>
        <button class="cta-btn secondary" type="button" data-open-diary-modal>Vedi diario completo</button>
      </div>
      <div class="diary-grid">
        <?php foreach ($mealSlots as $slotKey => $slot): ?>
          <div class="diary-slot">
            <div class="section-head" style="margin:0">
              <strong><?= h($slot['emoji']) ?> <?= h($slot['label']) ?></strong>
              <button class="add-btn" type="button" data-open-add-modal data-slot="<?= h($slotKey) ?>">+</button>
            </div>
            <?php if (!$diaryBySlot[$slotKey]): ?>
              <div style="font-size:12px;color:rgba(227,235,253,.62)">Nessuna voce registrata.</div>
            <?php else: ?>
              <?php foreach ($diaryBySlot[$slotKey] as $entry): ?>
                <div class="entry-item">
                  <div class="entry-top">
                    <span class="entry-time"><?= h((string)($entry['entry_time'] ?: '--:--')) ?></span>
                    <span class="entry-kcal"><?= (int)round((float)($entry['entry_kcal'] ?? 0)) ?> kcal</span>
                  </div>
                  <div class="entry-desc"><?= h((string)($entry['entry_description'] ?? 'Voce pasto')) ?></div>
                  <?php if (!empty($entry['entry_id'])): ?>
                    <form method="post" style="margin:0">
                      <input type="hidden" name="action" value="delete_diary_entry">
                      <input type="hidden" name="entry_id" value="<?= (int)$entry['entry_id'] ?>">
                      <button type="submit" class="entry-delete">Elimina</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </article>
  </div>

  <aside class="nutrizione-side">
    <article class="n-card">
      <div class="section-head"><h3>Focus del giorno</h3></div>
      <p style="margin:0;color:rgba(231,239,255,.75)">Punta al <?= max(20, (int)round($proteinTarget * 0.28)) ?>% del target proteico entro pranzo e mantieni una buona idratazione.</p>
    </article>

    <article class="n-card">
      <div class="section-head"><h3>Mini KPI</h3></div>
      <div class="kpi-list">
        <div class="kpi-row"><span>Calorie consumate</span><strong><?= (int)$kcalConsumed ?></strong></div>
        <div class="kpi-row"><span>Calorie rimanenti</span><strong><?= (int)$kcalLeft ?></strong></div>
        <div class="kpi-row"><span>Nutrizionista</span><strong><?= h($nutritionistName ?: 'Non assegnato') ?></strong></div>
      </div>
    </article>

    <article class="n-card">
      <div class="cta-col">
        <button type="button" class="cta-btn" data-open-add-modal data-slot="spuntini">Aggiungi pasto</button>
        <button type="button" class="cta-btn secondary" data-open-off-modal data-slot="spuntini">Aggiungi da Open Food Facts</button>
        <button type="button" class="cta-btn secondary" data-open-diary-modal>Vedi diario di oggi</button>
      </div>
    </article>
  </aside>
</section>

<div class="modal" data-modal-add>
  <div class="modal-panel">
    <div class="modal-head">
      <h3 style="margin:0">Aggiungi pasto</h3>
      <button type="button" class="modal-close" data-close-modal>Chiudi</button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add_diary_entry">
      <div class="form-grid">
        <label class="field"><span>Tipologia pasto</span>
          <select name="meal_type" data-field-slot required>
            <?php foreach ($mealSlots as $slotKey => $slot): ?>
              <option value="<?= h($slotKey) ?>"><?= h($slot['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field"><span>Orario</span><input name="entry_time" type="time" value="<?= h(date('H:i')) ?>" required></label>
        <label class="field"><span>Calorie</span><input name="entry_kcal" type="number" min="1" max="5000" step="1" required></label>
        <label class="field full"><span>Descrizione</span><textarea name="entry_description" rows="2" maxlength="140" placeholder="Es. 200g yogurt greco + 30g avena" required></textarea></label>
        <label class="field"><span>Proteine (g)</span><input name="entry_proteine" type="number" min="0" max="500" step="0.1" value="0"></label>
        <label class="field"><span>Carbo (g)</span><input name="entry_carbo" type="number" min="0" max="500" step="0.1" value="0"></label>
        <label class="field"><span>Grassi (g)</span><input name="entry_grassi" type="number" min="0" max="500" step="0.1" value="0"></label>
      </div>
      <div style="margin-top:12px;display:flex;justify-content:flex-end">
        <button type="button" class="cta-btn secondary" data-open-off-modal data-slot="spuntini" style="margin-right:8px">Open Food Facts</button>
        <button type="submit" class="cta-btn">Salva voce</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" data-modal-off>
  <div class="modal-panel">
    <div class="modal-head">
      <h3 style="margin:0">Aggiungi alimento da Open Food Facts</h3>
      <button type="button" class="modal-close" data-close-modal>Chiudi</button>
    </div>
    <div class="form-grid">
      <label class="field full"><span>Pasto</span>
        <select data-off-meal>
          <?php foreach ($mealSlots as $slotKey => $slot): ?>
            <option value="<?= h($slotKey) ?>"><?= h($slot['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field full"><span>Ricerca per nome</span>
        <div class="off-search-row">
          <input type="text" data-off-query placeholder="Es. yogurt greco">
          <button type="button" class="cta-btn secondary" data-off-search-btn>Cerca</button>
        </div>
      </label>
      <label class="field full"><span>Oppure barcode</span>
        <div class="off-search-row">
          <input type="text" data-off-barcode placeholder="Es. 8000500310427">
          <button type="button" class="cta-btn secondary" data-off-barcode-btn>Lookup</button>
        </div>
      </label>
    </div>
    <div class="off-results" data-off-results></div>
    <div class="off-preview" data-off-preview style="display:none">
      <div class="form-grid">
        <label class="field"><span>Modalità</span>
          <select data-off-mode>
            <option value="grams">Grammi</option>
            <option value="servings">Porzioni</option>
          </select>
        </label>
        <label class="field"><span>Quantità</span><input type="number" min="0.1" step="0.1" value="100" data-off-amount></label>
        <label class="field"><span>Orario</span><input type="time" value="<?= h(date('H:i')) ?>" data-off-time></label>
      </div>
      <div class="off-meta" data-off-macros>Seleziona quantità per vedere i macro.</div>
      <div style="margin-top:10px;display:flex;justify-content:flex-end">
        <button type="button" class="cta-btn" data-off-save>Salva nel diario</button>
      </div>
    </div>
  </div>
</div>

<div class="modal" data-modal-diary>
  <div class="modal-panel">
    <div class="modal-head">
      <h3 style="margin:0">Diario completo di oggi</h3>
      <button type="button" class="modal-close" data-close-modal>Chiudi</button>
    </div>
    <?php if (!$diaryEntries): ?>
      <div class="empty-state" style="padding:10px 0 16px">
        <div class="icon">🗒️</div>
        <p style="margin:8px 0 0">Ancora nessuna voce registrata per oggi.</p>
      </div>
    <?php else: ?>
      <div class="diary-grid">
        <?php foreach ($mealSlots as $slotKey => $slot): ?>
          <div class="diary-slot">
            <strong><?= h($slot['emoji']) ?> <?= h($slot['label']) ?></strong>
            <?php if (!$diaryBySlot[$slotKey]): ?>
              <div style="font-size:12px;color:rgba(227,235,253,.62)">Slot vuoto.</div>
            <?php else: ?>
              <?php foreach ($diaryBySlot[$slotKey] as $entry): ?>
                <div class="entry-item">
                  <div class="entry-top"><span class="entry-time"><?= h((string)($entry['entry_time'] ?: '--:--')) ?></span><span class="entry-kcal"><?= (int)round((float)($entry['entry_kcal'] ?? 0)) ?> kcal</span></div>
                  <div class="entry-desc"><?= h((string)($entry['entry_description'] ?? 'Voce pasto')) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  const body = document.body;
  const addModal = document.querySelector('[data-modal-add]');
  const diaryModal = document.querySelector('[data-modal-diary]');
  const offModal = document.querySelector('[data-modal-off]');
  const slotField = addModal ? addModal.querySelector('[data-field-slot]') : null;
  const offMeal = offModal ? offModal.querySelector('[data-off-meal]') : null;
  const offResults = offModal ? offModal.querySelector('[data-off-results]') : null;
  const offPreview = offModal ? offModal.querySelector('[data-off-preview]') : null;
  const offMacros = offModal ? offModal.querySelector('[data-off-macros]') : null;
  let selectedProduct = null;
  const OFF_API_URL = '/public/api/openfoodfacts.php';

  function openModal(modal){
    if(!modal) return;
    modal.classList.add('open');
    body.style.overflow = 'hidden';
  }

  function closeModal(modal){
    if(!modal) return;
    modal.classList.remove('open');
    if(!document.querySelector('.modal.open')){
      body.style.overflow = '';
    }
  }

  document.querySelectorAll('[data-open-add-modal]').forEach((btn)=>{
    btn.addEventListener('click', ()=>{
      const slot = btn.getAttribute('data-slot') || 'spuntini';
      if(slotField){ slotField.value = slot; }
      openModal(addModal);
    });
  });

  document.querySelectorAll('[data-open-diary-modal]').forEach((btn)=>{
    btn.addEventListener('click', ()=>openModal(diaryModal));
  });
  document.querySelectorAll('[data-open-off-modal]').forEach((btn)=>{
    btn.addEventListener('click', ()=>{
      const slot = btn.getAttribute('data-slot') || 'spuntini';
      if(offMeal){ offMeal.value = slot; }
      openModal(offModal);
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach((btn)=>{
    btn.addEventListener('click', ()=>closeModal(btn.closest('.modal')));
  });

  [addModal, diaryModal, offModal].forEach((modal)=>{
    if(!modal) return;
    modal.addEventListener('click', (event)=>{ if(event.target === modal){ closeModal(modal); } });
  });

  document.addEventListener('keydown', (event)=>{
    if(event.key === 'Escape'){
      closeModal(addModal);
      closeModal(diaryModal);
      closeModal(offModal);
    }
  });

  async function offApi(params){
    const res = await fetch(OFF_API_URL, { method:'POST', body: params, headers:{'Accept':'application/json'} });
    const raw = await res.text();
    let payload = null;
    if(raw.trim() !== ''){
      try { payload = JSON.parse(raw); } catch (e) {
        throw new Error('Risposta API non valida: ' + raw.slice(0, 180));
      }
    }
    if(!payload){
      throw new Error('Endpoint OFF vuoto (HTTP ' + res.status + ').');
    }
    if(!res.ok || !payload.ok){ throw new Error(payload.message || 'Errore API'); }
    return payload;
  }

  function renderResults(products){
    offResults.innerHTML = '';
    if(!products.length){ offResults.innerHTML = '<div class="off-meta">Nessun prodotto trovato.</div>'; return; }
    products.forEach((p)=>{
      const card = document.createElement('div');
      card.className = 'off-product-card';
      card.innerHTML = `<img src="${p.image_url || ''}" alt=""><div><div class="name">${p.name || 'Senza nome'}</div><div class="off-meta">${p.brand || 'Marca n/d'} · ${p.kcal_100g ?? 0} kcal/100g</div></div><button type="button" class="cta-btn secondary">Seleziona</button>`;
      card.querySelector('button').addEventListener('click', ()=>{ selectedProduct = p; offPreview.style.display='block'; recalcPreview(); });
      offResults.appendChild(card);
    });
  }

  async function recalcPreview(){
    if(!selectedProduct){ return; }
    const form = new FormData();
    form.append('action','calculate');
    form.append('barcode', selectedProduct.barcode);
    form.append('mode', offModal.querySelector('[data-off-mode]').value);
    form.append('amount', offModal.querySelector('[data-off-amount]').value);
    const payload = await offApi(form);
    const m = payload.macros;
    offMacros.textContent = `Kcal ${m.calorie} · P ${m.proteine}g · C ${m.carboidrati}g · G ${m.grassi}g · ${m.grammiTotali ?? '-'}g`;
  }

  offModal?.querySelector('[data-off-search-btn]')?.addEventListener('click', async ()=>{
    try{
      const q = offModal.querySelector('[data-off-query]').value.trim();
      if(q.length < 2){ return; }
      const form = new FormData(); form.append('action','search'); form.append('q', q);
      const payload = await offApi(form);
      renderResults(payload.products || []);
    } catch(error){ alert(error.message || 'Errore ricerca OFF'); }
  });

  offModal?.querySelector('[data-off-barcode-btn]')?.addEventListener('click', async ()=>{
    try{
      const barcode = offModal.querySelector('[data-off-barcode]').value.trim();
      if(!barcode){ return; }
      const form = new FormData(); form.append('action','barcode_lookup'); form.append('barcode', barcode);
      const payload = await offApi(form);
      renderResults(payload.product ? [payload.product] : []);
    } catch(error){ alert(error.message || 'Errore lookup barcode'); }
  });

  offModal?.querySelector('[data-off-mode]')?.addEventListener('change', recalcPreview);
  offModal?.querySelector('[data-off-amount]')?.addEventListener('input', recalcPreview);

  offModal?.querySelector('[data-off-save]')?.addEventListener('click', async ()=>{
    try{
      if(!selectedProduct){ return; }
      const form = new FormData();
      form.append('action','add_diary_food');
      form.append('barcode', selectedProduct.barcode);
      form.append('mode', offModal.querySelector('[data-off-mode]').value);
      form.append('amount', offModal.querySelector('[data-off-amount]').value);
      form.append('meal_type', offMeal.value);
      form.append('entry_time', offModal.querySelector('[data-off-time]').value);
      await offApi(form);
      window.location.reload();
    } catch(error){ alert(error.message || 'Errore salvataggio alimento'); }
  });
})();
</script>
<?php
renderEnd();
