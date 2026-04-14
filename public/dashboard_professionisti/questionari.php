<?php
require __DIR__ . '/common.php';

$errors = [];
$success = [];
$questionari = [];
$domande = [];
$opzioniByDomanda = [];
$clientiAssociati = [];
$assegnazioni = [];
$compilazioni = [];
$selectedQuestionario = null;

if (!$dbAvailable) {
  $errors[] = $dbError ?? 'Database non disponibile.';
} else {
  try {
    $professionistaId = getProfessionistaId($userId);
    if (!$professionistaId) {
      $errors[] = 'Profilo professionista non trovato.';
    } else {
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['createQuestionario'])) {
        $titolo = trim((string)($_POST['titolo'] ?? ''));
        if ($titolo !== '') {
          Database::exec('INSERT INTO Questionari (professionista,titolo,descrizione,categoria,stato,creatoIl,aggiornatoIl) VALUES (?,?,?,?,?,NOW(),NOW())', [$professionistaId, $titolo, trim((string)($_POST['descrizione'] ?? '')), trim((string)($_POST['categoria'] ?? 'generale')), 'attivo']);
          $success[] = 'Questionario creato.';
        } else {
          $errors[] = 'Inserisci un titolo valido.';
        }
      }

      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicaQuestionario'])) {
        $sourceId = (int)($_POST['idQuestionario'] ?? 0);
        $source = Database::exec('SELECT * FROM Questionari WHERE idQuestionario = ? AND professionista = ? LIMIT 1', [$sourceId, $professionistaId])->fetch();
        if ($source) {
          Database::pdo()->beginTransaction();
          try {
            Database::exec('INSERT INTO Questionari (professionista,titolo,descrizione,categoria,stato,creatoIl,aggiornatoIl) VALUES (?,?,?,?,?,NOW(),NOW())', [$professionistaId, $source['titolo'] . ' (Copia)', $source['descrizione'], $source['categoria'], 'bozza']);
            $newId = (int)Database::pdo()->lastInsertId();
            $domandeSource = Database::exec('SELECT * FROM QuestionarioDomande WHERE questionario = ? ORDER BY ordine ASC', [$sourceId])->fetchAll();
            foreach ($domandeSource as $d) {
              Database::exec('INSERT INTO QuestionarioDomande (questionario,tipoDomanda,testoDomanda,descrizione,placeholderText,ordine,impostazioniJson,creatoIl,aggiornatoIl) VALUES (?,?,?,?,?,?,?,?,?)', [$newId, $d['tipoDomanda'], $d['testoDomanda'], $d['descrizione'], $d['placeholderText'], $d['ordine'], $d['impostazioniJson'], date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
              $newDomanda = (int)Database::pdo()->lastInsertId();
              $ops = Database::exec('SELECT * FROM QuestionarioOpzioni WHERE domanda = ? ORDER BY ordine ASC', [$d['idDomanda']])->fetchAll();
              foreach ($ops as $o) {
                Database::exec('INSERT INTO QuestionarioOpzioni (domanda,labelOpzione,valoreOpzione,ordine) VALUES (?,?,?,?)', [$newDomanda, $o['labelOpzione'], $o['valoreOpzione'], $o['ordine']]);
              }
            }
            Database::pdo()->commit();
            $success[] = 'Questionario duplicato.';
          } catch (Throwable $e) {
            Database::pdo()->rollBack();
            $errors[] = 'Duplicazione non riuscita.';
          }
        }
      }

      $questionari = Database::exec("SELECT q.*, (SELECT COUNT(*) FROM QuestionarioAssegnazioni qa WHERE qa.questionario = q.idQuestionario AND qa.stato='attivo') AS assegnazioniAttive, (SELECT COUNT(*) FROM QuestionarioCompilazioni qc WHERE qc.questionario = q.idQuestionario AND qc.stato='inviato') AS compilazioniRicevute FROM Questionari q WHERE q.professionista = ? ORDER BY q.aggiornatoIl DESC", [$professionistaId])->fetchAll();

      $selectedId = (int)($_GET['idQuestionario'] ?? 0);
      if ($selectedId > 0) {
        $selectedQuestionario = Database::exec('SELECT * FROM Questionari WHERE idQuestionario = ? AND professionista = ? LIMIT 1', [$selectedId, $professionistaId])->fetch();
        if ($selectedQuestionario) {
          $domande = Database::exec('SELECT * FROM QuestionarioDomande WHERE questionario = ? ORDER BY ordine ASC', [$selectedId])->fetchAll();
          if ($domande) {
            $ids = array_map(static function ($d) { return (int)$d['idDomanda']; }, $domande);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $ops = Database::exec("SELECT * FROM QuestionarioOpzioni WHERE domanda IN ($in) ORDER BY ordine ASC", $ids)->fetchAll();
            foreach ($ops as $o) {
              $opzioniByDomanda[(int)$o['domanda']][] = $o;
            }
          }
        }
      }

      $clientiAssociati = Database::exec("SELECT c.idCliente, u.nome, u.cognome FROM Associazioni a INNER JOIN Clienti c ON c.idCliente = a.cliente INNER JOIN Utenti u ON u.idUtente = c.idUtente WHERE a.professionista = ? AND a.attivaFlag = 1 AND a.tipoAssociazione IN ('pt','nutrizionista') ORDER BY u.nome ASC", [$professionistaId])->fetchAll();

      $assegnazioni = Database::exec("SELECT qa.*, q.titolo, u.nome, u.cognome FROM QuestionarioAssegnazioni qa INNER JOIN Questionari q ON q.idQuestionario=qa.questionario INNER JOIN Clienti c ON c.idCliente=qa.cliente INNER JOIN Utenti u ON u.idUtente=c.idUtente WHERE qa.professionista = ? ORDER BY qa.assegnatoIl DESC", [$professionistaId])->fetchAll();

      $compilazioni = Database::exec("SELECT qc.*, q.titolo, u.nome, u.cognome FROM QuestionarioCompilazioni qc INNER JOIN Questionari q ON q.idQuestionario=qc.questionario INNER JOIN Clienti c ON c.idCliente=qc.cliente INNER JOIN Utenti u ON u.idUtente=c.idUtente WHERE q.professionista = ? ORDER BY qc.aggiornatoIl DESC LIMIT 100", [$professionistaId])->fetchAll();
    }
  } catch (Throwable $e) {
    $errors[] = 'Errore nel caricamento questionari.';
  }
}

renderStart('Questionari', 'questionari', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <h2 class="section-title">Questionari</h2>
  <?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>
  <?php foreach ($success as $msg): ?><div class="okbox" style="display:block"><?= h($msg) ?></div><?php endforeach; ?>

  <h3>Libreria Questionari</h3>
  <form method="post" class="toolbar" style="gap:8px;align-items:flex-end">
    <input type="hidden" name="createQuestionario" value="1">
    <label class="field"><span>Titolo</span><input name="titolo" required></label>
    <label class="field" style="min-width:320px"><span>Descrizione</span><input name="descrizione"></label>
    <button class="btn primary" type="submit">Nuovo questionario</button>
  </form>
  <div style="overflow:auto"><table><thead><tr><th>Titolo</th><th>Stato</th><th>Assegnazioni</th><th>Compilazioni</th><th>Azioni</th></tr></thead><tbody>
  <?php foreach ($questionari as $q): ?><tr>
    <td><strong><?= h($q['titolo']) ?></strong><div class="muted"><?= h($q['descrizione']) ?></div></td>
    <td><?= h($q['stato']) ?></td>
    <td><?= (int)$q['assegnazioniAttive'] ?></td>
    <td><?= (int)$q['compilazioniRicevute'] ?></td>
    <td><a class="btn" href="?idQuestionario=<?= (int)$q['idQuestionario'] ?>">Modifica</a>
      <form method="post" style="display:inline"><input type="hidden" name="duplicaQuestionario" value="1"><input type="hidden" name="idQuestionario" value="<?= (int)$q['idQuestionario'] ?>"><button class="btn" type="submit">Duplica</button></form>
    </td>
  </tr><?php endforeach; ?></tbody></table></div>

  <?php if ($selectedQuestionario): ?>
  <hr style="opacity:.2;margin:18px 0">
  <h3>Builder Questionario: <?= h($selectedQuestionario['titolo']) ?></h3>
  <div class="grid cols-2 builder-layout">
    <div class="card builder-card builder-card-questions" style="background:rgba(255,255,255,.03)">
      <h4>Domande</h4>
      <?php foreach ($domande as $d): ?>
        <div class="stat" style="margin-bottom:8px">
          <strong>#<?= (int)$d['ordine'] ?> <?= h($d['testoDomanda']) ?></strong>
          <div class="muted"><?= h($d['tipoDomanda']) ?></div>
          <?php foreach (($opzioniByDomanda[(int)$d['idDomanda']] ?? []) as $o): ?>
            <div class="muted">- <?= h($o['labelOpzione']) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card builder-card" style="background:rgba(255,255,255,.03)">
      <h4>Aggiungi domanda</h4>
      <form id="addDomandaForm" class="stack" style="display:flex;flex-direction:column;gap:8px">
        <input type="hidden" name="idQuestionario" value="<?= (int)$selectedQuestionario['idQuestionario'] ?>">
        <input class="builder-input" name="testoDomanda" placeholder="Testo domanda" required>
        <select class="builder-input" name="tipoDomanda">
          <option value="short_text">Risposta breve</option>
          <option value="long_text">Risposta lunga</option>
          <option value="single_choice">Scelta singola</option>
          <option value="multiple_choice">Scelta multipla</option>
          <option value="number">Numero</option>
          <option value="date">Data</option>
          <option value="consent_checkbox">Consenso (checkbox)</option>
        </select>
        <input class="builder-input" name="descrizione" placeholder="Descrizione (opzionale)">
        <input class="builder-input" name="placeholderText" placeholder="Placeholder (opzionale)">
        <button class="btn primary" type="submit">Aggiungi domanda</button>
      </form>
      <p class="muted" id="builderFeedback"></p>

      <h4>Assegna questionario</h4>
      <button class="btn" type="button" data-open-assign-modal>Assegna questionario</button>
    </div>
  </div>

  <div class="assign-modal-layer" data-assign-questionario-modal>
    <div class="assign-modal-card" role="dialog" aria-modal="true" aria-labelledby="assign-questionario-modal-title">
      <h3 id="assign-questionario-modal-title" style="margin:0">Assegna questionario</h3>
      <p class="muted" style="margin:8px 0 16px">Seleziona i clienti attivi associati al tuo profilo PT.</p>
      <label class="assign-toggle-all">
        <input type="checkbox" data-assign-toggle-all>
        <span>Seleziona tutti / Deseleziona tutti</span>
      </label>
      <div class="assign-client-list" data-assign-client-list>
        <?php foreach ($clientiAssociati as $c): ?>
          <label class="assign-client-item">
            <input type="checkbox" data-assign-cliente value="<?= (int)$c['idCliente'] ?>">
            <span><?= h(trim($c['nome'] . ' ' . $c['cognome'])) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="assign-feedback" data-assign-feedback></p>
      <div class="library-toolbar" style="justify-content:flex-end">
        <button class="btn" type="button" data-close-assign-modal>Chiudi</button>
        <button class="btn primary" type="button" data-submit-assign>Assegna</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <h3 style="margin-top:18px">Compilazioni ricevute</h3>
  <div style="overflow:auto"><table><thead><tr><th>Questionario</th><th>Cliente</th><th>#</th><th>Stato</th><th>Aggiornato</th><th>Inviato</th></tr></thead><tbody>
    <?php foreach ($compilazioni as $c): ?><tr><td><?= h($c['titolo']) ?></td><td><?= h(trim($c['nome'].' '.$c['cognome'])) ?></td><td><?= (int)$c['numeroCompilazione'] ?></td><td><?= h($c['stato']) ?></td><td><?= h($c['aggiornatoIl']) ?></td><td><?= h($c['inviatoIl'] ?: '—') ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<style>
  .builder-layout {
    align-items: stretch;
  }
  .builder-card {
    width: 100%;
  }
  .builder-card-questions {
    min-width: 0;
    flex: 1 1 50%;
  }
  .builder-layout > .builder-card {
    flex: 1 1 50%;
  }
  .builder-input {
    width: 100%;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(12, 19, 35, 0.8);
    color: #e7efff;
    padding: 10px 12px;
    outline: none;
    transition: border-color .2s ease, box-shadow .2s ease;
  }
  .builder-input:focus {
    border-color: rgba(99, 102, 241, 0.85);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
  }
  .assign-modal-layer {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 18, 0.84);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1200;
    padding: 16px;
  }
  .assign-modal-layer.open { display: flex; }
  .assign-modal-card {
    width: min(680px, 100%);
    max-height: min(82vh, 720px);
    overflow: hidden;
    border-radius: 18px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: linear-gradient(180deg, rgba(18, 24, 41, 0.98), rgba(9, 13, 24, 0.98));
    box-shadow: 0 22px 56px rgba(0, 0, 0, 0.55);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .assign-toggle-all {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(235, 243, 255, 0.92);
    font-size: 14px;
  }
  .assign-client-list {
    max-height: 330px;
    overflow-y: auto;
    padding: 8px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.02);
  }
  .assign-client-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 6px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  }
  .assign-client-item:last-child { border-bottom: none; }
  .assign-feedback {
    min-height: 20px;
    margin: 0;
    font-size: 14px;
    color: #fda4af;
  }
  .assign-feedback.ok { color: #86efac; }
</style>
<?php renderEnd('<script>
(function(){
const add=document.getElementById("addDomandaForm"); const fb=document.getElementById("builderFeedback");
add?.addEventListener("submit", async function(e){e.preventDefault(); const form=new FormData(add); const payload=Object.fromEntries(form.entries()); const r=await fetch("../api/questionari/domanda_create.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)}); const d=await r.json(); fb.textContent=d.ok?"Domanda aggiunta. Ricarico...":(d.error||"Errore"); if(d.ok) location.reload();});
const modal=document.querySelector("[data-assign-questionario-modal]");
const openBtn=document.querySelector("[data-open-assign-modal]");
const closeBtn=document.querySelector("[data-close-assign-modal]");
const submitBtn=document.querySelector("[data-submit-assign]");
const toggleAll=document.querySelector("[data-assign-toggle-all]");
const feedback=document.querySelector("[data-assign-feedback]");
const idQuestionario=' . (int)$selectedQuestionario['idQuestionario'] . ';

function setFeedback(message, ok){ if(!feedback) return; feedback.textContent=message||""; feedback.classList.toggle("ok",!!ok); }
function toggleModal(open){ if(!modal) return; modal.classList.toggle("open", !!open); if(!open) setFeedback("", false); }
function selectedClienti(){ return [...document.querySelectorAll("[data-assign-cliente]:checked")].map(i=>parseInt(i.value,10)).filter(Number.isFinite); }

openBtn?.addEventListener("click", ()=>toggleModal(true));
closeBtn?.addEventListener("click", ()=>toggleModal(false));
modal?.addEventListener("click", (e)=>{ if(e.target===modal) toggleModal(false); });
toggleAll?.addEventListener("change", function(){ document.querySelectorAll("[data-assign-cliente]").forEach((el)=>{ el.checked=toggleAll.checked; }); });
submitBtn?.addEventListener("click", async function(){
  const clienti=selectedClienti();
  if(clienti.length===0){ setFeedback("Seleziona almeno un cliente.", false); return; }
  const r=await fetch("../api/questionari/assegna.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({idQuestionario, clienti})});
  const d=await r.json();
  if(d.ok){ setFeedback(`Assegnati ${d.inserted} clienti.`, true); setTimeout(()=>location.reload(), 500); return; }
  setFeedback(d.error||"Errore durante assegnazione.", false);
});
})();
</script>'); ?>
