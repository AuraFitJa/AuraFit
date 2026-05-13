<?php
require __DIR__ . '/common.php';

$weightError = null;
$weightSuccess = null;
$weightHistory = [];
$defaultDateTime = date('Y-m-d\TH:i');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save_weight')) {
  if (!aurafit_validate_csrf_token(aurafit_request_csrf_token())) {
    $weightError = 'Richiesta non valida (CSRF).';
  } elseif (!$dbAvailable) {
    $weightError = $dbError ?? 'Database non disponibile.';
  } else {
    $pesoInput = trim((string)($_POST['peso'] ?? ''));
    $dateTimeInput = trim((string)($_POST['misurata_il'] ?? $defaultDateTime));
    $pesoValue = str_replace(',', '.', $pesoInput);

    $parsedTs = strtotime(str_replace('T', ' ', $dateTimeInput));
    if ($pesoValue === '' || !is_numeric($pesoValue) || (float)$pesoValue < 20 || (float)$pesoValue > 700) {
      $weightError = 'Inserisci un peso valido tra 20 e 700 kg.';
    } elseif ($parsedTs === false) {
      $weightError = 'Data e ora misurazione non valide.';
    } else {
      try {
        $cliente = Database::exec(
          'SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1',
          [(int)$user['idUtente']]
        )->fetch();

        if (!$cliente) {
          $weightError = 'Profilo cliente non collegato all\'utente.';
        } else {
          $misurataIl = date('Y-m-d H:i:s', $parsedTs);
          Database::exec(
            'INSERT INTO Misurazioni (cliente, tipoMisura, valore, unita, misurataIl)
             VALUES (?, ?, ?, ?, ?)',
            [(int)$cliente['idCliente'], 'peso', round((float)$pesoValue, 2), 'kg', $misurataIl]
          );

          Database::exec(
            'INSERT INTO ProfiloCliente (idCliente, pesoKg, creatoIl, aggiornatoIl)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               pesoKg = VALUES(pesoKg),
               aggiornatoIl = NOW()',
            [(int)$cliente['idCliente'], round((float)$pesoValue, 2)]
          );

          $weightSuccess = 'Peso salvato correttamente.';
          $defaultDateTime = date('Y-m-d\TH:i');
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
      $weightHistory = Database::exec(
        "SELECT misurataIl, valore
         FROM Misurazioni
         WHERE cliente = ? AND LOWER(tipoMisura) = 'peso'
         ORDER BY misurataIl DESC
         LIMIT 30",
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
  <p class="lead">Aggiungi il tuo peso con data e ora: lo storico viene salvato su database reale.</p>
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
        <span>Data e ora misurazione</span>
        <input type="datetime-local" name="misurata_il" value="<?= h($defaultDateTime) ?>" required>
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
        <thead><tr><th>Data e ora</th><th>Peso</th></tr></thead>
        <tbody>
          <?php foreach ($weightHistory as $row): ?>
            <tr>
              <td><?= h(date('Y-m-d H:i', strtotime((string)$row['misurataIl']))) ?></td>
              <td><?= number_format((float)$row['valore'], 1, ',', '.') ?> kg</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </article>
</section>
<?php
renderEnd();
