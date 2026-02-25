<?php
require __DIR__ . '/common.php';

$messages = [];
$errors = [];
$idKeys = [];
$canGenerateIdKey = false;
$limiteClienti = null;
$clientiAttiviCount = 0;

if (!$dbAvailable) {
  $errors[] = $dbError ?? 'Database non disponibile.';
} else {
  try {
    $professionistaId = getProfessionistaId($userId);

    if (!$professionistaId) {
      $errors[] = 'Profilo professionista non trovato per questo account.';
    } else {
      $sub = Database::exec(
        "SELECT a.stato, p.limiteClienti
         FROM Abbonamenti a
         INNER JOIN PianiAbbonamento p ON p.idPiano = a.piano
         WHERE a.utente = ?
         ORDER BY a.idAbbonamento DESC
         LIMIT 1",
        [$userId]
      )->fetch();

      if ($sub) {
        $limiteClienti = isset($sub['limiteClienti']) ? (int)$sub['limiteClienti'] : null;
      }

      $countRow = Database::exec(
        'SELECT COUNT(*) AS c FROM Associazioni WHERE professionista = ? AND attivaFlag = 1',
        [$professionistaId]
      )->fetch();
      $clientiAttiviCount = (int)($countRow['c'] ?? 0);

      $canGenerateIdKey = $limiteClienti === null || $clientiAttiviCount < $limiteClienti;

      if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'generate_idkey') {
        $tipoInput = strtolower((string)($_POST['tipo'] ?? ''));
        $allowedTypes = [];
        if ($isPt) {
          $allowedTypes[] = 'pt';
        }
        if ($isNutrizionista) {
          $allowedTypes[] = 'nutrizionista';
        }

        if (!in_array($tipoInput, $allowedTypes, true)) {
          $errors[] = 'Tipo ID-Key non valido per il tuo ruolo.';
        } elseif (!$canGenerateIdKey) {
          $errors[] = 'Limite clienti piano raggiunto: impossibile generare una nuova ID-Key (RF-018).';
        } else {
          $prefix = $tipoInput === 'pt' ? 'PT' : 'NU';
          $generated = null;
          for ($i = 0; $i < 10; $i++) {
            $candidate = 'AF-' . $prefix . '-' . strtoupper(bin2hex(random_bytes(3)));
            $exists = Database::exec('SELECT idKey FROM IdKey WHERE codice = ? LIMIT 1', [$candidate])->fetch();
            if (!$exists) {
              $generated = $candidate;
              break;
            }
          }

          if ($generated === null) {
            $errors[] = 'Impossibile generare un codice univoco. Riprova.';
          } else {
            Database::exec(
              'INSERT INTO IdKey (codice, professionista, tipoKey, stato) VALUES (?, ?, ?, ?)',
              [$generated, $professionistaId, $tipoInput, 'attiva']
            );
            $messages[] = 'Nuova ID-Key generata: ' . $generated;
          }
        }
      }

      $keysStmt = Database::exec(
        'SELECT idKey, codice, stato, tipoKey FROM IdKey WHERE professionista = ? ORDER BY idKey DESC',
        [$professionistaId]
      );
      while ($row = $keysStmt->fetch()) {
        $idKeys[] = [
          'idKey' => (int)$row['idKey'],
          'key' => (string)$row['codice'],
          'stato' => (string)$row['stato'],
          'tipo' => (string)$row['tipoKey'],
        ];
      }
    }
  } catch (Throwable $e) {
    $errors[] = 'Errore DB gestione ID-Key: ' . $e->getMessage();
  }
}

renderStart('Gestione ID-Key', 'idkey', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <h2 class="section-title">Gestione ID-Key (RF-020, RF-021, RF-018)</h2>

  <?php foreach ($messages as $message): ?>
    <div class="okbox" style="margin-bottom:10px"><?= h($message) ?></div>
  <?php endforeach; ?>

  <?php foreach ($errors as $error): ?>
    <div class="alert" style="margin-bottom:10px"><?= h($error) ?></div>
  <?php endforeach; ?>

  <div class="toolbar">
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
      <input type="hidden" name="action" value="generate_idkey" />
      <?php if ($isPt && $isNutrizionista): ?>
        <select name="tipo" class="field" style="padding:10px 12px;border-radius:12px;background:rgba(255,255,255,.03);color:var(--text);border:1px solid rgba(255,255,255,.12)">
          <option value="pt">PT</option>
          <option value="nutrizionista">Nutrizionista</option>
        </select>
      <?php elseif ($isPt): ?>
        <input type="hidden" name="tipo" value="pt" />
      <?php else: ?>
        <input type="hidden" name="tipo" value="nutrizionista" />
      <?php endif; ?>
      <button class="btn primary" type="submit" <?= $canGenerateIdKey ? '' : 'disabled' ?>>Genera nuova ID-Key</button>
    </form>

    <span class="muted">
      Clienti attivi: <?= (int)$clientiAttiviCount ?>
      <?php if ($limiteClienti !== null): ?>
        / limite piano: <?= (int)$limiteClienti ?>
      <?php else: ?>
        / limite piano: illimitato
      <?php endif; ?>
    </span>
  </div>

  <table>
    <thead><tr><th>ID-Key</th><th>Tipo</th><th>Stato</th></tr></thead>
    <tbody>
      <?php if (!$idKeys): ?>
        <tr><td colspan="3" class="muted">Nessuna ID-Key presente.</td></tr>
      <?php endif; ?>
      <?php foreach ($idKeys as $key): ?>
        <?php
          $status = strtolower($key['stato']);
          $statusClass = $status === 'attiva' ? 'ok' : ($status === 'sospesa' ? 'warn' : 'danger');
        ?>
        <tr>
          <td><code><?= h($key['key']) ?></code></td>
          <td><?= h($key['tipo']) ?></td>
          <td><span class="status <?= $statusClass ?>"><?= h($key['stato']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php
renderEnd();