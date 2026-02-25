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

      if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)($_POST['action'] ?? ''), ['reactivate_idkey', 'delete_idkey'], true)) {
        $idKeyInput = (int)($_POST['idKey'] ?? 0);
        $actionInput = (string)($_POST['action'] ?? '');

        if ($idKeyInput <= 0) {
          $errors[] = 'ID-Key non valida.';
        } else {
          $pdo = Database::pdo();
          $pdo->beginTransaction();
          try {
            $keyRow = Database::exec(
              'SELECT idKey, stato FROM IdKey WHERE idKey = ? AND professionista = ? LIMIT 1 FOR UPDATE',
              [$idKeyInput, $professionistaId]
            )->fetch();

            if (!$keyRow) {
              $pdo->rollBack();
              $errors[] = 'ID-Key non trovata per questo professionista.';
            } else {
              $currentState = strtolower((string)$keyRow['stato']);
              if ($actionInput === 'reactivate_idkey') {
                if ($currentState === 'eliminata') {
                  $pdo->rollBack();
                  $errors[] = 'Una ID-Key eliminata non può essere riattivata.';
                } elseif ($currentState === 'attiva') {
                  $pdo->rollBack();
                  $messages[] = 'La ID-Key è già attiva.';
                } else {
                  Database::exec(
                    "UPDATE IdKey SET stato = 'attiva' WHERE idKey = ? AND professionista = ?",
                    [$idKeyInput, $professionistaId]
                  );
                  $pdo->commit();
                  $messages[] = 'ID-Key riattivata con successo.';
                }
              }

              if ($actionInput === 'delete_idkey') {
                if ($currentState === 'eliminata') {
                  $pdo->rollBack();
                  $messages[] = 'La ID-Key è già eliminata.';
                } else {
                  $activeAssocStmt = Database::exec(
                    'SELECT idAssociazione, cliente, tipoAssociazione FROM Associazioni WHERE idKeyOrigine = ? AND professionista = ? AND attivaFlag = 1 FOR UPDATE',
                    [$idKeyInput, $professionistaId]
                  );

                  while ($assocRow = $activeAssocStmt->fetch()) {
                    $attivaFlagTarget = 0;
                    $usedFlagsStmt = Database::exec(
                      'SELECT attivaFlag FROM Associazioni WHERE cliente = ? AND tipoAssociazione = ? AND idAssociazione <> ? FOR UPDATE',
                      [(int)$assocRow['cliente'], (string)$assocRow['tipoAssociazione'], (int)$assocRow['idAssociazione']]
                    );
                    $usedFlags = [];
                    while ($flagRow = $usedFlagsStmt->fetch()) {
                      $usedFlags[(int)$flagRow['attivaFlag']] = true;
                    }

                    if (isset($usedFlags[$attivaFlagTarget])) {
                      $attivaFlagTarget = 2;
                      while (isset($usedFlags[$attivaFlagTarget])) {
                        $attivaFlagTarget++;
                      }
                    }

                    Database::exec(
                      "UPDATE Associazioni
                       SET attivaFlag = ?,
                           stato = 'terminata',
                           terminataIl = NOW()
                       WHERE idAssociazione = ?",
                      [$attivaFlagTarget, (int)$assocRow['idAssociazione']]
                    );
                  }

                  Database::exec(
                    "UPDATE IdKey SET stato = 'eliminata' WHERE idKey = ? AND professionista = ?",
                    [$idKeyInput, $professionistaId]
                  );
                  $pdo->commit();
                  $messages[] = 'ID-Key eliminata con successo. Eventuali associazioni attive collegate sono state terminate.';
                }
              }
            }
          } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
              $pdo->rollBack();
            }
            throw $e;
          }
        }
      }

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
    <thead><tr><th>ID-Key</th><th>Tipo</th><th>Stato</th><th>Azioni</th></tr></thead>
    <tbody>
      <?php if (!$idKeys): ?>
        <tr><td colspan="4" class="muted">Nessuna ID-Key presente.</td></tr>
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
          <td>
            <?php if ($status !== 'eliminata'): ?>
              <?php if ($status !== 'attiva'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="reactivate_idkey" />
                  <input type="hidden" name="idKey" value="<?= (int)$key['idKey'] ?>" />
                  <button class="btn" type="submit">Riattiva</button>
                </form>
              <?php endif; ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="delete_idkey" />
                <input type="hidden" name="idKey" value="<?= (int)$key['idKey'] ?>" />
                <button class="btn danger" type="submit">Elimina</button>
              </form>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php
renderEnd();