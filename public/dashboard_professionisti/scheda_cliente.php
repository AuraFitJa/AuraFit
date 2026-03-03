<?php
require __DIR__ . '/common.php';

$errors = [];
$cliente = null;
$programmiAssegnati = [];
$storicoAssociazioni = [];

$idCliente = (int)($_GET['idCliente'] ?? 0);

if ($idCliente < 1) {
  $errors[] = 'Cliente non valido.';
} elseif (!$dbAvailable) {
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

      if (!$tipiConsentiti) {
        $errors[] = 'Ruolo professionista non autorizzato.';
      } else {
        $placeholders = implode(',', array_fill(0, count($tipiConsentiti), '?'));

        $cliente = Database::exec(
          "SELECT c.idCliente, u.nome, u.cognome, u.email, pc.eta, pc.altezzaCm, pc.pesoKg
           FROM Associazioni a
           INNER JOIN Clienti c ON c.idCliente = a.cliente
           INNER JOIN Utenti u ON u.idUtente = c.idUtente
           LEFT JOIN ProfiloCliente pc ON pc.idCliente = c.idCliente
           WHERE a.professionista = ?
             AND a.cliente = ?
             AND a.tipoAssociazione IN ($placeholders)
           ORDER BY a.attivaFlag = 1 DESC, a.iniziataIl DESC
           LIMIT 1",
          array_merge([$professionistaId, $idCliente], $tipiConsentiti)
        )->fetch();

        if (!$cliente) {
          $errors[] = 'Cliente non associato al tuo profilo professionista.';
        } else {
          $programmiAssegnati = Database::exec(
            "SELECT p.titolo, ap.stato, ap.assegnatoIl
             FROM AssegnazioniProgramma ap
             INNER JOIN ProgrammiAllenamento p ON p.idProgramma = ap.programma
             WHERE ap.cliente = ?
             ORDER BY ap.assegnatoIl DESC
             LIMIT 5",
            [$idCliente]
          )->fetchAll();

          $storicoAssociazioni = Database::exec(
            "SELECT tipoAssociazione, iniziataIl, terminataIl, attivaFlag
             FROM Associazioni
             WHERE professionista = ? AND cliente = ?
             ORDER BY iniziataIl DESC
             LIMIT 5",
            [$professionistaId, $idCliente]
          )->fetchAll();
        }
      }
    }
  } catch (Throwable $e) {
    $errors[] = 'Errore DB scheda cliente: ' . $e->getMessage();
  }
}

$clienteNome = $cliente ? trim((string)$cliente['nome'] . ' ' . (string)$cliente['cognome']) : 'Cliente';
renderStart('Scheda Cliente', 'clienti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <h2 class="section-title">Scheda Cliente - <?= h($clienteNome) ?></h2>

  <?php foreach ($errors as $error): ?>
    <div class="alert" style="margin-bottom:10px"><?= h($error) ?></div>
  <?php endforeach; ?>

  <?php if ($cliente): ?>
    <div class="grid cols-3" style="margin-bottom:16px">
      <div class="stat">
        <h3 style="margin:0 0 8px">Contatti</h3>
        <p style="margin:0"><strong>Email:</strong> <?= h((string)$cliente['email']) ?></p>
      </div>
      <div class="stat">
        <h3 style="margin:0 0 8px">Dati fisici</h3>
        <p style="margin:0"><strong>Età:</strong> <?= h(isset($cliente['eta']) ? (string)$cliente['eta'] : '—') ?></p>
        <p style="margin:4px 0 0"><strong>Altezza:</strong> <?= h(isset($cliente['altezzaCm']) ? (string)$cliente['altezzaCm'] . ' cm' : '—') ?></p>
        <p style="margin:4px 0 0"><strong>Peso:</strong> <?= h(isset($cliente['pesoKg']) ? (string)$cliente['pesoKg'] . ' kg' : '—') ?></p>
      </div>
      <div class="stat">
        <h3 style="margin:0 0 8px">Azioni rapide</h3>
        <a class="btn" href="programma.php">Vai a Programma</a>
      </div>
    </div>

    <h3>Ultimi programmi assegnati</h3>
    <table>
      <thead>
        <tr><th>Titolo</th><th>Stato</th><th>Data assegnazione</th></tr>
      </thead>
      <tbody>
        <?php if (!$programmiAssegnati): ?>
          <tr><td colspan="3" class="muted">Nessun programma assegnato.</td></tr>
        <?php endif; ?>
        <?php foreach ($programmiAssegnati as $programma): ?>
          <tr>
            <td><?= h((string)$programma['titolo']) ?></td>
            <td><?= h((string)$programma['stato']) ?></td>
            <td><?= h((string)$programma['assegnatoIl']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="divider"></div>

    <h3>Storico associazioni con questo cliente</h3>
    <table>
      <thead>
        <tr><th>Tipo</th><th>Data inizio</th><th>Data fine</th><th>Stato</th></tr>
      </thead>
      <tbody>
        <?php foreach ($storicoAssociazioni as $associazione): ?>
          <tr>
            <td><?= h((string)$associazione['tipoAssociazione']) ?></td>
            <td><?= h((string)$associazione['iniziataIl']) ?></td>
            <td><?= h((string)$associazione['terminataIl'] ?: '—') ?></td>
            <td>
              <?php if ((int)$associazione['attivaFlag'] === 1): ?>
                <span class="status ok">Attiva</span>
              <?php else: ?>
                <span class="status warn">Terminata</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php
renderEnd();
