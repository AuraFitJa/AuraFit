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
*/

function redirectNutrition(array $params = []): void {
  $query = http_build_query($params);
  header('Location: nutrizione.php' . ($query !== '' ? ('?' . $query) : ''));
  exit;
}

function setFlash(string $type, string $message): void {
  $_SESSION['nutrizione_flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
  $flash = $_SESSION['nutrizione_flash'] ?? null;
  unset($_SESSION['nutrizione_flash']);
  return is_array($flash) ? $flash : null;
}

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
      setFlash('ok', 'Cartella creata con successo.');
      redirectNutrition();
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
      setFlash('ok', 'Cartella rinominata.');
      redirectNutrition();
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
      setFlash('ok', 'Cartella eliminata.');
      redirectNutrition();
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
      if ($clienteValue === null && strtolower($stato) !== 'bozza') {
        throw new RuntimeException('Per piani senza cliente lo stato deve essere bozza.');
      }

      $folder = Database::exec('SELECT idCartella FROM PianiAlimentariCartelle WHERE idCartella = ? AND professionista = ? LIMIT 1', [$folderId, $professionistaId])->fetch();
      if (!$folder) {
        throw new RuntimeException('Cartella non trovata.');
      }

      Database::exec(
        'INSERT INTO PianiAlimentari (cliente, creatoreUtente, stato, titolo, note, versione, pianoPrecedente, creatoIl, aggiornatoIl, cartellaId)
         VALUES (?, ?, ?, ?, ?, 1, NULL, NOW(), NOW(), ?)',
        [$clienteValue, $userId, $stato, $titolo, $note, $folderId]
      );
      $newPlanId = (int)Database::pdo()->lastInsertId();
      setFlash('ok', 'Piano alimentare creato.');
      redirectNutrition(['cartella' => $folderId, 'piano' => $newPlanId]);
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
      setFlash('ok', 'Piano aggiornato.');
      redirectNutrition(['cartella' => (int)$ownedPlan['cartellaId'], 'piano' => $planId]);
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
      setFlash('ok', 'Piano duplicato con successo.');
      redirectNutrition(['cartella' => (int)$original['cartellaId'], 'piano' => $newPlanId]);
    }

    if ($action === 'delete_plan') {
      $planId = (int)($_POST['plan_id'] ?? 0);
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
      setFlash('ok', 'Piano eliminato.');
      redirectNutrition(['cartella' => (int)$ownedPlan['cartellaId']]);
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

      setFlash('ok', 'Piano assegnato al cliente.');
      redirectNutrition(['cartella' => (int)$plan['cartellaId'], 'piano' => $planId]);
    }
  } catch (Throwable $e) {
    if (Database::pdo()->inTransaction()) {
      Database::pdo()->rollBack();
    }
    setFlash('error', $e->getMessage());
    $redirect = [];
    if (!empty($_POST['folder_id'])) {
      $redirect['cartella'] = (int)$_POST['folder_id'];
    }
    if (!empty($_POST['plan_id'])) {
      $redirect['piano'] = (int)$_POST['plan_id'];
    }
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

      <?php if (!$pianiCartella): ?>
        <article class="program-card nutrition-plan-card">
          <h4>Nessun piano in cartella</h4>
          <p class="muted-sm">Crea un nuovo piano alimentare per iniziare.</p>
        </article>
      <?php endif; ?>

      <button type="button" class="program-card add-program-card" data-open-create-plan data-folder-id="<?= (int)$cartellaAttiva['idCartella'] ?>">
        <div>
          <div class="create-plus">＋</div>
          <div class="muted-sm">Crea nuovo piano alimentare</div>
        </div>
      </button>
    </div>
  <?php else: ?>
    <div class="program-toolbar">
      <a href="nutrizione.php?cartella=<?= (int)$pianoAttivo['cartellaId'] ?>" class="link-btn">← Torna alla cartella</a>
      <h2 class="section-title" style="margin:0"><?= h((string)$pianoAttivo['titolo']) ?></h2>
    </div>

    <article class="folder-card nutrition-builder-shell">
      <div class="program-toolbar nutrition-builder-toolbar">
        <button type="button" class="btn primary" data-open-assign-plan>Assegna piano</button>
        <form method="post" style="margin:0">
          <input type="hidden" name="action" value="duplicate_plan">
          <input type="hidden" name="plan_id" value="<?= (int)$pianoAttivo['idPianoAlim'] ?>">
          <button type="submit" class="btn">Duplica</button>
        </form>
        <button type="button" class="btn danger" data-open-delete-plan data-plan-id="<?= (int)$pianoAttivo['idPianoAlim'] ?>" data-plan-name="<?= h((string)$pianoAttivo['titolo']) ?>">Elimina</button>
      </div>

      <?php if ($assegnazioneAttiva): ?>
        <p class="muted-sm">Ultima assegnazione: <?= h(trim((string)$assegnazioneAttiva['cognome'] . ' ' . (string)$assegnazioneAttiva['nome'])) ?> (<?= h((string)$assegnazioneAttiva['stato']) ?>)</p>
      <?php endif; ?>

      <form method="post" class="nutrition-builder-fields">
        <input type="hidden" name="action" value="update_plan">
        <input type="hidden" name="plan_id" value="<?= (int)$pianoAttivo['idPianoAlim'] ?>">

        <label class="muted-sm" for="diet-plan-name">Nome piano</label>
        <input id="diet-plan-name" name="titolo" class="dark-input" type="text" value="<?= h((string)$pianoAttivo['titolo']) ?>" required />

        <label class="muted-sm" for="diet-plan-description">Descrizione</label>
        <textarea id="diet-plan-description" name="note" class="dark-textarea" rows="8"><?= h((string)$pianoAttivo['note']) ?></textarea>

        <div class="library-toolbar" style="justify-content:flex-end">
          <button type="submit" class="btn primary">Salva modifiche</button>
        </div>
      </form>
    </article>
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
    <form method="post" style="display:flex;justify-content:flex-end;gap:10px">
      <input type="hidden" name="action" value="delete_plan" />
      <input type="hidden" name="plan_id" data-delete-plan-id />
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
</style>
<?php
renderEnd(<<<'SCRIPT'
<script>
  (function () {
    const modals = document.querySelectorAll('[data-modal]');
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
      if (target) target.textContent = trigger.getAttribute('data-plan-name') || '';
      if (input) input.value = trigger.getAttribute('data-plan-id') || '';
      openModal('delete-plan');
    });

    document.querySelector('[data-open-assign-plan]')?.addEventListener('click', function () { openModal('assign-plan'); });
  })();
</script>
SCRIPT
);
