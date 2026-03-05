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
               AND ap.stato IN ('attivo', 'attiva')
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
  <style>
    .remove-program-overlay {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
      background: rgba(0, 0, 0, .6);
      z-index: 1200;
      opacity: 0;
      transition: opacity .2s ease;
    }
    .remove-program-overlay.open {
      display: flex;
      opacity: 1;
    }
    .remove-program-modal {
      width: min(520px, 100%);
      border-radius: 14px;
      background: #1a2026;
      border: 1px solid rgba(255, 255, 255, .1);
      box-shadow: 0 22px 55px rgba(0, 0, 0, .45);
      padding: 18px;
      color: #eef3f7;
      transform: scale(.96);
      transition: transform .2s ease;
    }
    .remove-program-overlay.open .remove-program-modal { transform: scale(1); }
    .remove-program-meta {
      margin-top: 12px;
      padding: 10px;
      border-radius: 10px;
      background: rgba(255, 255, 255, .03);
      border: 1px solid rgba(255, 255, 255, .08);
    }
    .remove-program-actions {
      margin-top: 14px;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      flex-wrap: wrap;
    }
  </style>
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
            <td data-program-status><?= h((string)$programma['stato']) ?></td>
            <td><?= h((string)$programma['assegnatoIl']) ?></td>
            <td style="display:flex;gap:8px;flex-wrap:wrap">
              <a class="btn" href="programma.php?id=<?= (int)$programma['idProgramma'] ?>">Apri programma</a>
              <a class="btn" href="progressi_programma.php?idCliente=<?= (int)$idCliente ?>&idProgramma=<?= (int)$programma['idProgramma'] ?>">Visualizza Progressi</a>
              <?php if (in_array((string)$programma['stato'], ['attivo', 'attiva'], true)): ?>
                <button
                  class="btn"
                  type="button"
                  data-remove-program
                  data-id-cliente="<?= (int)$idCliente ?>"
                  data-id-programma="<?= (int)$programma['idProgramma'] ?>"
                  data-titolo="<?= h((string)$programma['titolo']) ?>"
                  data-assegnato-il="<?= h((string)$programma['assegnatoIl']) ?>"
                >Rimuovi</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="divider"></div>

    <button class="btn" type="button" data-toggle-associazioni>
      &gt; Storico associazioni con questo cliente
    </button>

    <div data-associazioni-container style="display:none;margin-top:10px">
      <table>
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Data inizio</th>
            <th>Data fine</th>
            <th>Stato</th>
          </tr>
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
    </div>
  <?php endif; ?>
</section>

<div id="removeProgramOverlay" class="remove-program-overlay" aria-hidden="true">
  <div id="removeProgramModal" class="remove-program-modal" role="dialog" aria-modal="true" aria-labelledby="removeProgramTitle">
    <div class="toolbar" style="justify-content:space-between;align-items:center;gap:12px">
      <h3 id="removeProgramTitle" style="margin:0">Conferma rimozione</h3>
      <button class="btn" type="button" data-remove-close aria-label="Chiudi">✕</button>
    </div>
    <p style="margin:10px 0 0">Vuoi rimuovere questo programma dal cliente? Il cliente non lo vedrà più in dashboard.</p>
    <div class="remove-program-meta">
      <p style="margin:0"><strong>Programma:</strong> <span data-remove-program-title>—</span></p>
      <p style="margin:8px 0 0"><strong>Assegnato il:</strong> <span data-remove-program-date>—</span></p>
    </div>
    <p class="muted" data-remove-feedback style="margin:10px 0 0"></p>
    <div class="remove-program-actions">
      <button class="btn" type="button" data-remove-cancel>Annulla</button>
      <button class="btn primary" type="button" data-remove-confirm>Conferma rimozione</button>
    </div>
  </div>
</div>
<?php
renderEnd('<script>(function(){const contactBtn=document.querySelector("[data-toggle-contact]");const contactMail=document.querySelector("[data-contact-mail]");if(contactBtn&&contactMail){contactBtn.addEventListener("click",function(){const isHidden=contactMail.style.display==="none";contactMail.style.display=isHidden?"block":"none";contactBtn.textContent=isHidden?"Nascondi mail contatto":"Mostra mail contatto";});}const physicalBtn=document.querySelector("[data-toggle-physical]");const physicalLine=document.querySelector("[data-physical-line]");if(physicalBtn&&physicalLine){physicalBtn.addEventListener("click",function(){const hidden=physicalLine.style.display==="none";physicalLine.style.display=hidden?"block":"none";physicalBtn.textContent=hidden?"Nascondi dati fisici":"Mostra dati fisici";});}const assocBtn=document.querySelector("[data-toggle-associazioni]");const assocContainer=document.querySelector("[data-associazioni-container]");if(assocBtn&&assocContainer){assocBtn.addEventListener("click",function(){const hidden=assocContainer.style.display==="none";assocContainer.style.display=hidden?"block":"none";assocBtn.textContent=hidden?"˅ Nascondi storico associazioni":"> Storico associazioni con questo cliente";});}const overlay=document.getElementById("removeProgramOverlay");const modal=document.getElementById("removeProgramModal");const titleEl=document.querySelector("[data-remove-program-title]");const dateEl=document.querySelector("[data-remove-program-date]");const feedbackEl=document.querySelector("[data-remove-feedback]");const closeEls=document.querySelectorAll("[data-remove-close],[data-remove-cancel]");const confirmBtn=document.querySelector("[data-remove-confirm]");let currentBtn=null;let payload=null;function closeModal(){if(!overlay){return;}overlay.classList.remove("open");overlay.setAttribute("aria-hidden","true");document.body.style.overflow="";if(feedbackEl){feedbackEl.textContent="";}currentBtn=null;payload=null;if(confirmBtn){confirmBtn.disabled=false;confirmBtn.textContent="Conferma rimozione";}}function openModal(btn){if(!overlay||!modal){return;}currentBtn=btn;payload={idCliente:Number(btn.dataset.idCliente||0),idProgramma:Number(btn.dataset.idProgramma||0)};if(titleEl){titleEl.textContent=btn.dataset.titolo||"—";}if(dateEl){dateEl.textContent=btn.dataset.assegnatoIl||"—";}if(feedbackEl){feedbackEl.textContent="";}overlay.classList.add("open");overlay.setAttribute("aria-hidden","false");document.body.style.overflow="hidden";}document.querySelectorAll("[data-remove-program]").forEach(function(btn){btn.addEventListener("click",function(){openModal(btn);});});closeEls.forEach(function(el){el.addEventListener("click",closeModal);});overlay?.addEventListener("click",function(event){if(event.target===overlay){closeModal();}});document.addEventListener("keydown",function(event){if(event.key==="Escape"&&overlay&&overlay.classList.contains("open")){closeModal();}});confirmBtn?.addEventListener("click",async function(){if(!currentBtn||!payload){return;}confirmBtn.disabled=true;confirmBtn.textContent="Rimozione in corso...";closeEls.forEach(function(el){el.disabled=true;});if(feedbackEl){feedbackEl.textContent="Rimozione in corso...";}try{const response=await fetch("api/rimuovi_programma_cliente.php",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(payload)});const text=await response.text();let data=null;try{data=JSON.parse(text);}catch(parseError){throw new Error("Risposta non valida dal server.");}if(!response.ok||!data.ok){throw new Error((data&&data.error)||"Rimozione non riuscita.");}const row=currentBtn.closest("tr");if(row){row.remove();}if(feedbackEl){feedbackEl.textContent="Programma rimosso correttamente.";}setTimeout(closeModal,500);}catch(error){if(feedbackEl){feedbackEl.textContent=error.message||"Errore durante la rimozione.";}confirmBtn.disabled=false;confirmBtn.textContent="Conferma rimozione";}finally{closeEls.forEach(function(el){el.disabled=false;});}});})();</script>');
