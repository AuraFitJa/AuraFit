<?php
require __DIR__ . '/common.php';

$messages = [];
$errors = [];
$clientiAttivi = [];
$clientiTerminati = [];

if (!$dbAvailable) {
  $errors[] = $dbError ?? 'Database non disponibile.';
} else {
  try {
    $professionistaId = getProfessionistaId($userId);

    if (!$professionistaId) {
      $errors[] = 'Profilo professionista non trovato per questo account.';
    } else {
      $tipiConsentiti = [];
      if ($isPt) {
        $tipiConsentiti[] = 'pt';
      }
      if ($isNutrizionista) {
        $tipiConsentiti[] = 'nutrizionista';
      }

      if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'terminate_association') {
        $idAssociazione = (int)($_POST['idAssociazione'] ?? 0);
        if ($idAssociazione > 0) {
          $pdo = Database::pdo();
          $pdo->beginTransaction();
          try {
            $assocRow = Database::exec(
              'SELECT idAssociazione, idKeyOrigine, cliente, tipoAssociazione FROM Associazioni WHERE idAssociazione = ? AND professionista = ? AND attivaFlag = 1 LIMIT 1 FOR UPDATE',
              [$idAssociazione, $professionistaId]
            )->fetch();

            if ($assocRow) {
              $attivaFlagTarget = 0;
              $usedFlagsStmt = Database::exec(
                'SELECT attivaFlag FROM Associazioni WHERE cliente = ? AND tipoAssociazione = ? AND idAssociazione <> ? FOR UPDATE',
                [(int)$assocRow['cliente'], (string)$assocRow['tipoAssociazione'], $idAssociazione]
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
                [$attivaFlagTarget, $idAssociazione]
              );

              if (!empty($assocRow['idKeyOrigine'])) {
                Database::exec(
                  "UPDATE IdKey
                   SET stato = 'sospesa'
                   WHERE idKey = ? AND professionista = ? AND stato = 'attiva'",
                  [(int)$assocRow['idKeyOrigine'], $professionistaId]
                );
              }

              $pdo->commit();
              $messages[] = 'Associazione terminata con successo. Chat bloccata e ID-Key origine sospesa.';
            } else {
              $pdo->rollBack();
              $errors[] = 'Associazione non trovata o già terminata.';
            }
          } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
              $pdo->rollBack();
            }
            throw $e;
          }
        }
      }

      $placeholders = implode(',', array_fill(0, count($tipiConsentiti), '?'));
      $paramsAttivi = array_merge([$professionistaId], $tipiConsentiti);
      $attiviStmt = Database::exec(
        "SELECT a.idAssociazione, a.iniziataIl, a.tipoAssociazione, c.idCliente, u.nome, u.cognome, u.email
         FROM Associazioni a
         INNER JOIN Clienti c ON c.idCliente = a.cliente
         INNER JOIN Utenti u ON u.idUtente = c.idUtente
         WHERE a.professionista = ?
           AND a.attivaFlag = 1
           AND a.tipoAssociazione IN ($placeholders)
         ORDER BY a.iniziataIl DESC",
        $paramsAttivi
      );
      while ($row = $attiviStmt->fetch()) {
        $clientiAttivi[] = [
          'idAssociazione' => (int)$row['idAssociazione'],
          'nome' => trim((string)$row['nome'] . ' ' . (string)$row['cognome']),
          'email' => (string)$row['email'],
          'tipo' => (string)$row['tipoAssociazione'],
          'stato' => 'Attiva',
          'associazione' => (string)$row['iniziataIl'],
          'ultimoUpdate' => '—',
          'idCliente' => (int)$row['idCliente'],
        ];
      }

      $paramsTerminati = array_merge([$professionistaId], $tipiConsentiti);
      $terminatiStmt = Database::exec(
        "SELECT a.terminataIl, a.tipoAssociazione, u.nome, u.cognome, u.email
         FROM Associazioni a
         INNER JOIN Clienti c ON c.idCliente = a.cliente
         INNER JOIN Utenti u ON u.idUtente = c.idUtente
         WHERE a.professionista = ?
           AND a.attivaFlag <> 1
           AND a.tipoAssociazione IN ($placeholders)
         ORDER BY a.terminataIl DESC",
        $paramsTerminati
      );
      while ($row = $terminatiStmt->fetch()) {
        $clientiTerminati[] = [
          'nome' => trim((string)$row['nome'] . ' ' . (string)$row['cognome']),
          'email' => (string)$row['email'],
          'tipo' => (string)$row['tipoAssociazione'],
          'stato' => 'Terminata',
          'chiusura' => (string)$row['terminataIl'],
          'nota' => 'Storico disponibile lato cliente (RF-015)',
        ];
      }
    }
  } catch (Throwable $e) {
    $errors[] = 'Errore DB gestione clienti: ' . $e->getMessage();
  }
}

renderStart('Gestione Clienti', 'clienti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <style>
    .cliente-row {
      cursor: pointer;
    }

    .cliente-row:hover {
      background: rgba(255, 255, 255, 0.03);
    }
  </style>

  <h2 class="section-title">Gestione Clienti</h2>

  <?php foreach ($messages as $message): ?>
    <div class="okbox" style="margin-bottom:10px"><?= h($message) ?></div>
  <?php endforeach; ?>

  <?php foreach ($errors as $error): ?>
    <div class="alert" style="margin-bottom:10px"><?= h($error) ?></div>
  <?php endforeach; ?>

  <table>
    <thead><tr><th>Cliente</th><th>Email</th><th>Tipo</th><th>Stato</th><th>Data associazione</th><th>Ultimo aggiornamento</th><th>Azioni</th></tr></thead>
    <tbody>
      <?php if (!$clientiAttivi): ?>
        <tr><td colspan="7" class="muted">Nessun cliente attivo associato.</td></tr>
      <?php endif; ?>

      <?php foreach ($clientiAttivi as $cliente): ?>
        <tr class="cliente-row" data-href="scheda_cliente.php?idCliente=<?= (int)$cliente['idCliente'] ?>">
          <td><?= h($cliente['nome']) ?></td>
          <td><?= h($cliente['email']) ?></td>
          <td><?= h($cliente['tipo']) ?></td>
          <td><span class="status ok"><?= h($cliente['stato']) ?></span></td>
          <td><?= h($cliente['associazione']) ?></td>
          <td><?= h($cliente['ultimoUpdate']) ?></td>
          <td>
            <form method="post" style="display:inline" data-confirm-terminate-association>
              <input type="hidden" name="action" value="terminate_association" />
              <input type="hidden" name="idAssociazione" value="<?= (int)$cliente['idAssociazione'] ?>" />
              <button class="btn danger" type="submit">Termina associazione</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="divider"></div>

  <button
    id="toggleStoricoTerminati"
    class="btn"
    type="button"
    aria-expanded="false"
    aria-controls="storicoTerminati"
    style="display:inline-flex; align-items:center; gap:8px; margin-bottom:12px;"
  >
    <span id="toggleStoricoTerminatiIcon" aria-hidden="true">&gt;</span>
    <span>Storico clienti terminati</span>
  </button>

  <div id="storicoTerminati" hidden>
    <table>
      <thead><tr><th>Cliente</th><th>Email</th><th>Tipo</th><th>Stato</th><th>Data chiusura</th><th>Nota</th></tr></thead>
      <tbody>
        <?php if (!$clientiTerminati): ?>
          <tr><td colspan="6" class="muted">Nessuna associazione terminata.</td></tr>
        <?php endif; ?>
        <?php foreach ($clientiTerminati as $cliente): ?>
          <tr>
            <td><?= h($cliente['nome']) ?></td>
            <td><?= h($cliente['email']) ?></td>
            <td><?= h($cliente['tipo']) ?></td>
            <td><span class="status warn"><?= h($cliente['stato']) ?></span></td>
            <td><?= h($cliente['chiusura']) ?></td>
            <td><?= h($cliente['nota']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="profile-modal" data-terminate-association-confirm-modal aria-hidden="true">
  <div class="profile-modal-card" role="dialog" aria-modal="true" aria-labelledby="terminate-association-confirm-title" style="width:min(520px,100%);">
    <div class="profile-modal-head">
      <div>
        <h3 id="terminate-association-confirm-title" class="section-title" style="margin:0">Conferma terminazione</h3>
        <p class="muted" style="margin:8px 0 0">Confermi di voler terminare questa associazione?</p>
      </div>
    </div>
    <div class="toolbar" style="justify-content:flex-end;gap:10px;margin-top:14px">
      <button class="btn" type="button" data-terminate-association-confirm-cancel>Annulla</button>
      <button class="btn primary" type="button" data-terminate-association-confirm-ok>Conferma</button>
    </div>
  </div>
</div>

<script>
  const toggleStoricoBtn = document.getElementById('toggleStoricoTerminati');
  const storicoTerminati = document.getElementById('storicoTerminati');
  const toggleStoricoIcon = document.getElementById('toggleStoricoTerminatiIcon');

  toggleStoricoBtn?.addEventListener('click', () => {
    const isOpen = !storicoTerminati.hidden;
    storicoTerminati.hidden = isOpen;
    toggleStoricoBtn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    toggleStoricoIcon.textContent = isOpen ? '>' : 'v';
  });

  const terminateAssociationConfirmModal = document.querySelector('[data-terminate-association-confirm-modal]');
  const terminateAssociationConfirmCancel = document.querySelector('[data-terminate-association-confirm-cancel]');
  const terminateAssociationConfirmOk = document.querySelector('[data-terminate-association-confirm-ok]');
  let pendingTerminateAssociationForm = null;

  const closeTerminateAssociationConfirmModal = () => {
    terminateAssociationConfirmModal?.classList.remove('open');
    terminateAssociationConfirmModal?.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    pendingTerminateAssociationForm = null;
  };

  const openTerminateAssociationConfirmModal = (formElement) => {
    pendingTerminateAssociationForm = formElement;
    terminateAssociationConfirmModal?.classList.add('open');
    terminateAssociationConfirmModal?.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  };

  document.querySelectorAll('form[data-confirm-terminate-association]').forEach((formElement) => {
    formElement.addEventListener('submit', (event) => {
      if (formElement.dataset.confirmedSubmit === '1') {
        delete formElement.dataset.confirmedSubmit;
        return;
      }

      event.preventDefault();
      openTerminateAssociationConfirmModal(formElement);
    });
  });

  terminateAssociationConfirmCancel?.addEventListener('click', closeTerminateAssociationConfirmModal);
  terminateAssociationConfirmModal?.addEventListener('click', (event) => {
    if (event.target === terminateAssociationConfirmModal) {
      closeTerminateAssociationConfirmModal();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && terminateAssociationConfirmModal?.classList.contains('open')) {
      closeTerminateAssociationConfirmModal();
    }
  });

  terminateAssociationConfirmOk?.addEventListener('click', () => {
    if (!pendingTerminateAssociationForm) {
      closeTerminateAssociationConfirmModal();
      return;
    }

    pendingTerminateAssociationForm.dataset.confirmedSubmit = '1';
    pendingTerminateAssociationForm.requestSubmit();
    closeTerminateAssociationConfirmModal();
  });

  document.querySelectorAll('.cliente-row').forEach((row) => {
    row.addEventListener('click', function (e) {
      if (e.target.closest('button') || e.target.closest('a')) return;

      const url = this.dataset.href;
      if (url) {
        window.location.href = url;
      }
    });
  });
</script>
<?php
renderEnd();
