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
            "SELECT p.idProgramma, p.titolo, ap.stato, ap.assegnatoIl
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
$clienteEmail = $cliente ? (string)$cliente['email'] : '';
$ultimoProgramma = $programmiAssegnati[0] ?? null;
renderStart('Scheda Cliente', 'clienti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <div class="toolbar" style="justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <a class="btn" href="clienti.php">← Indietro</a>
      <h2 class="section-title" style="margin:0">Scheda Cliente - <?= h($clienteNome) ?></h2>
    </div>
    <?php if ($cliente): ?>
      <button class="btn" type="button" data-toggle-contact>Mostra mail contatto</button>
    <?php endif; ?>
  </div>

  <?php foreach ($errors as $error): ?>
    <div class="alert" style="margin-bottom:10px"><?= h($error) ?></div>
  <?php endforeach; ?>

  <?php if ($cliente): ?>
    <div data-contact-mail style="display:none;margin:10px 0 14px">
      <div class="stat">
        <p style="margin:0"><strong>Mail contatto cliente:</strong> <?= h($clienteEmail) ?></p>
      </div>
    </div>

    <div class="stat" style="margin-bottom:16px">
      <div class="toolbar" style="justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px">
        <h3 style="margin:0">Dati fisici</h3>
        <button class="btn" type="button" data-toggle-physical>Nascondi dati fisici</button>
      </div>
      <p data-physical-line style="margin:0">
        <strong>Età:</strong> <?= h(isset($cliente['eta']) ? (string)$cliente['eta'] : '—') ?> ·
        <strong>Altezza:</strong> <?= h(isset($cliente['altezzaCm']) ? (string)$cliente['altezzaCm'] . ' cm' : '—') ?> ·
        <strong>Peso:</strong> <?= h(isset($cliente['pesoKg']) ? (string)$cliente['pesoKg'] . ' kg' : '—') ?>
      </p>
    </div>

    <h3>Ultimi programmi assegnati</h3>
    <div class="toolbar" style="justify-content:flex-end;gap:10px;margin-bottom:10px">
      <?php if ($ultimoProgramma): ?>
        <a class="btn" href="programma.php?id=<?= (int)$ultimoProgramma['idProgramma'] ?>">Vai al programma assegnato</a>
      <?php else: ?>
        <a class="btn primary" href="allenamenti.php">+ Assegna programma</a>
      <?php endif; ?>
    </div>
    <table>
      <thead>
        <tr><th>Titolo</th><th>Stato</th><th>Data assegnazione</th><th>Azione</th></tr>
      </thead>
      <tbody>
        <?php if (!$programmiAssegnati): ?>
          <tr><td colspan="4" class="muted">Nessun programma assegnato.</td></tr>
        <?php endif; ?>
        <?php foreach ($programmiAssegnati as $programma): ?>
          <tr>
            <td><?= h((string)$programma['titolo']) ?></td>
            <td><?= h((string)$programma['stato']) ?></td>
            <td><?= h((string)$programma['assegnatoIl']) ?></td>
            <td><a class="btn" href="programma.php?id=<?= (int)$programma['idProgramma'] ?>">Apri programma</a></td>
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
renderEnd('<script>(function(){const contactBtn=document.querySelector("[data-toggle-contact]");const contactMail=document.querySelector("[data-contact-mail]");if(contactBtn&&contactMail){contactBtn.addEventListener("click",function(){const isHidden=contactMail.style.display==="none";contactMail.style.display=isHidden?"block":"none";contactBtn.textContent=isHidden?"Nascondi mail contatto":"Mostra mail contatto";});}const physicalBtn=document.querySelector("[data-toggle-physical]");const physicalLine=document.querySelector("[data-physical-line]");if(physicalBtn&&physicalLine){physicalBtn.addEventListener("click",function(){const hidden=physicalLine.style.display==="none";physicalLine.style.display=hidden?"block":"none";physicalBtn.textContent=hidden?"Nascondi dati fisici":"Mostra dati fisici";});}})();</script>');
