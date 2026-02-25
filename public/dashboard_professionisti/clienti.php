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
          Database::exec(
            "UPDATE Associazioni
             SET attivaFlag = 0,
                 stato = 'terminata',
                 terminataIl = NOW()
             WHERE idAssociazione = ? AND professionista = ? AND attivaFlag = 1",
            [$idAssociazione, $professionistaId]
          );
          $messages[] = 'Associazione terminata con successo. La chat verrà bloccata automaticamente (RF-016).';
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
           AND a.attivaFlag = 0
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
  <h2 class="section-title">Gestione Clienti (RF-004, RF-013, RF-014)</h2>

  <?php foreach ($messages as $message): ?>
    <div class="okbox" style="margin-bottom:10px"><?= h($message) ?></div>
  <?php endforeach; ?>

  <?php foreach ($errors as $error): ?>
    <div class="alert" style="margin-bottom:10px"><?= h($error) ?></div>
  <?php endforeach; ?>

  <div class="toolbar">
    <span class="muted">Alla cessazione associazione la chat viene bloccata automaticamente (RF-016). Lo storico resta visibile al cliente in caso cambio professionista (RF-015).</span>
  </div>

  <table>
    <thead><tr><th>Cliente</th><th>Email</th><th>Tipo</th><th>Stato associazione</th><th>Data associazione</th><th>Ultimo aggiornamento</th><th>Azioni</th></tr></thead>
    <tbody>
      <?php if (!$clientiAttivi): ?>
        <tr><td colspan="7" class="muted">Nessun cliente attivo associato.</td></tr>
      <?php endif; ?>

      <?php foreach ($clientiAttivi as $cliente): ?>
        <tr>
          <td><?= h($cliente['nome']) ?></td>
          <td><?= h($cliente['email']) ?></td>
          <td><?= h($cliente['tipo']) ?></td>
          <td><span class="status ok"><?= h($cliente['stato']) ?></span></td>
          <td><?= h($cliente['associazione']) ?></td>
          <td><?= h($cliente['ultimoUpdate']) ?></td>
          <td>
            <a class="btn" href="../dashboard_cliente.php?idCliente=<?= (int)$cliente['idCliente'] ?>">Scheda cliente</a>
            <form method="post" style="display:inline">
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

  <h3>Storico clienti terminati</h3>
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
</section>
<?php
renderEnd();
