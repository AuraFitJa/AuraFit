<?php
require __DIR__ . '/common.php';

/*
MIGRAZIONE SQL (eseguire una sola volta se non già presente)

CREATE TABLE IF NOT EXISTS PianiAlimentariCartelle (
  idCartella BIGINT AUTO_INCREMENT PRIMARY KEY,
  professionista BIGINT NOT NULL,
  nome VARCHAR(150) NOT NULL,
  ordine INT NOT NULL DEFAULT 1,
  creataIl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_piani_cartelle_professionista
    FOREIGN KEY (professionista) REFERENCES Professionisti(idProfessionista)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE PianiAlimentari
  ADD COLUMN cartellaId BIGINT NULL;

ALTER TABLE PianiAlimentari
  ADD CONSTRAINT fk_piani_alimentari_cartella
  FOREIGN KEY (cartellaId) REFERENCES PianiAlimentariCartelle(idCartella)
  ON DELETE SET NULL;

-- necessario per bozze senza cliente associato
ALTER TABLE PianiAlimentari
  MODIFY COLUMN cliente BIGINT NULL;
*/


function nutritionBasePath(): string {
  $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '/public/dashboard_professionisti/nutrizione.php');
  $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
  if ($dir === '' || $dir === '.') {
    return '/nutrizione.php';
  }
  return $dir . '/nutrizione.php';
}

function redirectNutrition(array $params = []): void {
  $query = http_build_query($params);
  header('Location: ' . nutritionBasePath() . ($query !== '' ? ('?' . $query) : ''));
  exit;
}

function isAjaxRequest(): bool {
  return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function completeNutritionAction(string $message, array $params = []): void {
  if (isAjaxRequest()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => true,
      'message' => $message,
      'redirect' => nutritionBasePath() . (($query = http_build_query($params)) !== '' ? ('?' . $query) : ''),
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  setFlash('ok', $message);
  redirectNutrition($params);
}

function setFlash(string $type, string $message): void {
  $_SESSION['nutrizione_flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
  $flash = $_SESSION['nutrizione_flash'] ?? null;
  unset($_SESSION['nutrizione_flash']);
  return is_array($flash) ? $flash : null;
}

function sanitizeFloat($value): float {
  $normalized = str_replace(',', '.', trim((string)$value));
  if ($normalized === '' || !is_numeric($normalized)) {
    return 0.0;
  }
  return (float)$normalized;
}

function canonicalMealKey(string $name): string {
  $name = mb_strtolower(trim($name));
  $name = str_replace(['à', 'á', 'â', 'ä'], 'a', $name);
  $name = str_replace(['è', 'é', 'ê', 'ë'], 'e', $name);
  $name = str_replace(['ì', 'í', 'î', 'ï'], 'i', $name);
  $name = str_replace(['ò', 'ó', 'ô', 'ö'], 'o', $name);
  $name = str_replace(['ù', 'ú', 'û', 'ü'], 'u', $name);
  $name = preg_replace('/\s+/', ' ', $name);

  if ($name === 'colazione') {
    return 'colazione';
  }
  if ($name === 'spuntino mattina' || $name === 'spuntino di mattina') {
    return 'spuntino_mattina';
  }
  if ($name === 'pranzo') {
    return 'pranzo';
  }
  if ($name === 'spuntino pomeriggio' || $name === 'spuntino del pomeriggio') {
    return 'spuntino_pomeriggio';
  }
  if ($name === 'cena') {
    return 'cena';
  }

  return '';
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!$isNutrizionista) {
  renderStart('Nutrizione', 'nutrizione', $email, $roleBadge, $isPt, $isNutrizionista);
  echo '<section class="card"><h2 class="section-title">Nutrizione</h2><p class="muted">Questa sezione è disponibile solo per professionisti con ruolo nutrizionista.</p></section>';
  renderEnd();
  exit;
}

renderStart('Nutrizione', 'nutrizione', $email, $roleBadge, $isPt, $isNutrizionista);

if (!$dbAvailable) {
  echo '<section class="card"><h2 class="section-title">Nutrizione</h2><p class="muted">Database non disponibile: ' . h((string)$dbError) . '</p></section>';
  renderEnd();
  exit;
}

$professionistaId = getProfessionistaId($userId);
if (!$professionistaId) {
  echo '<section class="card"><h2 class="section-title">Nutrizione</h2><p class="muted">Profilo nutrizionista non trovato.</p></section>';
  renderEnd();
  exit;
}

function tableExists(string $table): bool {
  static $cache = [];
  if (isset($cache[$table])) {
    return $cache[$table];
  }
  $row = Database::exec('SHOW TABLES LIKE ?', [$table])->fetch();
  $cache[$table] = (bool)$row;
  return $cache[$table];
}

function columnExists(string $table, string $column): bool {
  static $cache = [];
  $key = $table . '.' . $column;
  if (isset($cache[$key])) {
    return $cache[$key];
  }
  $row = Database::exec("SHOW COLUMNS FROM {$table} LIKE ?", [$column])->fetch();
  $cache[$key] = (bool)$row;
  return $cache[$key];
}

$cartelleEnabled = tableExists('PianiAlimentariCartelle') && columnExists('PianiAlimentari', 'cartellaId');
$assegnazioniEnabled = tableExists('AssegnazioniPianoAlimentare');

$clientRows = Database::exec(
  "SELECT DISTINCT c.idCliente, u.nome, u.cognome, u.email
   FROM Associazioni a
   INNER JOIN Clienti c ON c.idCliente = a.cliente
   INNER JOIN Utenti u ON u.idUtente = c.idUtente
   WHERE a.professionista = ?
     AND LOWER(a.tipoAssociazione) = 'nutrizionista'
     AND a.attivaFlag = 1
   ORDER BY u.cognome, u.nome",
  [$professionistaId]
)->fetchAll();

$clientiMap = [];
foreach ($clientRows as $client) {
  $clientiMap[(int)$client['idCliente']] = $client;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  try {
    if ($action === 'create_folder') {
      if (!$cartelleEnabled) {
        throw new RuntimeException('Cartelle nutrizione non disponibili: eseguire la migrazione SQL.');
      }
      $nome = trim((string)($_POST['nome'] ?? ''));
      if ($nome === '') {
        throw new RuntimeException('Il nome cartella è obbligatorio.');
      }
      $maxOrdine = Database::exec('SELECT COALESCE(MAX(ordine), 0) AS m FROM PianiAlimentariCartelle WHERE professionista = ?', [$professionistaId])->fetch();
      $ordine = ((int)($maxOrdine['m'] ?? 0)) + 1;
      Database::exec('INSERT INTO PianiAlimentariCartelle (professionista, nome, ordine, creataIl) VALUES (?, ?, ?, NOW())', [$professionistaId, $nome, $ordine]);
      completeNutritionAction('Cartella creata con successo.');
    }

    if ($action === 'rename_folder') {
      $folderId = (int)($_POST['folder_id'] ?? 0);
      $nome = trim((string)($_POST['nome'] ?? ''));
      if ($folderId <= 0 || $nome === '') {
        throw new RuntimeException('Dati cartella non validi.');
      }
      $owned = Database::exec('SELECT 1 FROM PianiAlimentariCartelle WHERE idCartella = ? AND professionista = ? LIMIT 1', [$folderId, $professionistaId])->fetch();
      if (!$owned) {
        throw new RuntimeException('Cartella non trovata.');
      }
      Database::exec('UPDATE PianiAlimentariCartelle SET nome = ? WHERE idCartella = ? AND professionista = ? LIMIT 1', [$nome, $folderId, $professionistaId]);
      completeNutritionAction('Cartella rinominata.');
    }

    if ($action === 'delete_folder') {
      $folderId = (int)($_POST['folder_id'] ?? 0);
      if ($folderId <= 0) {
        throw new RuntimeException('Cartella non valida.');
      }
      $owned = Database::exec('SELECT 1 FROM PianiAlimentariCartelle WHERE idCartella = ? AND professionista = ? LIMIT 1', [$folderId, $professionistaId])->fetch();
      if (!$owned) {
        throw new RuntimeException('Cartella non trovata.');
      }
      Database::exec('UPDATE PianiAlimentari SET cartellaId = NULL WHERE cartellaId = ?', [$folderId]);
      Database::exec('DELETE FROM PianiAlimentariCartelle WHERE idCartella = ? AND professionista = ? LIMIT 1', [$folderId, $professionistaId]);
      completeNutritionAction('Cartella eliminata.');
    }

    if ($action === 'create_plan') {
      $folderId = (int)($_POST['folder_id'] ?? 0);
      $clienteRaw = trim((string)($_POST['cliente'] ?? ''));
      $clienteId = $clienteRaw !== '' ? (int)$clienteRaw : 0;
      $clienteValue = $clienteId > 0 ? $clienteId : null;
      $titolo = trim((string)($_POST['titolo'] ?? ''));
      $stato = trim((string)($_POST['stato'] ?? 'bozza'));
      $note = trim((string)($_POST['note'] ?? ''));

      if ($folderId <= 0 || $titolo === '') {
        throw new RuntimeException('Compila i campi obbligatori del piano.');
      }
      if ($clienteValue !== null && !isset($clientiMap[$clienteId])) {
        throw new RuntimeException('Cliente non valido per questo nutrizionista.');
      }

      if ($clienteValue === null) {
        $stato = 'bozza';
      }

      $folder = Database::exec('SELECT idCartella FROM PianiAlimentariCartelle WHERE idCartella = ? AND professionista = ? LIMIT 1', [$folderId, $professionistaId])->fetch();
      if (!$folder) {
        throw new RuntimeException('Cartella non trovata.');
      }

      try {
        if ($clienteValue === null) {
          Database::exec(
            'INSERT INTO PianiAlimentari (creatoreUtente, stato, titolo, note, versione, pianoPrecedente, creatoIl, aggiornatoIl, cartellaId)
             VALUES (?, ?, ?, ?, 1, NULL, NOW(), NOW(), ?)',
            [$userId, $stato, $titolo, $note, $folderId]
          );
        } else {
          Database::exec(
            'INSERT INTO PianiAlimentari (cliente, creatoreUtente, stato, titolo, note, versione, pianoPrecedente, creatoIl, aggiornatoIl, cartellaId)
             VALUES (?, ?, ?, ?, ?, 1, NULL, NOW(), NOW(), ?)',
            [$clienteValue, $userId, $stato, $titolo, $note, $folderId]
          );
        }
      } catch (Throwable $createError) {
        if ($clienteValue === null) {
          throw new RuntimeException('Il database corrente richiede un cliente obbligatorio per il piano. Rendi nullable PianiAlimentari.cliente per supportare bozze senza cliente.');
        }
        throw $createError;
      }

      $newPlanId = (int)Database::pdo()->lastInsertId();
      completeNutritionAction('Piano alimentare creato.', ['cartella' => $folderId, 'piano' => $newPlanId]);
    }

    if ($action === 'update_plan') {
      $planId = (int)($_POST['plan_id'] ?? 0);
      $titolo = trim((string)($_POST['titolo'] ?? ''));
      $note = trim((string)($_POST['note'] ?? ''));
      if ($planId <= 0 || $titolo === '') {
        throw new RuntimeException('Titolo piano obbligatorio.');
      }
      $ownedPlan = Database::exec(
        "SELECT p.idPianoAlim, p.cartellaId
         FROM PianiAlimentari p
         WHERE p.idPianoAlim = ?
           AND p.creatoreUtente = ?
           AND (
             p.cliente IS NULL
             OR EXISTS (
               SELECT 1
               FROM Associazioni a
               WHERE a.cliente = p.cliente
                 AND a.professionista = ?
                 AND LOWER(a.tipoAssociazione) = 'nutrizionista'
                 AND a.attivaFlag = 1
             )
           )
         LIMIT 1",
        [$planId, $userId, $professionistaId]
      )->fetch();

      if (!$ownedPlan) {
        throw new RuntimeException('Piano non trovato o non modificabile.');
      }

      Database::exec('UPDATE PianiAlimentari SET titolo = ?, note = ?, aggiornatoIl = NOW() WHERE idPianoAlim = ? LIMIT 1', [$titolo, $note, $planId]);
      completeNutritionAction('Piano aggiornato.', ['cartella' => (int)$ownedPlan['cartellaId'], 'piano' => $planId]);
    }

    if ($action === 'create_meal_section') {
      $planId = (int)($_POST['plan_id'] ?? 0);
      $mealLabel = trim((string)($_POST['meal_label'] ?? ''));
      $mealOrder = max(1, (int)($_POST['meal_order'] ?? 1));
      if ($planId <= 0 || $mealLabel === '') {
        throw new RuntimeException('Sezione pasto non valida.');
      }

      $ownedPlan = Database::exec(
        "SELECT p.idPianoAlim, p.cartellaId
         FROM PianiAlimentari p
         WHERE p.idPianoAlim = ?
           AND p.creatoreUtente = ?
           AND (
             p.cliente IS NULL
             OR EXISTS (
               SELECT 1
               FROM Associazioni a
               WHERE a.cliente = p.cliente
                 AND a.professionista = ?
                 AND LOWER(a.tipoAssociazione) = 'nutrizionista'
                 AND a.attivaFlag = 1
             )
           )
         LIMIT 1",
        [$planId, $userId, $professionistaId]
      )->fetch();
      if (!$ownedPlan) {
        throw new RuntimeException('Piano non trovato o non modificabile.');
      }

      $mealKey = canonicalMealKey($mealLabel);
      if ($mealKey !== '') {
        $rows = Database::exec('SELECT idPastoPiano, nomePasto FROM PastiPiano WHERE pianoAlim = ?', [$planId])->fetchAll();
        foreach ($rows as $row) {
          if (canonicalMealKey((string)$row['nomePasto']) === $mealKey) {
            throw new RuntimeException('La sezione è già presente nel piano.');
          }
        }
      }

      Database::exec(
        'INSERT INTO PastiPiano (pianoAlim, nomePasto, ordine, note) VALUES (?, ?, ?, ?)',
        [$planId, $mealLabel, $mealOrder, '']
      );
      Database::exec('UPDATE PianiAlimentari SET aggiornatoIl = NOW() WHERE idPianoAlim = ? LIMIT 1', [$planId]);
      completeNutritionAction('Sezione pasto creata.', ['cartella' => (int)$ownedPlan['cartellaId'], 'piano' => $planId]);
    }

    if ($action === 'save_meal_notes') {
      $mealId = (int)($_POST['meal_id'] ?? 0);
      $note = trim((string)($_POST['meal_note'] ?? ''));
      if ($mealId <= 0) {
        throw new RuntimeException('Pasto non valido.');
      }

      $meal = Database::exec(
        "SELECT pp.idPastoPiano, p.idPianoAlim, p.cartellaId
         FROM PastiPiano pp
         INNER JOIN PianiAlimentari p ON p.idPianoAlim = pp.pianoAlim
         WHERE pp.idPastoPiano = ?
           AND p.creatoreUtente = ?
           AND (
             p.cliente IS NULL
             OR EXISTS (
               SELECT 1
               FROM Associazioni a
               WHERE a.cliente = p.cliente
                 AND a.professionista = ?
                 AND LOWER(a.tipoAssociazione) = 'nutrizionista'
                 AND a.attivaFlag = 1
             )
           )
         LIMIT 1",
        [$mealId, $userId, $professionistaId]
      )->fetch();
      if (!$meal) {
        throw new RuntimeException('Pasto non trovato.');
      }

      Database::exec('UPDATE PastiPiano SET note = ? WHERE idPastoPiano = ? LIMIT 1', [$note, $mealId]);
      Database::exec('UPDATE PianiAlimentari SET aggiornatoIl = NOW() WHERE idPianoAlim = ? LIMIT 1', [(int)$meal['idPianoAlim']]);
      completeNutritionAction('Pasto aggiornato.', ['cartella' => (int)$meal['cartellaId'], 'piano' => (int)$meal['idPianoAlim']]);
    }

    if ($action === 'delete_meal_section') {
      $mealId = (int)($_POST['meal_id'] ?? 0);
      if ($mealId <= 0) {
        throw new RuntimeException('Sezione pasto non valida.');
      }

      $meal = Database::exec(
        "SELECT pp.idPastoPiano, pp.nomePasto, p.idPianoAlim, p.cartellaId
         FROM PastiPiano pp
         INNER JOIN PianiAlimentari p ON p.idPianoAlim = pp.pianoAlim
         WHERE pp.idPastoPiano = ?
           AND p.creatoreUtente = ?
           AND (
             p.cliente IS NULL
             OR EXISTS (
               SELECT 1
               FROM Associazioni a
               WHERE a.cliente = p.cliente
                 AND a.professionista = ?
                 AND LOWER(a.tipoAssociazione) = 'nutrizionista'
                 AND a.attivaFlag = 1
             )
           )
         LIMIT 1",
        [$mealId, $userId, $professionistaId]
      )->fetch();
      if (!$meal) {
        throw new RuntimeException('Sezione pasto non trovata.');
      }

      if (canonicalMealKey((string)$meal['nomePasto']) !== '') {
        throw new RuntimeException('Non puoi rimuovere una sezione pasto base.');
      }

      Database::exec('DELETE FROM AlimentiPiano WHERE pastoPiano = ?', [$mealId]);
      Database::exec('DELETE FROM PastiPiano WHERE idPastoPiano = ? LIMIT 1', [$mealId]);
      Database::exec('UPDATE PianiAlimentari SET aggiornatoIl = NOW() WHERE idPianoAlim = ? LIMIT 1', [(int)$meal['idPianoAlim']]);
      completeNutritionAction('Spuntino extra rimosso.', ['cartella' => (int)$meal['cartellaId'], 'piano' => (int)$meal['idPianoAlim']]);
    }

    if ($action === 'add_food') {
      $mealId = (int)($_POST['meal_id'] ?? 0);
      $nomeAlimento = trim((string)($_POST['nomeAlimento'] ?? ''));
      $quantita = sanitizeFloat($_POST['quantita'] ?? null);
      $unita = trim((string)($_POST['unita'] ?? 'g'));
      $proteine = sanitizeFloat($_POST['proteine'] ?? null);
      $carboidrati = sanitizeFloat($_POST['carboidrati'] ?? null);
      $grassi = sanitizeFloat($_POST['grassi'] ?? null);
      $calorie = sanitizeFloat($_POST['calorie'] ?? null);
      if ($mealId <= 0 || $nomeAlimento === '') {
        throw new RuntimeException('Inserisci almeno il nome alimento.');
      }

      $meal = Database::exec(
        "SELECT pp.idPastoPiano, p.idPianoAlim, p.cartellaId
         FROM PastiPiano pp
         INNER JOIN PianiAlimentari p ON p.idPianoAlim = pp.pianoAlim
         WHERE pp.idPastoPiano = ?
           AND p.creatoreUtente = ?
           AND (
             p.cliente IS NULL
             OR EXISTS (
               SELECT 1
               FROM Associazioni a
               WHERE a.cliente = p.cliente
                 AND a.professionista = ?
                 AND LOWER(a.tipoAssociazione) = 'nutrizionista'
                 AND a.attivaFlag = 1
             )
           )
         LIMIT 1",
        [$mealId, $userId, $professionistaId]
      )->fetch();
      if (!$meal) {
        throw new RuntimeException('Pasto non valido.');
      }

      Database::exec(
        'INSERT INTO AlimentiPiano (pastoPiano, nomeAlimento, quantita, unita, proteine, carboidrati, grassi, calorie)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$mealId, $nomeAlimento, $quantita, $unita, $proteine, $carboidrati, $grassi, $calorie]
      );
      Database::exec('UPDATE PianiAlimentari SET aggiornatoIl = NOW() WHERE idPianoAlim = ? LIMIT 1', [(int)$meal['idPianoAlim']]);
      completeNutritionAction('Alimento aggiunto.', ['cartella' => (int)$meal['cartellaId'], 'piano' => (int)$meal['idPianoAlim']]);
    }

    if ($action === 'update_food') {
      $foodId = (int)($_POST['food_id'] ?? 0);
      $nomeAlimento = trim((string)($_POST['nomeAlimento'] ?? ''));
      $quantita = sanitizeFloat($_POST['quantita'] ?? null);
      $unita = trim((string)($_POST['unita'] ?? 'g'));
      $proteine = sanitizeFloat($_POST['proteine'] ?? null);
      $carboidrati = sanitizeFloat($_POST['carboidrati'] ?? null);
      $grassi = sanitizeFloat($_POST['grassi'] ?? null);
      $calorie = sanitizeFloat($_POST['calorie'] ?? null);
      if ($foodId <= 0 || $nomeAlimento === '') {
        throw new RuntimeException('Dati alimento non validi.');
      }

      $food = Database::exec(
        "SELECT ap.idAlimentoPiano, pp.idPastoPiano, p.idPianoAlim, p.cartellaId
         FROM AlimentiPiano ap
         INNER JOIN PastiPiano pp ON pp.idPastoPiano = ap.pastoPiano
         INNER JOIN PianiAlimentari p ON p.idPianoAlim = pp.pianoAlim
         WHERE ap.idAlimentoPiano = ?
           AND p.creatoreUtente = ?
           AND (
             p.cliente IS NULL
             OR EXISTS (
               SELECT 1
               FROM Associazioni a
               WHERE a.cliente = p.cliente
                 AND a.professionista = ?
                 AND LOWER(a.tipoAssociazione) = 'nutrizionista'
                 AND a.attivaFlag = 1
             )
           )
         LIMIT 1",
        [$foodId, $userId, $professionistaId]
      )->fetch();
      if (!$food) {
        throw new RuntimeException('Alimento non trovato.');
      }

      Database::exec(
        'UPDATE AlimentiPiano
         SET nomeAlimento = ?, quantita = ?, unita = ?, proteine = ?, carboidrati = ?, grassi = ?, calorie = ?
         WHERE idAlimentoPiano = ? LIMIT 1',
        [$nomeAlimento, $quantita, $unita, $proteine, $carboidrati, $grassi, $calorie, $foodId]
      );
      Database::exec('UPDATE PianiAlimentari SET aggiornatoIl = NOW() WHERE idPianoAlim = ? LIMIT 1', [(int)$food['idPianoAlim']]);
      completeNutritionAction('Alimento aggiornato.', ['cartella' => (int)$food['cartellaId'], 'piano' => (int)$food['idPianoAlim']]);
    }

    if ($action === 'delete_food') {
      $foodId = (int)($_POST['food_id'] ?? 0);
      if ($foodId <= 0) {
        throw new RuntimeException('Alimento non valido.');
      }

      $food = Database::exec(
        "SELECT ap.idAlimentoPiano, p.idPianoAlim, p.cartellaId
         FROM AlimentiPiano ap
         INNER JOIN PastiPiano pp ON pp.idPastoPiano = ap.pastoPiano
         INNER JOIN PianiAlimentari p ON p.idPianoAlim = pp.pianoAlim
         WHERE ap.idAlimentoPiano = ?
           AND p.creatoreUtente = ?
           AND (
             p.cliente IS NULL
             OR EXISTS (
               SELECT 1
               FROM Associazioni a
               WHERE a.cliente = p.cliente
                 AND a.professionista = ?
                 AND LOWER(a.tipoAssociazione) = 'nutrizionista'
                 AND a.attivaFlag = 1
             )
           )
         LIMIT 1",
        [$foodId, $userId, $professionistaId]
      )->fetch();
      if (!$food) {
        throw new RuntimeException('Alimento non trovato.');
      }

      Database::exec('DELETE FROM AlimentiPiano WHERE idAlimentoPiano = ? LIMIT 1', [$foodId]);
      Database::exec('UPDATE PianiAlimentari SET aggiornatoIl = NOW() WHERE idPianoAlim = ? LIMIT 1', [(int)$food['idPianoAlim']]);
      completeNutritionAction('Alimento rimosso.', ['cartella' => (int)$food['cartellaId'], 'piano' => (int)$food['idPianoAlim']]);
    }

    if ($action === 'duplicate_plan') {
      $planId = (int)($_POST['plan_id'] ?? 0);
      if ($planId <= 0) {
        throw new RuntimeException('Piano non valido.');
      }

      $pdo = Database::pdo();
      $pdo->beginTransaction();

      $original = Database::exec(
        "SELECT p.*
         FROM PianiAlimentari p
         WHERE p.idPianoAlim = ?
           AND p.creatoreUtente = ?
           AND (
             p.cliente IS NULL
             OR EXISTS (
               SELECT 1
               FROM Associazioni a
               WHERE a.cliente = p.cliente
                 AND a.professionista = ?
                 AND LOWER(a.tipoAssociazione) = 'nutrizionista'
                 AND a.attivaFlag = 1
             )
           )
         LIMIT 1",
        [$planId, $userId, $professionistaId]
      )->fetch();

      if (!$original) {
        throw new RuntimeException('Piano non trovato o non duplicabile.');
      }

      Database::exec(
        'INSERT INTO PianiAlimentari (cliente, creatoreUtente, stato, titolo, note, versione, pianoPrecedente, creatoIl, aggiornatoIl, cartellaId)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)',
        [
          (int)$original['cliente'],
          (int)$original['creatoreUtente'],
          (string)$original['stato'],
          trim((string)$original['titolo']) . ' - Copia',
          (string)$original['note'],
          (int)$original['versione'],
          (int)$original['idPianoAlim'],
          $original['cartellaId'] !== null ? (int)$original['cartellaId'] : null,
        ]
      );
      $newPlanId = (int)$pdo->lastInsertId();

      $pasti = Database::exec(
        'SELECT idPastoPiano, nomePasto, ordine, note FROM PastiPiano WHERE pianoAlim = ? ORDER BY ordine, idPastoPiano',
        [$planId]
      )->fetchAll();

      foreach ($pasti as $pasto) {
        Database::exec(
          'INSERT INTO PastiPiano (pianoAlim, nomePasto, ordine, note) VALUES (?, ?, ?, ?)',
          [$newPlanId, (string)$pasto['nomePasto'], (int)$pasto['ordine'], (string)$pasto['note']]
        );
        $newPastoId = (int)$pdo->lastInsertId();

        $alimenti = Database::exec(
          'SELECT nomeAlimento, quantita, unita, proteine, carboidrati, grassi, calorie FROM AlimentiPiano WHERE pastoPiano = ? ORDER BY idAlimentoPiano',
          [(int)$pasto['idPastoPiano']]
        )->fetchAll();

        foreach ($alimenti as $alimento) {
          Database::exec(
            'INSERT INTO AlimentiPiano (pastoPiano, nomeAlimento, quantita, unita, proteine, carboidrati, grassi, calorie)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
              $newPastoId,
              (string)$alimento['nomeAlimento'],
              $alimento['quantita'],
              (string)$alimento['unita'],
              $alimento['proteine'],
              $alimento['carboidrati'],
              $alimento['grassi'],
              $alimento['calorie'],
            ]
          );
        }
      }

      $pdo->commit();
      completeNutritionAction('Piano duplicato con successo.', ['cartella' => (int)$original['cartellaId'], 'piano' => $newPlanId]);
    }

    if ($action === 'delete_plan') {
      $planId = (int)($_POST['plan_id'] ?? 0);
      $requestedFolderId = (int)($_POST['folder_id'] ?? 0);
      if ($planId <= 0) {
        throw new RuntimeException('Piano non valido.');
      }

      $ownedPlan = Database::exec(
        "SELECT p.idPianoAlim, p.cartellaId
         FROM PianiAlimentari p
         WHERE p.idPianoAlim = ?
           AND p.creatoreUtente = ?
           AND (
             p.cliente IS NULL
             OR EXISTS (
               SELECT 1
               FROM Associazioni a
               WHERE a.cliente = p.cliente
                 AND a.professionista = ?
                 AND LOWER(a.tipoAssociazione) = 'nutrizionista'
                 AND a.attivaFlag = 1
             )
           )
         LIMIT 1",
        [$planId, $userId, $professionistaId]
      )->fetch();

      if (!$ownedPlan) {
        throw new RuntimeException('Piano non trovato o non eliminabile.');
      }

      Database::exec('DELETE FROM PianiAlimentari WHERE idPianoAlim = ? LIMIT 1', [$planId]);

      $targetFolderId = (int)$ownedPlan['cartellaId'];
      if ($targetFolderId <= 0 && $requestedFolderId > 0) {
        $targetFolderId = $requestedFolderId;
      }

      $redirectUrl = 'nutrizione.php' . ($targetFolderId > 0 ? ('?cartella=' . $targetFolderId) : '');
      if (isAjaxRequest()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
          'ok' => true,
          'message' => 'Piano eliminato.',
          'redirect' => $redirectUrl,
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }

      setFlash('ok', 'Piano eliminato.');
      header('Location: ' . $redirectUrl);
      exit;
    }

    if ($action === 'assign_plan') {
      if (!$assegnazioniEnabled) {
        throw new RuntimeException('Tabella AssegnazioniPianoAlimentare non disponibile.');
      }
      $planId = (int)($_POST['plan_id'] ?? 0);
      $clienteId = (int)($_POST['cliente'] ?? 0);
      if ($planId <= 0 || !isset($clientiMap[$clienteId])) {
        throw new RuntimeException('Dati assegnazione non validi.');
      }

      $plan = Database::exec(
        'SELECT p.idPianoAlim, p.cartellaId FROM PianiAlimentari p WHERE p.idPianoAlim = ? AND p.creatoreUtente = ? LIMIT 1',
        [$planId, $userId]
      )->fetch();

      if (!$plan) {
        throw new RuntimeException('Piano non assegnabile.');
      }

      $exists = Database::exec(
        'SELECT idAssegnazionePiano FROM AssegnazioniPianoAlimentare WHERE pianoAlim = ? AND cliente = ? LIMIT 1',
        [$planId, $clienteId]
      )->fetch();

      if ($exists) {
        Database::exec(
          "UPDATE AssegnazioniPianoAlimentare SET stato = 'attivo', assegnatoIl = NOW() WHERE idAssegnazionePiano = ? LIMIT 1",
          [(int)$exists['idAssegnazionePiano']]
        );
      } else {
        Database::exec(
          "INSERT INTO AssegnazioniPianoAlimentare (pianoAlim, cliente, assegnatoIl, stato) VALUES (?, ?, NOW(), 'attivo')",
          [$planId, $clienteId]
        );
      }

      completeNutritionAction('Piano assegnato al cliente.', ['cartella' => (int)$plan['cartellaId'], 'piano' => $planId]);
    }
  } catch (Throwable $e) {
    if (Database::pdo()->inTransaction()) {
      Database::pdo()->rollBack();
    }
    $redirect = [];
    if (!empty($_POST['folder_id'])) {
      $redirect['cartella'] = (int)$_POST['folder_id'];
    }
    if (!empty($_POST['plan_id'])) {
      $redirect['piano'] = (int)$_POST['plan_id'];
    }

    if (isAjaxRequest()) {
      header('Content-Type: application/json; charset=utf-8');
      http_response_code(422);
      echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
        'redirect' => nutritionBasePath() . (($query = http_build_query($redirect)) !== '' ? ('?' . $query) : ''),
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    setFlash('error', $e->getMessage());
    redirectNutrition($redirect);
  }
}

$flash = getFlash();
$cartellaAttivaId = (int)($_GET['cartella'] ?? 0);
$pianoAttivoId = (int)($_GET['piano'] ?? 0);

$folders = [];
if ($cartelleEnabled) {
  $folders = Database::exec(
    'SELECT c.idCartella, c.nome, c.ordine, COUNT(p.idPianoAlim) AS totalePiani
     FROM PianiAlimentariCartelle c
     LEFT JOIN PianiAlimentari p ON p.cartellaId = c.idCartella AND p.creatoreUtente = ?
     WHERE c.professionista = ?
     GROUP BY c.idCartella, c.nome, c.ordine
     ORDER BY c.ordine, c.nome',
    [$userId, $professionistaId]
  )->fetchAll();
}

$cartellaAttiva = null;
foreach ($folders as $folder) {
  if ((int)$folder['idCartella'] === $cartellaAttivaId) {
    $cartellaAttiva = $folder;
    break;
  }
}
if ($cartellaAttivaId > 0 && !$cartellaAttiva) {
  redirectNutrition();
}

$pianiCartella = [];
if ($cartellaAttiva) {
  $pianiCartella = Database::exec(
    "SELECT p.idPianoAlim, p.titolo, p.note, p.stato, p.versione, p.aggiornatoIl,
            p.cartellaId, c.idCliente, u.nome, u.cognome
     FROM PianiAlimentari p
     LEFT JOIN Clienti c ON c.idCliente = p.cliente
     LEFT JOIN Utenti u ON u.idUtente = c.idUtente
     WHERE p.cartellaId = ? AND p.creatoreUtente = ?
       AND (
         p.cliente IS NULL
         OR EXISTS (
           SELECT 1
           FROM Associazioni a
           WHERE a.cliente = p.cliente
             AND a.professionista = ?
             AND LOWER(a.tipoAssociazione) = 'nutrizionista'
             AND a.attivaFlag = 1
         )
       )
     ORDER BY p.aggiornatoIl DESC, p.idPianoAlim DESC",
    [(int)$cartellaAttiva['idCartella'], $userId, $professionistaId]
  )->fetchAll();
}

$pianoAttivo = null;
$assegnazioneAttiva = null;
$mealSlots = [
  'colazione' => ['label' => 'Colazione', 'emoji' => '🌅', 'ordine' => 1],
  'spuntino_mattina' => ['label' => 'Spuntino mattina', 'emoji' => '🍎', 'ordine' => 2],
  'pranzo' => ['label' => 'Pranzo', 'emoji' => '🍽️', 'ordine' => 3],
  'spuntino_pomeriggio' => ['label' => 'Spuntino pomeriggio', 'emoji' => '🥜', 'ordine' => 4],
  'cena' => ['label' => 'Cena', 'emoji' => '🌙', 'ordine' => 5],
];
$mealCards = [];
$dailyTotals = ['proteine' => 0.0, 'carboidrati' => 0.0, 'grassi' => 0.0, 'calorie' => 0.0];
if ($pianoAttivoId > 0) {
  $pianoAttivo = Database::exec(
    "SELECT p.idPianoAlim, p.titolo, p.note, p.stato, p.versione, p.cartellaId, p.cliente,
            p.aggiornatoIl, u.nome, u.cognome
     FROM PianiAlimentari p
     LEFT JOIN Clienti c ON c.idCliente = p.cliente
     LEFT JOIN Utenti u ON u.idUtente = c.idUtente
     WHERE p.idPianoAlim = ?
       AND p.creatoreUtente = ?
       AND (
         p.cliente IS NULL
         OR EXISTS (
           SELECT 1
           FROM Associazioni a
           WHERE a.cliente = p.cliente
             AND a.professionista = ?
             AND LOWER(a.tipoAssociazione) = 'nutrizionista'
             AND a.attivaFlag = 1
         )
       )
     LIMIT 1",
    [$pianoAttivoId, $userId, $professionistaId]
  )->fetch();

  if (!$pianoAttivo) {
    redirectNutrition($cartellaAttiva ? ['cartella' => (int)$cartellaAttiva['idCartella']] : []);
  }

  if ($assegnazioniEnabled) {
    $assegnazioneAttiva = Database::exec(
      "SELECT ap.idAssegnazionePiano, ap.cliente, ap.stato, ap.assegnatoIl, u.nome, u.cognome
       FROM AssegnazioniPianoAlimentare ap
       INNER JOIN Clienti c ON c.idCliente = ap.cliente
       INNER JOIN Utenti u ON u.idUtente = c.idUtente
       WHERE ap.pianoAlim = ?
       ORDER BY ap.assegnatoIl DESC, ap.idAssegnazionePiano DESC
       LIMIT 1",
      [$pianoAttivoId]
    )->fetch();
  }

  $mealRows = Database::exec(
    'SELECT idPastoPiano, nomePasto, ordine, note FROM PastiPiano WHERE pianoAlim = ? ORDER BY ordine, idPastoPiano',
    [$pianoAttivoId]
  )->fetchAll();

  $foodsRows = Database::exec(
    'SELECT ap.idAlimentoPiano, ap.pastoPiano, ap.nomeAlimento, ap.quantita, ap.unita, ap.proteine, ap.carboidrati, ap.grassi, ap.calorie
     FROM AlimentiPiano ap
     INNER JOIN PastiPiano pp ON pp.idPastoPiano = ap.pastoPiano
     WHERE pp.pianoAlim = ?
     ORDER BY ap.idAlimentoPiano',
    [$pianoAttivoId]
  )->fetchAll();

  $foodsByMeal = [];
  foreach ($foodsRows as $food) {
    $mealId = (int)$food['pastoPiano'];
    $foodsByMeal[$mealId][] = $food;
  }

  $mealByKey = [];
  $extraMeals = [];
  foreach ($mealRows as $mealRow) {
    $key = canonicalMealKey((string)$mealRow['nomePasto']);
    if ($key !== '' && !isset($mealByKey[$key])) {
      $mealByKey[$key] = $mealRow;
    } else {
      $extraMeals[] = $mealRow;
    }
  }

  foreach ($mealSlots as $slotKey => $slotData) {
    $mealRow = $mealByKey[$slotKey] ?? null;
    $mealFoods = $mealRow ? ($foodsByMeal[(int)$mealRow['idPastoPiano']] ?? []) : [];
    $totals = ['proteine' => 0.0, 'carboidrati' => 0.0, 'grassi' => 0.0, 'calorie' => 0.0];
    foreach ($mealFoods as $food) {
      $totals['proteine'] += (float)$food['proteine'];
      $totals['carboidrati'] += (float)$food['carboidrati'];
      $totals['grassi'] += (float)$food['grassi'];
      $totals['calorie'] += (float)$food['calorie'];
    }
    $dailyTotals['proteine'] += $totals['proteine'];
    $dailyTotals['carboidrati'] += $totals['carboidrati'];
    $dailyTotals['grassi'] += $totals['grassi'];
    $dailyTotals['calorie'] += $totals['calorie'];

    $mealCards[] = [
      'slotKey' => $slotKey,
      'label' => $slotData['label'],
      'emoji' => $slotData['emoji'],
      'ordine' => (int)$slotData['ordine'],
      'meal' => $mealRow,
      'foods' => $mealFoods,
      'totals' => $totals,
    ];
  }

  foreach ($extraMeals as $idx => $mealRow) {
    $mealFoods = $foodsByMeal[(int)$mealRow['idPastoPiano']] ?? [];
    $totals = ['proteine' => 0.0, 'carboidrati' => 0.0, 'grassi' => 0.0, 'calorie' => 0.0];
    foreach ($mealFoods as $food) {
      $totals['proteine'] += (float)$food['proteine'];
      $totals['carboidrati'] += (float)$food['carboidrati'];
      $totals['grassi'] += (float)$food['grassi'];
      $totals['calorie'] += (float)$food['calorie'];
    }
    $dailyTotals['proteine'] += $totals['proteine'];
    $dailyTotals['carboidrati'] += $totals['carboidrati'];
    $dailyTotals['grassi'] += $totals['grassi'];
    $dailyTotals['calorie'] += $totals['calorie'];

    $mealCards[] = [
      'slotKey' => 'extra_' . $idx,
      'label' => (string)$mealRow['nomePasto'],
      'emoji' => '✨',
      'ordine' => (int)$mealRow['ordine'],
      'meal' => $mealRow,
      'foods' => $mealFoods,
      'totals' => $totals,
    ];
  }
}
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell nutrition-shell">
  <?php if ($flash): ?>
    <div class="alert-strip <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= h((string)$flash['message']) ?></div>
  <?php endif; ?>

  <?php if (!$cartelleEnabled): ?>
    <article class="folder-card">
      <h3 style="margin-top:0">Supporto cartelle nutrizione non presente</h3>
      <p class="muted-sm">Esegui la migrazione SQL indicata in testa al file per abilitare la libreria cartelle e il collegamento ai piani alimentari.</p>
    </article>
  <?php elseif (!$cartellaAttiva): ?>
    <div class="library-toolbar">
      <h2 class="section-title" style="margin:0">Libreria piani alimentari</h2>
    </div>

    <div class="folder-grid">
      <?php foreach ($folders as $cartella): ?>
        <article class="folder-card folder-item nutrition-folder-card">
          <a class="folder-link" href="nutrizione.php?cartella=<?= (int)$cartella['idCartella'] ?>">
            <div>
              <strong>📁 <?= h((string)$cartella['nome']) ?></strong>
              <p class="muted-sm"><?= (int)$cartella['totalePiani'] ?> piano/i</p>
            </div>
          </a>
          <div class="folder-actions">
            <button type="button" class="icon-btn" data-open-rename-folder data-folder-id="<?= (int)$cartella['idCartella'] ?>" data-folder-name="<?= h((string)$cartella['nome']) ?>" aria-label="Modifica cartella">✎</button>
            <button type="button" class="icon-btn danger" data-open-delete-folder data-folder-id="<?= (int)$cartella['idCartella'] ?>" data-folder-name="<?= h((string)$cartella['nome']) ?>" aria-label="Elimina cartella">🗑</button>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if (!$folders): ?>
        <article class="folder-card">
          <strong>Nessuna cartella disponibile</strong>
          <p class="muted-sm">Crea la prima cartella per organizzare i tuoi piani alimentari.</p>
        </article>
      <?php endif; ?>

      <button type="button" class="folder-card folder-create" data-open-create-folder>
        <span class="create-plus">＋</span>
        <span class="muted-sm">Crea nuova cartella</span>
      </button>
    </div>
  <?php elseif (!$pianoAttivo): ?>
    <div class="library-toolbar">
      <a href="nutrizione.php" class="link-btn">← Torna alle cartelle</a>
      <h2 class="section-title" style="margin:0">📁 <?= h((string)$cartellaAttiva['nome']) ?></h2>
    </div>

    <div class="program-grid nutrition-plan-grid">
      <?php foreach ($pianiCartella as $piano): ?>
        <article class="program-card nutrition-plan-card" onclick="window.location.href='nutrizione.php?cartella=<?= (int)$cartellaAttiva['idCartella'] ?>&piano=<?= (int)$piano['idPianoAlim'] ?>'">
          <h4><?= h((string)$piano['titolo']) ?></h4>
          <p class="muted-sm"><?= h(mb_strimwidth((string)($piano['note'] ?? ''), 0, 140, '…')) ?></p>
          <div class="program-meta nutrition-preview">
            <span>Cliente: <?= $piano['idCliente'] ? h(trim((string)$piano['cognome'] . ' ' . (string)$piano['nome'])) : 'Non associato (bozza)' ?></span><br>
            <span>Stato: <?= h((string)$piano['stato']) ?> · v<?= (int)$piano['versione'] ?></span><br>
            <span>Aggiornato: <?= h((string)$piano['aggiornatoIl']) ?></span>
          </div>
        </article>
      <?php endforeach; ?>

      <button type="button" class="program-card add-program-card" data-open-create-plan data-folder-id="<?= (int)$cartellaAttiva['idCartella'] ?>">
        <div>
          <div class="create-plus">＋</div>
          <div class="muted-sm">Crea nuovo piano alimentare</div>
        </div>
      </button>
    </div>
  <?php else: ?>
    <div class="nutrition-builder-head">
      <div>
        <a href="nutrizione.php?cartella=<?= (int)$pianoAttivo['cartellaId'] ?>" class="link-btn">← Torna alla cartella</a>
        <h2 class="section-title" style="margin:10px 0 4px"><?= h((string)$pianoAttivo['titolo']) ?></h2>
        <p class="muted-sm">Builder piano nutrizionale · modifica rapida pasti e alimenti</p>
      </div>
      <div class="nutrition-builder-actions">
        <button type="button" class="btn primary" data-open-assign-plan>Assegna piano</button>
        <form method="post" style="margin:0">
          <input type="hidden" name="action" value="duplicate_plan">
          <input type="hidden" name="plan_id" value="<?= (int)$pianoAttivo['idPianoAlim'] ?>">
          <button type="submit" class="btn">Duplica</button>
        </form>
        <button type="button" class="btn danger" data-open-delete-plan data-plan-id="<?= (int)$pianoAttivo['idPianoAlim'] ?>" data-folder-id="<?= (int)$pianoAttivo['cartellaId'] ?>" data-plan-name="<?= h((string)$pianoAttivo['titolo']) ?>">Elimina</button>
      </div>
    </div>

    <article class="folder-card nutrition-plan-overview">
      <form method="post" class="nutrition-builder-fields">
        <input type="hidden" name="action" value="update_plan">
        <input type="hidden" name="plan_id" value="<?= (int)$pianoAttivo['idPianoAlim'] ?>">
        <div class="nutrition-overview-grid">
          <div>
            <label class="muted-sm" for="diet-plan-name">Nome piano</label>
            <input id="diet-plan-name" name="titolo" class="dark-input" type="text" value="<?= h((string)$pianoAttivo['titolo']) ?>" required />
          </div>
          <div class="muted-sm nutrition-meta-box">
            <strong><?= $pianoAttivo['cliente'] ? h(trim((string)$pianoAttivo['cognome'] . ' ' . (string)$pianoAttivo['nome'])) : 'Bozza non assegnata' ?></strong><br>
            Stato: <?= h((string)$pianoAttivo['stato']) ?> · v<?= (int)$pianoAttivo['versione'] ?><br>
            Ultimo aggiornamento: <?= h((string)$pianoAttivo['aggiornatoIl']) ?>
            <?php if ($assegnazioneAttiva): ?>
              <br>Assegnazione attiva: <?= h(trim((string)$assegnazioneAttiva['cognome'] . ' ' . (string)$assegnazioneAttiva['nome'])) ?>
            <?php endif; ?>
          </div>
        </div>
        <label class="muted-sm" for="diet-plan-description">Note generali</label>
        <textarea id="diet-plan-description" name="note" class="dark-textarea" rows="4"><?= h((string)$pianoAttivo['note']) ?></textarea>
        <div class="library-toolbar" style="justify-content:flex-end">
          <button type="submit" class="btn primary">Salva piano</button>
        </div>
      </form>
    </article>

    <div class="nutrition-builder-layout">
      <div class="nutrition-builder-main">
        <?php foreach ($mealCards as $mealCard): ?>
          <?php $meal = $mealCard['meal']; ?>
          <article class="folder-card meal-builder-card">
            <div class="meal-builder-header">
              <div>
                <h3><?= h((string)$mealCard['emoji']) ?> <?= h((string)$mealCard['label']) ?></h3>
                <p class="muted-sm"><?= count($mealCard['foods']) ?> alimento/i</p>
              </div>
              <div class="meal-header-right">
                <div class="meal-macro-chips">
                  <span>P <?= number_format((float)$mealCard['totals']['proteine'], 1) ?>g</span>
                  <span>C <?= number_format((float)$mealCard['totals']['carboidrati'], 1) ?>g</span>
                  <span>G <?= number_format((float)$mealCard['totals']['grassi'], 1) ?>g</span>
                  <span><?= number_format((float)$mealCard['totals']['calorie'], 0) ?> kcal</span>
                </div>
                <?php if ($meal && strpos((string)$mealCard['slotKey'], 'extra_') === 0): ?>
                  <form method="post" class="meal-extra-remove-form">
                    <input type="hidden" name="action" value="delete_meal_section">
                    <input type="hidden" name="meal_id" value="<?= (int)$meal['idPastoPiano'] ?>">
                    <button type="submit" class="btn tiny danger">Rimuovi spuntino extra</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>

            <?php if (!$meal): ?>
              <form method="post" class="meal-create-form">
                <input type="hidden" name="action" value="create_meal_section">
                <input type="hidden" name="plan_id" value="<?= (int)$pianoAttivo['idPianoAlim'] ?>">
                <input type="hidden" name="meal_label" value="<?= h((string)$mealCard['label']) ?>">
                <input type="hidden" name="meal_order" value="<?= (int)$mealCard['ordine'] ?>">
                <button type="submit" class="btn">Crea sezione</button>
              </form>
            <?php else: ?>
              <div class="meal-food-table-wrap">
                <table class="meal-food-table">
                  <thead>
                    <tr>
                      <th>Alimento</th>
                      <th>Qtà</th>
                      <th>Unità</th>
                      <th>P</th>
                      <th>C</th>
                      <th>G</th>
                      <th>Kcal</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($mealCard['foods'] as $food): ?>
                      <tr>
                        <form method="post">
                          <input type="hidden" name="action" value="update_food">
                          <input type="hidden" name="food_id" value="<?= (int)$food['idAlimentoPiano'] ?>">
                          <td><input class="dark-input slim" type="text" name="nomeAlimento" value="<?= h((string)$food['nomeAlimento']) ?>" required></td>
                          <td><input class="dark-input slim" type="number" step="0.1" min="0" name="quantita" value="<?= h((string)$food['quantita']) ?>"></td>
                          <td><input class="dark-input slim" type="text" name="unita" value="<?= h((string)$food['unita']) ?>"></td>
                          <td><input class="dark-input slim" type="number" step="0.1" min="0" name="proteine" value="<?= h((string)$food['proteine']) ?>"></td>
                          <td><input class="dark-input slim" type="number" step="0.1" min="0" name="carboidrati" value="<?= h((string)$food['carboidrati']) ?>"></td>
                          <td><input class="dark-input slim" type="number" step="0.1" min="0" name="grassi" value="<?= h((string)$food['grassi']) ?>"></td>
                          <td><input class="dark-input slim" type="number" step="0.1" min="0" name="calorie" value="<?= h((string)$food['calorie']) ?>"></td>
                          <td class="meal-row-actions">
                            <button class="btn tiny" type="submit">Salva</button>
                            <button class="btn tiny danger" type="submit" name="action" value="delete_food">🗑</button>
                          </td>
                        </form>
                      </tr>
                    <?php endforeach; ?>

                    <tr class="meal-add-row">
                      <form method="post">
                        <input type="hidden" name="action" value="add_food">
                        <input type="hidden" name="meal_id" value="<?= (int)$meal['idPastoPiano'] ?>">
                        <td><input class="dark-input slim" type="text" name="nomeAlimento" placeholder="Nuovo alimento"></td>
                        <td><input class="dark-input slim" type="number" step="0.1" min="0" name="quantita" placeholder="0"></td>
                        <td><input class="dark-input slim" type="text" name="unita" value="g"></td>
                        <td><input class="dark-input slim" type="number" step="0.1" min="0" name="proteine" placeholder="0"></td>
                        <td><input class="dark-input slim" type="number" step="0.1" min="0" name="carboidrati" placeholder="0"></td>
                        <td><input class="dark-input slim" type="number" step="0.1" min="0" name="grassi" placeholder="0"></td>
                        <td><input class="dark-input slim" type="number" step="0.1" min="0" name="calorie" placeholder="0"></td>
                        <td><button class="btn tiny" type="submit">+ Aggiungi alimento</button></td>
                      </form>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div style="display:flex;justify-content:flex-end;margin-top:6px">
                <button type="button" class="btn" data-open-off-plan-modal data-meal-id="<?= (int)$meal['idPastoPiano'] ?>">Aggiungi da Open Food Facts</button>
              </div>

              <form method="post" class="meal-notes-form">
                <input type="hidden" name="action" value="save_meal_notes">
                <input type="hidden" name="meal_id" value="<?= (int)$meal['idPastoPiano'] ?>">
                <label class="muted-sm">Note pasto</label>
                <textarea class="dark-textarea" name="meal_note" rows="2" placeholder="Indicazioni per il cliente..."><?= h((string)$meal['note']) ?></textarea>
                <div class="meal-actions-line">
                  <button class="btn primary" type="submit">Salva <?= h((string)$mealCard['label']) ?></button>
                </div>
              </form>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>

      <aside class="nutrition-builder-side">
        <article class="folder-card nutrition-side-card">
          <h4>Riepilogo giornaliero</h4>
          <div class="side-macros">
            <div><span>Proteine</span><strong><?= number_format((float)$dailyTotals['proteine'], 1) ?> g</strong></div>
            <div><span>Carboidrati</span><strong><?= number_format((float)$dailyTotals['carboidrati'], 1) ?> g</strong></div>
            <div><span>Grassi</span><strong><?= number_format((float)$dailyTotals['grassi'], 1) ?> g</strong></div>
            <div><span>Calorie</span><strong><?= number_format((float)$dailyTotals['calorie'], 0) ?> kcal</strong></div>
          </div>
        </article>

        <article class="folder-card nutrition-side-card">
          <h4>Azioni rapide</h4>
          <div class="quick-actions">
            <button class="btn primary" type="button" onclick="document.querySelector('form.nutrition-builder-fields button[type=submit]')?.click()">Salva tutto</button>
            <form method="post">
              <input type="hidden" name="action" value="create_meal_section">
              <input type="hidden" name="plan_id" value="<?= (int)$pianoAttivo['idPianoAlim'] ?>">
              <input type="hidden" name="meal_label" value="Spuntino extra">
              <input type="hidden" name="meal_order" value="6">
              <button class="btn" type="submit">Aggiungi spuntino extra</button>
            </form>
            <form method="post">
              <input type="hidden" name="action" value="duplicate_plan">
              <input type="hidden" name="plan_id" value="<?= (int)$pianoAttivo['idPianoAlim'] ?>">
              <button class="btn" type="submit">Duplica da template</button>
            </form>
          </div>
        </article>

        <article class="folder-card nutrition-side-card info">
          <h4>Info tecniche</h4>
          <p class="muted-sm">Builder attivo su PianiAlimentari / PastiPiano / AlimentiPiano.<?= !$assegnazioniEnabled ? ' La tabella AssegnazioniPianoAlimentare non risulta presente: gestire la migrazione separatamente.' : '' ?></p>
        </article>
      </aside>
    </div>
  <?php endif; ?>
</section>

<div class="modal-layer" data-modal="create-folder">
  <div class="modal-card">
    <h3>Crea nuova cartella</h3>
    <form method="post">
      <input type="hidden" name="action" value="create_folder" />
      <input class="dark-input" type="text" name="nome" placeholder="Nome cartella" required />
      <div class="library-toolbar" style="justify-content:flex-end">
        <button class="btn" type="button" data-close-modal>Annulla</button>
        <button class="btn primary" type="submit">Crea nuova cartella</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-layer" data-modal="rename-folder">
  <div class="modal-card">
    <h3>Rinomina cartella</h3>
    <form method="post">
      <input type="hidden" name="action" value="rename_folder" />
      <input type="hidden" name="folder_id" data-rename-folder-id />
      <input class="dark-input" type="text" name="nome" data-rename-folder-input placeholder="Nome cartella" required />
      <div class="library-toolbar" style="justify-content:flex-end">
        <button class="btn" type="button" data-close-modal>Annulla</button>
        <button class="btn primary" type="submit">Salva</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-layer" data-modal="delete-folder">
  <div class="modal-card">
    <h3>Elimina cartella</h3>
    <p class="muted-sm">Confermi l'eliminazione della cartella <strong data-delete-folder-name></strong>?</p>
    <form method="post" style="display:flex;justify-content:flex-end;gap:10px">
      <input type="hidden" name="action" value="delete_folder" />
      <input type="hidden" name="folder_id" data-delete-folder-id />
      <button class="btn" type="button" data-close-modal>Annulla</button>
      <button class="btn danger" type="submit">Elimina</button>
    </form>
  </div>
</div>

<div class="modal-layer" data-modal="create-plan">
  <div class="modal-card">
    <h3>Crea nuovo piano alimentare</h3>
    <form method="post">
      <input type="hidden" name="action" value="create_plan" />
      <input type="hidden" name="folder_id" data-create-plan-folder-id value="<?= (int)$cartellaAttivaId ?>" />
      <input class="dark-input" type="text" name="titolo" placeholder="Nome piano" required />
      <select class="dark-input" name="cliente">
        <option value="">Nessun cliente (bozza)</option>
        <?php foreach ($clientRows as $cliente): ?>
          <option value="<?= (int)$cliente['idCliente'] ?>"><?= h(trim((string)$cliente['cognome'] . ' ' . (string)$cliente['nome'])) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="dark-input" name="stato" required>
        <option value="bozza">bozza</option>
        <option value="attivo">attivo</option>
        <option value="archiviato">archiviato</option>
      </select>
      <textarea class="dark-textarea" name="note" placeholder="Descrizione"></textarea>
      <?php if (!$clientRows): ?>
        <p class="muted-sm">Nessun cliente associato disponibile: puoi creare comunque una bozza non assegnata.</p>
      <?php endif; ?>
      <div class="library-toolbar" style="justify-content:flex-end">
        <button class="btn" type="button" data-close-modal>Annulla</button>
        <button class="btn primary" type="submit">Crea nuovo piano alimentare</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-layer" data-modal="delete-plan">
  <div class="modal-card">
    <h3>Elimina piano alimentare</h3>
    <p class="muted-sm">Confermi l'eliminazione del piano <strong data-delete-plan-name></strong>?</p>
    <form method="post" data-delete-plan-form style="display:flex;justify-content:flex-end;gap:10px">
      <input type="hidden" name="action" value="delete_plan" />
      <input type="hidden" name="plan_id" data-delete-plan-id />
      <input type="hidden" name="folder_id" data-delete-plan-folder-id value="<?= (int)$cartellaAttivaId ?>" />
      <button class="btn" type="button" data-close-modal>Annulla</button>
      <button class="btn danger" type="submit">Elimina</button>
    </form>
  </div>
</div>

<div class="modal-layer" data-modal="assign-plan">
  <div class="modal-card">
    <h3>Assegna piano alimentare</h3>
    <form method="post">
      <input type="hidden" name="action" value="assign_plan" />
      <input type="hidden" name="plan_id" value="<?= (int)($pianoAttivo['idPianoAlim'] ?? 0) ?>" />
      <select class="dark-input" name="cliente" required>
        <option value="">Seleziona cliente</option>
        <?php foreach ($clientRows as $cliente): ?>
          <option value="<?= (int)$cliente['idCliente'] ?>"><?= h(trim((string)$cliente['cognome'] . ' ' . (string)$cliente['nome'])) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (!$assegnazioniEnabled): ?>
        <p class="muted-sm">Tabella AssegnazioniPianoAlimentare non trovata nel database.</p>
      <?php endif; ?>
      <div class="library-toolbar" style="justify-content:flex-end">
        <button class="btn" type="button" data-close-modal>Annulla</button>
        <button class="btn primary" type="submit" <?= (!$clientRows || !$assegnazioniEnabled) ? 'disabled' : '' ?>>Assegna</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-layer" data-modal="off-plan">
  <div class="modal-card">
    <h3>Aggiungi da Open Food Facts</h3>
    <input type="hidden" data-off-plan-meal-id value="">
    <label class="muted-sm">Ricerca alimento</label>
    <div style="display:flex;gap:8px">
      <input class="dark-input" type="text" data-off-plan-query placeholder="Es. riso basmati">
      <button class="btn" type="button" data-off-plan-search>Cerca</button>
    </div>
    <label class="muted-sm" style="margin-top:8px">Barcode</label>
    <div style="display:flex;gap:8px">
      <input class="dark-input" type="text" data-off-plan-barcode placeholder="EAN/UPC">
      <button class="btn" type="button" data-off-plan-lookup>Lookup</button>
    </div>
    <div class="off-results" data-off-plan-results></div>
    <div class="off-preview" data-off-plan-preview style="display:none">
      <div class="two">
        <label class="field"><span>Modalità</span>
          <select class="dark-input" data-off-plan-mode><option value="grams">Grammi</option><option value="servings">Porzioni</option></select>
        </label>
        <label class="field"><span>Quantità</span><input class="dark-input" type="number" min="0.1" step="0.1" value="100" data-off-plan-amount></label>
      </div>
      <p class="muted-sm" data-off-plan-macros>Macro non calcolati.</p>
      <div style="display:flex;justify-content:flex-end">
        <button class="btn primary" type="button" data-off-plan-save>Salva alimento nel pasto</button>
      </div>
    </div>
  </div>
</div>

<style>
  .nutrition-shell { width: min(100%, 1120px); }
  .nutrition-folder-card, .nutrition-plan-card { transition: transform .15s ease, border-color .15s ease, background .15s ease; }
  .nutrition-folder-card:hover, .nutrition-plan-card:hover { transform: translateY(-2px); border-color: rgba(134, 195, 255, .45); background: rgba(255, 255, 255, .08); }
  .nutrition-plan-grid { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); }
  .nutrition-preview { margin-top: 8px; line-height: 1.4; }
  .nutrition-builder-shell { display: grid; gap: 16px; max-width: 900px; }
  .nutrition-builder-toolbar { justify-content: flex-start; }
  .nutrition-builder-fields { display: grid; gap: 8px; }
  .alert-strip { padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; font-size: .95rem; }
  .alert-strip.ok { background: rgba(39,174,96,.18); border: 1px solid rgba(39,174,96,.45); }
  .alert-strip.error { background: rgba(231,76,60,.18); border: 1px solid rgba(231,76,60,.45); }
  .nutrition-builder-head { display:flex; gap:14px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; margin-bottom:12px; }
  .nutrition-builder-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  .nutrition-plan-overview { margin-bottom:16px; }
  .nutrition-overview-grid { display:grid; gap:12px; grid-template-columns: minmax(260px,1fr) minmax(260px,1fr); }
  .nutrition-meta-box { padding:12px; border:1px solid rgba(136,178,255,.24); border-radius:12px; background:rgba(14, 22, 42, .65); line-height:1.5; }
  .nutrition-builder-layout { display:grid; gap:16px; grid-template-columns: minmax(0, 2.1fr) minmax(280px, 1fr); align-items:start; }
  .nutrition-builder-main { display:grid; gap:14px; }
  .meal-builder-card { display:grid; gap:12px; }
  .meal-builder-header { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; }
  .meal-builder-header h3 { margin:0; }
  .meal-header-right { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
  .meal-macro-chips { display:flex; flex-wrap:wrap; gap:6px; justify-content:flex-end; }
  .meal-macro-chips span { display:inline-flex; align-items:center; justify-content:center; min-height:28px; font-size:.75rem; border:1px solid rgba(96,177,245,.34); border-radius:999px; padding:2px 9px; color:#cfe4ff; background:rgba(21,40,72,.55); line-height:1; white-space:nowrap; }
  .meal-extra-remove-form { margin:0; }
  .meal-food-table-wrap { overflow:auto; }
  .meal-food-table { width:100%; border-collapse:separate; border-spacing:0 8px; min-width:900px; }
  .meal-food-table th { text-align:left; font-size:.78rem; color:#9fb2d8; font-weight:600; }
  .meal-food-table td { vertical-align:middle; }
  .dark-input.slim { min-height:36px; padding:7px 9px; font-size:.88rem; }
  .meal-row-actions { display:flex; gap:6px; }
  .btn.tiny { padding:8px 10px; font-size:.75rem; }
  .meal-add-row td { padding-top:4px; }
  .meal-notes-form { display:grid; gap:8px; }
  .meal-actions-line { display:flex; justify-content:flex-end; }
  .off-results{display:grid;gap:8px;margin-top:10px}
  .off-card{display:grid;grid-template-columns:50px minmax(0,1fr) auto;gap:8px;align-items:center;padding:8px;border:1px solid rgba(96,177,245,.34);border-radius:12px;background:rgba(21,40,72,.35)}
  .off-card img{width:50px;height:50px;object-fit:cover;border-radius:10px;background:#0f1729}
  .off-preview{margin-top:10px;padding:10px;border:1px solid rgba(96,177,245,.34);border-radius:12px;background:rgba(14,22,42,.65)}
  .nutrition-builder-side { display:grid; gap:12px; position:sticky; top:10px; }
  .nutrition-side-card { display:grid; gap:10px; }
  .side-macros { display:grid; gap:8px; }
  .side-macros div { display:flex; justify-content:space-between; align-items:center; font-size:.92rem; padding:8px 10px; border-radius:10px; background:rgba(26,38,66,.56); border:1px solid rgba(130,165,227,.22); }
  .side-macros span { color:#adc2ea; }
  .quick-actions { display:grid; gap:8px; }
  .meal-create-form { display:flex; justify-content:flex-start; }
  @media (max-width: 1080px) {
    .nutrition-builder-layout { grid-template-columns: 1fr; }
    .nutrition-builder-side { position:static; }
    .meal-macro-chips span { min-height:26px; font-size:.72rem; padding:2px 8px; }
  }
  @media (max-width: 720px) {
    .nutrition-overview-grid { grid-template-columns: 1fr; }
  }
</style>
<?php
renderEnd(<<<'SCRIPT'
<script>
  (function () {
    const modals = document.querySelectorAll('[data-modal]');
    const offPlanModal = document.querySelector('[data-modal="off-plan"]');
    let offPlanProduct = null;
    let offPlanDebounce = null;
    function openModal(name) {
      const modal = document.querySelector('[data-modal="' + name + '"]');
      if (modal) modal.classList.add('open');
    }
    function closeAllModals() {
      modals.forEach(function (m) { m.classList.remove('open'); });
    }

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
      button.addEventListener('click', closeAllModals);
    });

    modals.forEach(function (modal) {
      modal.addEventListener('click', function (event) {
        if (event.target === modal) closeAllModals();
      });
    });

    document.querySelector('[data-open-create-folder]')?.addEventListener('click', function () { openModal('create-folder'); });

    document.querySelectorAll('[data-open-rename-folder]').forEach(function (button) {
      button.addEventListener('click', function () {
        const input = document.querySelector('[data-rename-folder-input]');
        const hiddenId = document.querySelector('[data-rename-folder-id]');
        if (input) input.value = button.getAttribute('data-folder-name') || '';
        if (hiddenId) hiddenId.value = button.getAttribute('data-folder-id') || '';
        openModal('rename-folder');
      });
    });

    document.querySelectorAll('[data-open-delete-folder]').forEach(function (button) {
      button.addEventListener('click', function () {
        const target = document.querySelector('[data-delete-folder-name]');
        const hiddenId = document.querySelector('[data-delete-folder-id]');
        if (target) target.textContent = button.getAttribute('data-folder-name') || '';
        if (hiddenId) hiddenId.value = button.getAttribute('data-folder-id') || '';
        openModal('delete-folder');
      });
    });

    document.querySelector('[data-open-create-plan]')?.addEventListener('click', function (event) {
      const folderId = event.currentTarget.getAttribute('data-folder-id') || '';
      const input = document.querySelector('[data-create-plan-folder-id]');
      if (input) input.value = folderId;
      openModal('create-plan');
    });

    document.querySelector('[data-open-delete-plan]')?.addEventListener('click', function (event) {
      const trigger = event.currentTarget;
      const target = document.querySelector('[data-delete-plan-name]');
      const input = document.querySelector('[data-delete-plan-id]');
      const folderInput = document.querySelector('[data-delete-plan-folder-id]');
      if (target) target.textContent = trigger.getAttribute('data-plan-name') || '';
      if (input) input.value = trigger.getAttribute('data-plan-id') || '';
      if (folderInput) folderInput.value = trigger.getAttribute('data-folder-id') || folderInput.value || '';
      openModal('delete-plan');
    });

    const deletePlanForm = document.querySelector('form[data-delete-plan-form]');
    deletePlanForm?.addEventListener('submit', async function (event) {
      event.preventDefault();
      const submitButton = deletePlanForm.querySelector('button[type="submit"]');
      if (submitButton) submitButton.disabled = true;

      const folderId = deletePlanForm.querySelector('[data-delete-plan-folder-id]')?.value || '';
      const fallbackTarget = folderId && Number(folderId) > 0
        ? `nutrizione.php?cartella=${encodeURIComponent(folderId)}`
        : 'nutrizione.php';

      try {
        const response = await fetch(window.location.href, {
          method: 'POST',
          body: new FormData(deletePlanForm),
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          cache: 'no-store'
        });

        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        let payload = null;

        if (contentType.includes('application/json')) {
          payload = await response.json();
        } else if (response.redirected && response.url) {
          window.location.href = response.url;
          return;
        } else {
          const raw = await response.text();
          try {
            payload = JSON.parse(raw);
          } catch (parseError) {
            if (response.ok) {
              window.location.href = fallbackTarget;
              return;
            }
            showInlineAlert('error', 'Operazione non riuscita.');
            return;
          }
        }

        if (!response.ok || !payload || !payload.ok) {
          showInlineAlert('error', (payload && payload.message) ? payload.message : 'Operazione non riuscita.');
          return;
        }

        window.location.href = (payload.redirect && typeof payload.redirect === 'string') ? payload.redirect : fallbackTarget;
      } catch (error) {
        showInlineAlert('error', 'Errore di rete. Riprova.');
      } finally {
        if (submitButton) submitButton.disabled = false;
      }
    });

    document.querySelector('[data-open-assign-plan]')?.addEventListener('click', function () { openModal('assign-plan'); });
    document.querySelectorAll('[data-open-off-plan-modal]').forEach(function (button) {
      button.addEventListener('click', function () {
        const mealIdInput = document.querySelector('[data-off-plan-meal-id]');
        if (mealIdInput) mealIdInput.value = button.getAttribute('data-meal-id') || '';
        openModal('off-plan');
      });
    });

    async function offApi(form) {
      const response = await fetch('../api/openfoodfacts.php', { method: 'POST', body: form, headers: { 'Accept': 'application/json' } });
      const raw = await response.text();
      let payload = null;
      if (raw.trim() !== '') {
        try {
          payload = JSON.parse(raw);
        } catch (e) {
          throw new Error('Risposta API non valida: ' + raw.slice(0, 180));
        }
      }
      if (!payload) {
        throw new Error('Endpoint OFF vuoto (HTTP ' + response.status + ').');
      }
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'Errore API');
      return payload;
    }

    function renderOffPlanResults(products) {
      const wrap = offPlanModal?.querySelector('[data-off-plan-results]');
      if (!wrap) return;
      wrap.innerHTML = '';
      if (!products.length) {
        wrap.innerHTML = '<p class="muted-sm">Nessun risultato trovato.</p>';
        return;
      }
      products.forEach(function (p) {
        const card = document.createElement('div');
        card.className = 'off-card';
        card.innerHTML = `<img src="${p.image_url || ''}" alt=""><div><strong>${p.name || 'Senza nome'}</strong><br><span class="muted-sm">${p.brand || 'Marca n/d'} · ${p.kcal_100g ?? 0} kcal/100g</span></div><button type="button" class="btn tiny">Seleziona</button>`;
        card.querySelector('button')?.addEventListener('click', function () {
          offPlanProduct = p;
          offPlanModal.querySelector('[data-off-plan-preview]').style.display = '';
          recalcOffPlanPreview();
        });
        wrap.appendChild(card);
      });
    }

    async function recalcOffPlanPreview() {
      if (!offPlanProduct || !offPlanModal) return;
      const form = new FormData();
      form.append('action', 'calculate');
      form.append('barcode', offPlanProduct.barcode);
      form.append('mode', offPlanModal.querySelector('[data-off-plan-mode]').value);
      form.append('amount', offPlanModal.querySelector('[data-off-plan-amount]').value);
      const payload = await offApi(form);
      const m = payload.macros || {};
      const box = offPlanModal.querySelector('[data-off-plan-macros]');
      if (box) box.textContent = `Kcal ${m.calorie} · P ${m.proteine}g · C ${m.carboidrati}g · G ${m.grassi}g`;
    }

    offPlanModal?.querySelector('[data-off-plan-search]')?.addEventListener('click', async function () {
      try {
        const q = (offPlanModal.querySelector('[data-off-plan-query]')?.value || '').trim();
        if (q.length < 2) return;
        const form = new FormData();
        form.append('action', 'search');
        form.append('q', q);
        const payload = await offApi(form);
        renderOffPlanResults(payload.products || []);
      } catch (error) {
        showInlineAlert('error', error.message || 'Errore ricerca Open Food Facts.');
      }
    });

    offPlanModal?.querySelector('[data-off-plan-query]')?.addEventListener('input', function () {
      clearTimeout(offPlanDebounce);
      offPlanDebounce = setTimeout(function () {
        offPlanModal.querySelector('[data-off-plan-search]')?.click();
      }, 500);
    });

    offPlanModal?.querySelector('[data-off-plan-lookup]')?.addEventListener('click', async function () {
      try {
        const barcode = (offPlanModal.querySelector('[data-off-plan-barcode]')?.value || '').trim();
        if (!barcode) return;
        const form = new FormData();
        form.append('action', 'barcode_lookup');
        form.append('barcode', barcode);
        const payload = await offApi(form);
        renderOffPlanResults(payload.product ? [payload.product] : []);
      } catch (error) {
        showInlineAlert('error', error.message || 'Errore lookup barcode OFF.');
      }
    });

    offPlanModal?.querySelector('[data-off-plan-mode]')?.addEventListener('change', recalcOffPlanPreview);
    offPlanModal?.querySelector('[data-off-plan-amount]')?.addEventListener('input', recalcOffPlanPreview);

    offPlanModal?.querySelector('[data-off-plan-save]')?.addEventListener('click', async function () {
      try {
        if (!offPlanProduct) return;
        const mealId = offPlanModal.querySelector('[data-off-plan-meal-id]')?.value || '';
        if (!mealId) return;
        const form = new FormData();
        form.append('action', 'add_plan_food');
        form.append('meal_id', mealId);
        form.append('barcode', offPlanProduct.barcode);
        form.append('mode', offPlanModal.querySelector('[data-off-plan-mode]').value);
        form.append('amount', offPlanModal.querySelector('[data-off-plan-amount]').value);
        await offApi(form);
        window.location.reload();
      } catch (error) {
        showInlineAlert('error', error.message || 'Errore salvataggio alimento OFF.');
      }
    });

    const alertStrip = document.querySelector('.alert-strip');
    function showInlineAlert(type, message) {
      if (!message) return;
      if (!alertStrip) {
        window.alert(message);
        return;
      }
      alertStrip.textContent = message;
      alertStrip.classList.remove('ok', 'error');
      alertStrip.classList.add(type === 'ok' ? 'ok' : 'error');
      alertStrip.style.display = '';
    }

    document.querySelectorAll('form[method="post"]').forEach(function (form) {
      if (form.matches('[data-delete-plan-form]')) return;
      form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const actionButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        actionButtons.forEach(function (btn) { btn.disabled = true; });

        try {
          const response = await fetch(window.location.href, {
            method: 'POST',
            body: new FormData(form),
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            },
            cache: 'no-store'
          });

          const contentType = (response.headers.get('content-type') || '').toLowerCase();
          let payload = null;

          if (contentType.includes('application/json')) {
            payload = await response.json();
          } else if (response.redirected && response.url) {
            window.location.replace(response.url);
            return;
          } else {
            const raw = await response.text();
            try {
              payload = JSON.parse(raw);
            } catch (parseError) {
              if (response.ok) {
                window.location.reload();
                return;
              }
              showInlineAlert('error', 'Operazione non riuscita. Riprova.');
              return;
            }
          }

          if (!response.ok || !payload || !payload.ok) {
            showInlineAlert('error', (payload && payload.message) ? payload.message : 'Operazione non riuscita.');
            return;
          }

          showInlineAlert('ok', payload.message || 'Operazione completata.');
          const nextUrl = payload.redirect || window.location.href;
          window.location.replace(nextUrl + (nextUrl.includes('?') ? '&' : '?') + '_ts=' + Date.now());
        } catch (error) {
          showInlineAlert('error', 'Errore di rete. Riprova.');
        } finally {
          actionButtons.forEach(function (btn) { btn.disabled = false; });
        }
      });
    });
  })();
</script>
SCRIPT
);
