<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ob_start();

register_shutdown_function(static function (): void {
  $error = error_get_last();
  if (!$error || !in_array((int)$error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    return;
  }
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
  }
  while (ob_get_level() > 0) {
    ob_end_clean();
  }
  echo json_encode(['ok' => false, 'message' => 'Errore interno API Open Food Facts. Controlla i log PHP.'], JSON_UNESCAPED_UNICODE);
});

if (!file_exists(__DIR__ . '/../../config/database.php')) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'Config database.php mancante sul server.'], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . '/../../config/database.php';
if (!class_exists('Database')) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'Classe Database non disponibile.'], JSON_UNESCAPED_UNICODE);
  exit;
}
require_once __DIR__ . '/../lib/open_food_facts.php';

$user = $_SESSION['user'] ?? null;
if (!$user && isset($_SESSION['idUtente'])) {
  $user = [
    'idUtente' => (int)$_SESSION['idUtente'],
    'roles' => (array)($_SESSION['roles'] ?? []),
  ];
}

if (!$user || empty($user['idUtente'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'message' => 'Sessione non valida.']);
  exit;
}

function off_json_error(string $message, int $code = 422): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

function off_json_ok(array $payload = []): void {
  echo json_encode(array_merge(['ok' => true], $payload), JSON_UNESCAPED_UNICODE);
  exit;
}

function off_require_role(array $roles, string $target): void {
  foreach ($roles as $role) {
    if ($role === $target) {
      return;
    }
  }
  off_json_error('Permessi insufficienti.', 403);
}

$roles = array_map(static function ($r) {
  return strtolower((string)$r);
}, (array)($user['roles'] ?? []));
$action = trim((string)($_POST['action'] ?? ''));

try {
  if ($action === 'search') {
    $q = (string)($_POST['q'] ?? '');
    $page = (int)($_POST['page'] ?? 1);
    $pageSize = (int)($_POST['page_size'] ?? 12);
    $result = off_search_products($q, $page, $pageSize);
    off_json_ok($result);
  }

  if ($action === 'barcode_lookup') {
    $barcode = (string)($_POST['barcode'] ?? '');
    $product = off_lookup_barcode($barcode);
    if (!$product) {
      off_json_error('Prodotto non trovato.');
    }
    off_json_ok(['product' => $product]);
  }

  if ($action === 'calculate') {
    $barcode = (string)($_POST['barcode'] ?? '');
    $mode = (string)($_POST['mode'] ?? 'grams');
    $amount = (float)($_POST['amount'] ?? 0);
    $product = off_lookup_barcode($barcode);
    if (!$product) {
      off_json_error('Prodotto non trovato.');
    }
    $macros = off_calculate_macros($product, $mode, $amount);
    off_json_ok(['product' => $product, 'macros' => $macros]);
  }

  if ($action === 'add_diary_food') {
    off_require_role($roles, 'cliente');
    $barcode = (string)($_POST['barcode'] ?? '');
    $mode = (string)($_POST['mode'] ?? 'grams');
    $amount = (float)($_POST['amount'] ?? 0);
    $mealType = trim(strtolower((string)($_POST['meal_type'] ?? 'spuntini')));
    $entryTime = trim((string)($_POST['entry_time'] ?? date('H:i')));

    if (!in_array($mealType, ['colazione', 'pranzo', 'cena', 'spuntini'], true)) {
      off_json_error('Tipologia pasto non valida.');
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $entryTime)) {
      off_json_error('Orario non valido.');
    }

    $cliente = Database::exec('SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1', [(int)$user['idUtente']])->fetch();
    if (!$cliente) {
      off_json_error('Profilo cliente non trovato.', 404);
    }
    $clienteId = (int)$cliente['idCliente'];

    $product = off_lookup_barcode($barcode);
    if (!$product) {
      off_json_error('Prodotto non trovato.');
    }

    $macros = off_calculate_macros($product, $mode, $amount);

    $diaryCols = off_table_columns('VociDiarioAlimentare');
    $idCol = off_pick_column($diaryCols, ['idVoceDiario', 'idVoceDiarioAlimentare', 'idVoce']);
    $mealCol = off_pick_column($diaryCols, ['tipologiaPasto', 'tipoPasto', 'slotPasto', 'pasto']);
    $timeCol = off_pick_column($diaryCols, ['orario', 'oraPasto', 'orarioPasto']);
    $descCol = off_pick_column($diaryCols, ['descrizione', 'voce', 'nomeVoce', 'alimento']);
    $kcalCol = off_pick_column($diaryCols, ['calorieFinali', 'calorie', 'kcal']);
    $proCol = off_pick_column($diaryCols, ['proteineFinali', 'proteine']);
    $carbCol = off_pick_column($diaryCols, ['carboFinali', 'carboidratiFinali', 'carboidrati']);
    $fatCol = off_pick_column($diaryCols, ['grassiFinali', 'grassi']);
    $dateCol = off_pick_column($diaryCols, ['dataDiario', 'dataRiferimento', 'data', 'giorno']);
    $createdCol = off_pick_column($diaryCols, ['creatoIl', 'createdAt', 'inseritoIl']);

    if (!$idCol || !$mealCol || !$kcalCol || !$proCol || !$carbCol || !$fatCol) {
      off_json_error('Schema VociDiarioAlimentare incompleto.');
    }

    $today = date('Y-m-d');
    $params = [$clienteId, $mealType];
    $where = 'idCliente = ? AND ' . $mealCol . ' = ?';
    if ($dateCol) {
      $where .= ' AND ' . $dateCol . ' = ?';
      $params[] = $today;
    } elseif ($createdCol) {
      $where .= ' AND DATE(' . $createdCol . ') = ?';
      $params[] = $today;
    }

    $existing = Database::exec('SELECT ' . $idCol . ' AS idVoce FROM VociDiarioAlimentare WHERE ' . $where . ' LIMIT 1', $params)->fetch();
    if ($existing) {
      $voceId = (int)$existing['idVoce'];
    } else {
      $cols = ['idCliente', $mealCol, $kcalCol, $proCol, $carbCol, $fatCol];
      $vals = [$clienteId, $mealType, 0, 0, 0, 0];
      if ($timeCol) {
        $cols[] = $timeCol;
        $vals[] = $entryTime;
      }
      if ($descCol) {
        $cols[] = $descCol;
        $vals[] = 'Open Food Facts';
      }
      if ($dateCol) {
        $cols[] = $dateCol;
        $vals[] = $today;
      }
      if ($createdCol) {
        $cols[] = $createdCol;
        $vals[] = date('Y-m-d H:i:s');
      }
      Database::exec('INSERT INTO VociDiarioAlimentare (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', $vals);
      $voceId = (int)Database::pdo()->lastInsertId();
    }

    Database::exec(
      'INSERT INTO VociDiarioAlimentareAlimenti
      (voceDiario, ordine, fonteDati, offBarcode, nomeAlimento, marca, imageUrl, quantita, unita, numeroPorzioni, grammiTotali,
       offServingSize, offServingQuantityG, proteine, carboidrati, grassi, calorie, rawSnapshotJson, creatoIl, aggiornatoIl)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
      [
        $voceId,
        1,
        'openfoodfacts',
        $product['barcode'],
        $product['name'],
        $product['brand'] ?: null,
        $product['image_url'] ?: null,
        $amount,
        $mode === 'servings' ? 'porzioni' : 'g',
        $macros['numeroPorzioni'],
        $macros['grammiTotali'],
        $product['serving_size_label'] ?: null,
        $product['serving_quantity_g'],
        $macros['proteine'],
        $macros['carboidrati'],
        $macros['grassi'],
        $macros['calorie'],
        json_encode($product, JSON_UNESCAPED_UNICODE),
      ]
    );

    $totals = Database::exec(
      'SELECT COALESCE(SUM(proteine),0) AS proteine, COALESCE(SUM(carboidrati),0) AS carbo, COALESCE(SUM(grassi),0) AS grassi, COALESCE(SUM(calorie),0) AS calorie
       FROM VociDiarioAlimentareAlimenti
       WHERE voceDiario = ?',
      [$voceId]
    )->fetch();

    Database::exec(
      'UPDATE VociDiarioAlimentare SET ' . $proCol . ' = ?, ' . $carbCol . ' = ?, ' . $fatCol . ' = ?, ' . $kcalCol . ' = ? WHERE ' . $idCol . ' = ? LIMIT 1',
      [(float)$totals['proteine'], (float)$totals['carbo'], (float)$totals['grassi'], (float)$totals['calorie'], $voceId]
    );

    off_json_ok(['macros' => $macros]);
  }

  if ($action === 'add_plan_food') {
    off_require_role($roles, 'nutrizionista');

    $mealId = (int)($_POST['meal_id'] ?? 0);
    $barcode = (string)($_POST['barcode'] ?? '');
    $mode = (string)($_POST['mode'] ?? 'grams');
    $amount = (float)($_POST['amount'] ?? 0);

    if ($mealId <= 0) {
      off_json_error('Pasto non valido.');
    }

    $professionista = Database::exec('SELECT idProfessionista FROM Professionisti WHERE idUtente = ? LIMIT 1', [(int)$user['idUtente']])->fetch();
    if (!$professionista) {
      off_json_error('Profilo nutrizionista non trovato.', 404);
    }

    $meal = Database::exec(
      "SELECT pp.idPastoPiano, p.idPianoAlim
       FROM PastiPiano pp
       INNER JOIN PianiAlimentari p ON p.idPianoAlim = pp.pianoAlim
       WHERE pp.idPastoPiano = ?
         AND p.creatoreUtente = ?
         AND (
           p.cliente IS NULL OR EXISTS (
             SELECT 1 FROM Associazioni a
             WHERE a.cliente = p.cliente AND a.professionista = ? AND LOWER(a.tipoAssociazione) = 'nutrizionista' AND a.attivaFlag = 1
           )
         )
       LIMIT 1",
      [$mealId, (int)$user['idUtente'], (int)$professionista['idProfessionista']]
    )->fetch();

    if (!$meal) {
      off_json_error('Pasto non autorizzato.', 403);
    }

    $product = off_lookup_barcode($barcode);
    if (!$product) {
      off_json_error('Prodotto non trovato.');
    }

    $macros = off_calculate_macros($product, $mode, $amount);

    Database::exec(
      'INSERT INTO AlimentiPiano
      (pastoPiano, nomeAlimento, quantita, unita, proteine, carboidrati, grassi, calorie, fonteDati,
       offBarcode, offProductName, offBrand, offImageUrl, offServingSize, offServingQuantityG, grammiTotali, numeroPorzioni)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [
        $mealId,
        $product['name'],
        $amount,
        $mode === 'servings' ? 'porzioni' : 'g',
        $macros['proteine'],
        $macros['carboidrati'],
        $macros['grassi'],
        $macros['calorie'],
        'openfoodfacts',
        $product['barcode'],
        $product['name'],
        $product['brand'] ?: null,
        $product['image_url'] ?: null,
        $product['serving_size_label'] ?: null,
        $product['serving_quantity_g'],
        $macros['grammiTotali'],
        $macros['numeroPorzioni'],
      ]
    );

    Database::exec('UPDATE PianiAlimentari SET aggiornatoIl = NOW() WHERE idPianoAlim = ? LIMIT 1', [(int)$meal['idPianoAlim']]);

    off_json_ok(['macros' => $macros]);
  }

  off_json_error('Azione non supportata.', 400);
} catch (Throwable $e) {
  $message = (string)$e->getMessage();
  if (stripos($message, 'Errore Open Food Facts') !== false) {
    off_json_error(
      'Open Food Facts non raggiungibile dal server hosting (upstream 403/blocco rete). Usa inserimento manuale o cache locale.',
      503
    );
  }
  off_json_error($message, 500);
}
