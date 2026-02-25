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

      $attiviStmt = Database::exec(
        "SELECT a.idAssociazione, a.iniziataIl, c.idCliente, u.nome, u.cognome
         FROM Associazioni a
         INNER JOIN Clienti c ON c.idCliente = a.cliente
         INNER JOIN Utenti u ON u.idUtente = c.idUtente
         WHERE a.professionista = ? AND a.attivaFlag = 1
         ORDER BY a.iniziataIl DESC",
        [$professionistaId]
      );
      while ($row = $attiviStmt->fetch()) {
        $clientiAttivi[] = [
          'idAssociazione' => (int)$row['idAssociazione'],
          'nome' => trim((string)$row['nome'] . ' ' . (string)$row['cognome']),
          'stato' => 'Attiva',
          'associazione' => (string)$row['iniziataIl'],
          'ultimoUpdate' => '—',
          'idCliente' => (int)$row['idCliente'],
        ];
      }

      $terminatiStmt = Database::exec(
        "SELECT a.terminataIl, u.nome, u.cognome
         FROM Associazioni a
         INNER JOIN Clienti c ON c.idCliente = a.cliente
         INNER JOIN Utenti u ON u.idUtente = c.idUtente
         WHERE a.professionista = ? AND a.attivaFlag = 0
         ORDER BY a.terminataIl DESC",
        [$professionistaId]
      );
      while ($row = $terminatiStmt->fetch()) {
        $clientiTerminati[] = [
          'nome' => trim((string)$row['nome'] . ' ' . (string)$row['cognome']),
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
    <thead><tr><th>Cliente</th><th>Stato associazione</th><th>Data associazione</th><th>Ultimo aggiornamento</th><th>Azioni</th></tr></thead>
    <tbody>
      <?php if (!$clientiAttivi): ?>
        <tr><td colspan="5" class="muted">Nessun cliente attivo associato.</td></tr>
      <?php endif; ?>

      <?php foreach ($clientiAttivi as $cliente): ?>
        <tr>
          <td><?= h($cliente['nome']) ?></td>
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
    <thead><tr><th>Cliente</th><th>Stato</th><th>Data chiusura</th><th>Nota</th></tr></thead>
    <tbody>
      <?php if (!$clientiTerminati): ?>
        <tr><td colspan="4" class="muted">Nessuna associazione terminata.</td></tr>
      <?php endif; ?>
      <?php foreach ($clientiTerminati as $cliente): ?>
        <tr>
          <td><?= h($cliente['nome']) ?></td>
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
