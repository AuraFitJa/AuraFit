<?php
require __DIR__ . '/common.php';

$messages = [];
$errors = [];
$generatedIdKey = null;
$idKeys = [];
$idKeysEliminate = [];
$canGenerateIdKey = false;
$limiteClienti = null;
$clientiAttiviCount = 0;
$professionistaRuoloLabel = 'Professionista';

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
            $generatedIdKey = $generated;
          }
        }
      }

      $keysStmt = Database::exec(
        "SELECT k.idKey, k.codice, k.stato, k.tipoKey,
                (
                  SELECT TRIM(CONCAT(COALESCE(u.nome, ''), ' ', COALESCE(u.cognome, '')))
                  FROM Associazioni a
                  INNER JOIN Clienti c ON c.idCliente = a.cliente
                  INNER JOIN Utenti u ON u.idUtente = c.idUtente
                  WHERE a.idKeyOrigine = k.idKey
                    AND a.professionista = ?
                    AND a.attivaFlag = 1
                  ORDER BY a.idAssociazione DESC
                  LIMIT 1
                ) AS clienteCollegato
         FROM IdKey k
         WHERE k.professionista = ?
         ORDER BY k.idKey DESC",
        [$professionistaId, $professionistaId]
      );
      while ($row = $keysStmt->fetch()) {
        $clienteCollegato = trim((string)($row['clienteCollegato'] ?? ''));
        $keyData = [
          'idKey' => (int)$row['idKey'],
          'key' => (string)$row['codice'],
          'stato' => (string)$row['stato'],
          'tipo' => (string)$row['tipoKey'],
          'clienteCollegato' => $clienteCollegato !== '' ? $clienteCollegato : 'Nessun cliente collegato',
        ];

        if (strtolower((string)$row['stato']) === 'eliminata') {
          $idKeysEliminate[] = $keyData;
        } else {
          $idKeys[] = $keyData;
        }
      }
    }
  } catch (Throwable $e) {
    $errors[] = 'Errore DB gestione ID-Key: ' . $e->getMessage();
  }
}

$professionistaRuoloLabel = ($isPt && $isNutrizionista)
  ? 'PT + Nutrizionista'
  : ($isPt ? 'PT' : ($isNutrizionista ? 'Nutrizionista' : 'Professionista'));

$idKeysDisponibiliCount = 0;
foreach ($idKeys as $mobileKeyRow) {
  if (strtolower((string)($mobileKeyRow['stato'] ?? '')) === 'attiva' && ($mobileKeyRow['clienteCollegato'] ?? '') === 'Nessun cliente collegato') {
    $idKeysDisponibiliCount++;
  }
}

$idKeysTotaliCount = count($idKeys) + count($idKeysEliminate);

renderStart('Gestione ID-Key', 'idkey', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<style>
  .idkey-mobile-shell { display: none; }
  #toggleIdKeyEliminate { display: inline-flex; }
  #storicoIdKeyEliminate[hidden] { display: none !important; }
  @media (max-width: 820px) {
    html, body { overflow-x: hidden; }
    *, *::before, *::after { box-sizing: border-box; }
    .card > .section-title, .card > .toolbar, .card > table, .card > .divider, .card > #storicoIdKeyEliminate { display: none; }
    #toggleIdKeyEliminate { display: none !important; }
    .idkey-mobile-shell { max-width: 100%; min-width: 0; padding: 10px 0 94px; }
    .idkey-mobile-shell { display: block; }
    .idkey-mobile-title, .idkey-mobile-summary, .idkey-mobile-card, .idkey-mobile-key-card, .idkey-mobile-history { background:#0f172a; border:1px solid rgba(255,255,255,.08); border-radius:16px; padding:12px; margin-bottom:10px; min-width:0; }
    .idkey-mobile-title .label { margin:0 0 6px; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#9ca3af; }
    .idkey-mobile-title h3 { margin:0; font-size:24px; }
    .idkey-mobile-title p { margin:8px 0 0; color:#cbd5e1; font-size:14px; }
    .idkey-mobile-summary-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
    .idkey-mobile-summary .metric-label { display:block; font-size:11px; color:#94a3b8; margin-bottom:3px; }
    .idkey-mobile-summary .metric-value { font-size:18px; font-weight:600; color:#f8fafc; }
    .idkey-mobile-plan { margin-top:10px; padding-top:10px; border-top:1px solid rgba(255,255,255,.08); color:#cbd5e1; font-size:13px; }
    .idkey-mobile-card h4, .idkey-mobile-history summary { margin:0 0 10px; font-size:16px; }
    .idkey-mobile-form { display:grid; gap:10px; }
    .idkey-mobile-form .field { height:44px; padding:0 12px; border-radius:12px; width:100%; min-width:0; }
    .idkey-mobile-form .btn { width:100%; border-radius:12px; }
    .idkey-mobile-key-card-head, .idkey-mobile-key-card-sub, .idkey-mobile-key-card-footer { display:flex; align-items:center; justify-content:space-between; gap:8px; min-width:0; }
    .idkey-mobile-key-code { margin:0; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .idkey-mobile-subtext { margin:6px 0 10px; color:#cbd5e1; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .idkey-mobile-badge { padding:3px 8px; border-radius:999px; font-size:11px; text-transform:lowercase; border:1px solid transparent; }
    .idkey-mobile-badge.attiva { background:rgba(34,197,94,.15); border-color:rgba(34,197,94,.4); color:#bbf7d0; }
    .idkey-mobile-badge.disponibile { background:rgba(6,182,212,.14); border-color:rgba(6,182,212,.4); color:#a5f3fc; }
    .idkey-mobile-badge.eliminata { background:rgba(148,163,184,.16); border-color:rgba(148,163,184,.35); color:#e2e8f0; }
    .idkey-mobile-key-card-footer .muted { font-size:12px; }
    .idkey-mobile-actions { display:flex; gap:8px; flex-shrink:0; }
    .idkey-mobile-actions .btn { padding:6px 10px; border-radius:10px; font-size:12px; }
    .idkey-mobile-history details { margin:0; }
    .idkey-mobile-history ul { list-style:none; margin:8px 0 0; padding:0; display:grid; gap:8px; }
    .idkey-mobile-history li { border-top:1px solid rgba(255,255,255,.08); padding-top:8px; }
  }
</style>
<section class="card">
  <h2 class="section-title">Gestione ID-Key</h2>

  <?php foreach ($messages as $message): ?>
    <div class="okbox" style="margin-bottom:10px"><?= h($message) ?></div>
  <?php endforeach; ?>

  <?php foreach ($errors as $error): ?>
    <div class="alert" style="margin-bottom:10px"><?= h($error) ?></div>
  <?php endforeach; ?>

  <div class="idkey-mobile-shell">
    <section class="idkey-mobile-title">
      <p class="label">Gestione accessi</p>
      <h3>ID-Key</h3>
      <p>Genera e gestisci i codici di collegamento dei tuoi clienti.</p>
    </section>

    <section class="idkey-mobile-summary">
      <div class="idkey-mobile-summary-grid">
        <div><span class="metric-label">Clienti</span><span class="metric-value"><?= (int)$clientiAttiviCount ?></span></div>
        <div><span class="metric-label">Disponibili</span><span class="metric-value"><?= (int)$idKeysDisponibiliCount ?></span></div>
        <div><span class="metric-label">Totali</span><span class="metric-value"><?= (int)$idKeysTotaliCount ?></span></div>
      </div>
      <div class="idkey-mobile-plan">Limite piano: <?= $limiteClienti !== null ? (int)$limiteClienti : 'illimitato' ?></div>
    </section>

    <section class="idkey-mobile-card">
      <h4>Nuova ID-Key</h4>
      <form method="post" class="idkey-mobile-form">
        <input type="hidden" name="action" value="generate_idkey" />
        <?php if ($isPt && $isNutrizionista): ?>
          <select name="tipo" class="field"><option value="pt">PT</option><option value="nutrizionista">Nutrizionista</option></select>
        <?php elseif ($isPt): ?>
          <input type="hidden" name="tipo" value="pt" />
        <?php else: ?>
          <input type="hidden" name="tipo" value="nutrizionista" />
        <?php endif; ?>
        <button class="btn primary" type="submit" <?= $canGenerateIdKey ? '' : 'disabled' ?>>Genera nuova ID-Key</button>
      </form>
    </section>

    <?php if (!$idKeys): ?>
      <section class="idkey-mobile-card"><p class="muted" style="margin:0">Nessuna ID-Key presente.</p></section>
    <?php endif; ?>

    <?php foreach ($idKeys as $key): ?>
      <?php
        $mobileStatus = strtolower((string)$key['stato']);
        $isDisponibile = $mobileStatus === 'attiva' && ($key['clienteCollegato'] ?? '') === 'Nessun cliente collegato';
        $badgeText = $isDisponibile ? 'disponibile' : ($mobileStatus === 'eliminata' ? 'eliminata' : 'attiva');
      ?>
      <section class="idkey-mobile-key-card">
        <div class="idkey-mobile-key-card-head">
          <p class="idkey-mobile-key-code"><?= h($key['key']) ?></p>
          <span class="idkey-mobile-badge <?= h($badgeText) ?>"><?= h($badgeText) ?></span>
        </div>
        <p class="idkey-mobile-subtext"><?= h(strtoupper((string)$key['tipo'])) ?> · <?= h($key['clienteCollegato']) ?></p>
        <div class="idkey-mobile-key-card-footer">
          <div class="idkey-mobile-actions" style="margin-left:auto">
            <button class="btn" type="button" data-copy-idkey="<?= h($key['key']) ?>">Copia</button>
            <?php if ($mobileStatus !== 'eliminata'): ?>
              <form method="post" data-confirm-delete-idkey>
                <input type="hidden" name="action" value="delete_idkey" />
                <input type="hidden" name="idKey" value="<?= (int)$key['idKey'] ?>" />
                <button class="btn danger" type="submit">Elimina</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>

    <section class="idkey-mobile-history">
      <details>
        <summary>Storico ID-Key eliminate</summary>
        <ul>
          <?php if (!$idKeysEliminate): ?>
            <li><span class="muted">Nessuna ID-Key eliminata.</span></li>
          <?php endif; ?>
          <?php foreach ($idKeysEliminate as $key): ?>
            <li><code><?= h($key['key']) ?></code><br><span class="muted"><?= h(strtoupper((string)$key['tipo'])) ?> · eliminata</span></li>
          <?php endforeach; ?>
        </ul>
      </details>
    </section>
  </div>

  <div class="toolbar">
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
      <input type="hidden" name="action" value="generate_idkey" />
      <?php if ($isPt && $isNutrizionista): ?>
        <select name="tipo" class="field" style="padding:10px 12px;border-radius:12px;background:#0b1220;color:var(--text);border:1px solid rgba(255,255,255,.12)">
          <option value="pt" style="background:#0b1220;color:var(--text)">PT</option>
          <option value="nutrizionista" style="background:#0b1220;color:var(--text)">Nutrizionista</option>
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
    <thead><tr><th>ID-Key</th><th>Tipo</th><th>Cliente collegato</th><th>Stato</th><th>Azioni</th></tr></thead>
    <tbody>
      <?php if (!$idKeys): ?>
        <tr><td colspan="5" class="muted">Nessuna ID-Key presente.</td></tr>
      <?php endif; ?>
      <?php foreach ($idKeys as $key): ?>
        <?php
          $status = strtolower($key['stato']);
          $statusClass = $status === 'attiva' ? 'ok' : ($status === 'sospesa' ? 'warn' : 'danger');
        ?>
        <tr>
          <td><code><?= h($key['key']) ?></code></td>
          <td><?= h($key['tipo']) ?></td>
          <td><?= h($key['clienteCollegato']) ?></td>
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
              <form method="post" style="display:inline" data-confirm-delete-idkey>
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

  <div class="divider"></div>


  <button
    id="toggleIdKeyEliminate"
    class="btn"
    type="button"
    aria-expanded="false"
    aria-controls="storicoIdKeyEliminate"
    style="display:inline-flex; align-items:center; gap:8px; margin-bottom:12px;"
  >
    <span id="toggleIdKeyEliminateIcon" aria-hidden="true">&gt;</span>
    <span>Storico ID-Key terminate</span>
  </button>

  <div id="storicoIdKeyEliminate" hidden>
    <table>
      <thead><tr><th>ID-Key</th><th>Tipo</th><th>Cliente collegato</th><th>Stato</th><th>Azioni</th></tr></thead>
      <tbody>
        <?php if (!$idKeysEliminate): ?>
          <tr><td colspan="5" class="muted">Nessuna ID-Key eliminata.</td></tr>
        <?php endif; ?>
        <?php foreach ($idKeysEliminate as $key): ?>
          <tr>
            <td><code><?= h($key['key']) ?></code></td>
            <td><?= h($key['tipo']) ?></td>
            <td><?= h($key['clienteCollegato']) ?></td>
            <td><span class="status danger"><?= h($key['stato']) ?></span></td>
            <td><span class="muted">—</span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($generatedIdKey !== null): ?>
  <div class="profile-modal open" data-idkey-generated-modal aria-hidden="false">
    <div class="profile-modal-card" role="dialog" aria-modal="true" aria-labelledby="idkey-generated-title" style="width:min(520px,100%);">
      <div class="profile-modal-head">
        <div>
          <h3 id="idkey-generated-title" class="section-title" style="margin:0">ID-Key generata con successo</h3>
          <p class="muted" style="margin:8px 0 0">La nuova ID-Key è pronta per essere condivisa con il cliente.</p>
        </div>
      </div>
      <div style="margin-top:14px">
        <label class="muted" for="generatedIdKeyField" style="display:block;margin-bottom:6px">Nuova ID-Key</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <input id="generatedIdKeyField" class="field" type="text" value="<?= h($generatedIdKey) ?>" readonly style="flex:1;min-width:220px;background:rgba(11,18,32,.92);border:1px solid rgba(255,255,255,.14);border-radius:12px;color:var(--text);font-weight:700;letter-spacing:.03em;box-shadow:inset 0 1px 0 rgba(255,255,255,.05);" />
          <button class="btn" type="button" data-copy-generated-idkey>Copia key</button>
        </div>
        <p class="muted" data-generated-idkey-feedback style="margin:8px 0 0;min-height:20px"></p>
      </div>
      <div class="toolbar" style="justify-content:flex-end;gap:10px;margin-top:14px">
        <button class="btn primary" type="button" data-close-generated-idkey>Chiudi</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="profile-modal" data-idkey-confirm-modal aria-hidden="true">
  <div class="profile-modal-card" role="dialog" aria-modal="true" aria-labelledby="idkey-confirm-title" style="width:min(520px,100%);">
    <div class="profile-modal-head">
      <div>
        <h3 id="idkey-confirm-title" class="section-title" style="margin:0">Conferma eliminazione</h3>
        <p class="muted" style="margin:8px 0 0">Confermi di voler eliminare questa ID-Key? L'operazione terminerà anche le eventuali associazioni attive collegate.</p>
      </div>
    </div>
    <div class="toolbar" style="justify-content:flex-end;gap:10px;margin-top:14px">
      <button class="btn" type="button" data-idkey-confirm-cancel>Annulla</button>
      <button class="btn primary" type="button" data-idkey-confirm-ok>Conferma</button>
    </div>
  </div>
</div>
<script>
  const toggleIdKeyEliminateBtn = document.getElementById('toggleIdKeyEliminate');
  const storicoIdKeyEliminate = document.getElementById('storicoIdKeyEliminate');
  const toggleIdKeyEliminateIcon = document.getElementById('toggleIdKeyEliminateIcon');

  toggleIdKeyEliminateBtn?.addEventListener('click', () => {
    if (!storicoIdKeyEliminate) return;
    const isOpen = !storicoIdKeyEliminate.hidden;
    storicoIdKeyEliminate.hidden = isOpen;
    toggleIdKeyEliminateBtn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    if (toggleIdKeyEliminateIcon) {
      toggleIdKeyEliminateIcon.textContent = isOpen ? '>' : 'v';
    }
  });

  const idKeyConfirmModal = document.querySelector('[data-idkey-confirm-modal]');
  const idKeyConfirmCancel = document.querySelector('[data-idkey-confirm-cancel]');
  const idKeyConfirmOk = document.querySelector('[data-idkey-confirm-ok]');
  let pendingDeleteIdKeyForm = null;

  const closeIdKeyConfirmModal = () => {
    idKeyConfirmModal?.classList.remove('open');
    idKeyConfirmModal?.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    pendingDeleteIdKeyForm = null;
  };

  const openIdKeyConfirmModal = (formElement) => {
    pendingDeleteIdKeyForm = formElement;
    idKeyConfirmModal?.classList.add('open');
    idKeyConfirmModal?.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  };

  document.querySelectorAll('form[data-confirm-delete-idkey]').forEach((formElement) => {
    formElement.addEventListener('submit', (event) => {
      if (formElement.dataset.confirmedSubmit === '1') {
        delete formElement.dataset.confirmedSubmit;
        return;
      }

      event.preventDefault();
      openIdKeyConfirmModal(formElement);
    });
  });

  idKeyConfirmCancel?.addEventListener('click', closeIdKeyConfirmModal);
  idKeyConfirmModal?.addEventListener('click', (event) => {
    if (event.target === idKeyConfirmModal) {
      closeIdKeyConfirmModal();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && idKeyConfirmModal?.classList.contains('open')) {
      closeIdKeyConfirmModal();
    }
  });

  idKeyConfirmOk?.addEventListener('click', () => {
    if (!pendingDeleteIdKeyForm) {
      closeIdKeyConfirmModal();
      return;
    }

    pendingDeleteIdKeyForm.dataset.confirmedSubmit = '1';
    pendingDeleteIdKeyForm.requestSubmit();
    closeIdKeyConfirmModal();
  });

  const idKeyGeneratedModal = document.querySelector('[data-idkey-generated-modal]');
  const generatedIdKeyField = document.getElementById('generatedIdKeyField');
  const copyGeneratedIdKeyBtn = document.querySelector('[data-copy-generated-idkey]');
  const closeGeneratedIdKeyBtn = document.querySelector('[data-close-generated-idkey]');
  const generatedIdKeyFeedback = document.querySelector('[data-generated-idkey-feedback]');

  const closeGeneratedIdKeyModal = () => {
    idKeyGeneratedModal?.classList.remove('open');
    idKeyGeneratedModal?.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  };

  if (idKeyGeneratedModal?.classList.contains('open')) {
    document.body.style.overflow = 'hidden';
    generatedIdKeyField?.focus();
    generatedIdKeyField?.select();
  }

  closeGeneratedIdKeyBtn?.addEventListener('click', closeGeneratedIdKeyModal);
  idKeyGeneratedModal?.addEventListener('click', (event) => {
    if (event.target === idKeyGeneratedModal) {
      closeGeneratedIdKeyModal();
    }
  });

  copyGeneratedIdKeyBtn?.addEventListener('click', async () => {
    if (!generatedIdKeyField) {
      return;
    }

    const keyValue = generatedIdKeyField.value;
    let copied = false;

    if (navigator.clipboard?.writeText) {
      try {
        await navigator.clipboard.writeText(keyValue);
        copied = true;
      } catch (error) {
        copied = false;
      }
    }

    if (!copied) {
      generatedIdKeyField.focus();
      generatedIdKeyField.select();
      copied = document.execCommand('copy');
    }

    if (generatedIdKeyFeedback) {
      generatedIdKeyFeedback.textContent = copied
        ? 'ID-Key copiata negli appunti.'
        : 'Copia non riuscita. Seleziona la key e copia manualmente.';
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && idKeyGeneratedModal?.classList.contains('open')) {
      closeGeneratedIdKeyModal();
    }
  });

  document.querySelectorAll('[data-copy-idkey]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const keyValue = btn.getAttribute('data-copy-idkey') || '';
      if (!keyValue) return;
      let copied = false;
      if (navigator.clipboard?.writeText) {
        try {
          await navigator.clipboard.writeText(keyValue);
          copied = true;
        } catch (error) {
          copied = false;
        }
      }
      if (!copied) {
        const tempInput = document.createElement('input');
        tempInput.value = keyValue;
        document.body.appendChild(tempInput);
        tempInput.select();
        copied = document.execCommand('copy');
        tempInput.remove();
      }
      const oldLabel = btn.textContent;
      btn.textContent = copied ? 'Copiato' : 'Riprova';
      setTimeout(() => {
        btn.textContent = oldLabel;
      }, 1200);
    });
  });
</script>
<?php
renderEnd();
