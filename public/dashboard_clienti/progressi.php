<?php
require __DIR__ . '/common.php';

$weightError = null;
$weightSuccess = null;
$weightHistory = [];

function ensurePesoTableExists(): void {
  Database::exec(
    'CREATE TABLE IF NOT EXISTS MisurazioniPeso (
      idMisurazione BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      idCliente BIGINT UNSIGNED NOT NULL,
      pesoKg DECIMAL(5,2) NOT NULL,
      dataMisurazione DATE NOT NULL,
      creatoIl TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      aggiornatoIl TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uk_cliente_data (idCliente, dataMisurazione),
      KEY idx_cliente_data (idCliente, dataMisurazione),
      CONSTRAINT fk_misurazionipeso_cliente FOREIGN KEY (idCliente) REFERENCES Clienti(idCliente) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
  );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_weight')) {
  if (!aurafit_validate_csrf_token(aurafit_request_csrf_token())) {
    $weightError = 'Richiesta non valida (CSRF).';
  } elseif (!$dbAvailable) {
    $weightError = $dbError ?? 'Database non disponibile.';
  } else {
    $pesoInput = trim((string)($_POST['peso'] ?? ''));
    $dataInput = trim((string)($_POST['data_misurazione'] ?? date('Y-m-d')));
    $pesoValue = str_replace(',', '.', $pesoInput);

    if ($pesoValue === '' || !is_numeric($pesoValue) || (float)$pesoValue < 20 || (float)$pesoValue > 700) {
      $weightError = 'Inserisci un peso valido tra 20 e 700 kg.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInput) || strtotime($dataInput) === false) {
      $weightError = 'Data misurazione non valida.';
    } else {
      try {
        $cliente = Database::exec(
          'SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1',
          [(int)$user['idUtente']]
        )->fetch();

        if (!$cliente) {
          $weightError = 'Profilo cliente non collegato all\'utente.';
        } else {
          ensurePesoTableExists();

          Database::exec(
            'INSERT INTO MisurazioniPeso (idCliente, pesoKg, dataMisurazione)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
               pesoKg = VALUES(pesoKg),
               aggiornatoIl = CURRENT_TIMESTAMP',
            [(int)$cliente['idCliente'], round((float)$pesoValue, 2), $dataInput]
          );

          Database::exec(
            'UPDATE ProfiloCliente SET pesoKg = ?, aggiornatoIl = NOW() WHERE idCliente = ?',
            [round((float)$pesoValue, 2), (int)$cliente['idCliente']]
          );

          $weightSuccess = 'Peso salvato correttamente.';
        }
      } catch (Throwable $e) {
        $weightError = 'Salvataggio peso non riuscito. Riprova.';
      }
    }
  }
}

if ($dbAvailable) {
  try {
    $cliente = Database::exec('SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1', [(int)$user['idUtente']])->fetch();
    if ($cliente) {
      ensurePesoTableExists();
      $weightHistory = Database::exec(
        'SELECT dataMisurazione, pesoKg
         FROM MisurazioniPeso
         WHERE idCliente = ?
         ORDER BY dataMisurazione DESC
         LIMIT 30',
        [(int)$cliente['idCliente']]
      )->fetchAll();
    }
  } catch (Throwable $e) {
    $weightError = $weightError ?? 'Impossibile caricare lo storico peso dal database.';
  }
}

renderStart('Progressi cliente', 'progressi', $email);
?>
<section class="card hero">
  <span class="pill">Progressi</span>
  <h1>Andamento peso</h1>
  <p class="lead">Aggiungi il tuo peso e costruisci uno storico reale salvato su database.</p>
</section>

<section class="grid">
  <article class="card span-6">
    <h3 class="section-title">Registra peso</h3>
    <?php if ($weightError): ?><div class="alert" style="margin-bottom:10px"><?= h($weightError) ?></div><?php endif; ?>
    <?php if ($weightSuccess): ?><div class="okbox" style="margin-bottom:10px"><?= h($weightSuccess) ?></div><?php endif; ?>
    <form method="post" class="field" style="gap:10px">
      <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
      <input type="hidden" name="action" value="save_weight">
      <label class="field">
        <span>Data misurazione</span>
        <input type="date" name="data_misurazione" value="<?= h(date('Y-m-d')) ?>" required>
      </label>
      <label class="field">
        <span>Peso (kg)</span>
        <input type="number" name="peso" min="20" max="700" step="0.1" placeholder="Es. 74.6" required>
      </label>
      <button type="submit" class="btn primary">Salva peso</button>
    </form>
  </article>

  <article class="card span-6">
    <h3 class="section-title">Storico peso</h3>
    <?php if (!$dbAvailable): ?>
      <p class="muted"><?= h($dbError ?? 'Database non disponibile.') ?></p>
    <?php elseif (!$weightHistory): ?>
      <p class="muted">Nessuna misurazione registrata.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Data</th><th>Peso</th></tr></thead>
        <tbody>
          <?php foreach ($weightHistory as $row): ?>
            <tr>
              <td><?= h((string)$row['dataMisurazione']) ?></td>
              <td><?= number_format((float)$row['pesoKg'], 1, ',', '.') ?> kg</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </article>
</section>
<?php
renderEnd();
