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
  <div class="grid cols-2">
    <div class="card" style="background:rgba(255,255,255,.03)">
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
    <div class="card" style="background:rgba(255,255,255,.03)">
      <h4>Aggiungi domanda</h4>
      <form id="addDomandaForm" class="stack" style="display:flex;flex-direction:column;gap:8px">
        <input type="hidden" name="idQuestionario" value="<?= (int)$selectedQuestionario['idQuestionario'] ?>">
        <input name="testoDomanda" placeholder="Testo domanda" required>
        <select name="tipoDomanda">
          <option value="short_text">short_text</option><option value="long_text">long_text</option><option value="single_choice">single_choice</option><option value="multiple_choice">multiple_choice</option><option value="number">number</option><option value="date">date</option><option value="consent_checkbox">consent_checkbox</option>
        </select>
        <input name="descrizione" placeholder="Descrizione (opzionale)">
        <input name="placeholderText" placeholder="Placeholder (opzionale)">
        <button class="btn primary" type="submit">Aggiungi domanda</button>
      </form>
      <p class="muted" id="builderFeedback"></p>

      <h4>Assegna questionario</h4>
      <form id="assegnaForm" style="display:flex;flex-direction:column;gap:8px">
        <input type="hidden" name="idQuestionario" value="<?= (int)$selectedQuestionario['idQuestionario'] ?>">
        <select name="clienti[]" multiple size="6">
          <?php foreach ($clientiAssociati as $c): ?><option value="<?= (int)$c['idCliente'] ?>"><?= h(trim($c['nome'].' '.$c['cognome'])) ?></option><?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Assegna selezionati</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <h3 style="margin-top:18px">Compilazioni ricevute</h3>
  <div style="overflow:auto"><table><thead><tr><th>Questionario</th><th>Cliente</th><th>#</th><th>Stato</th><th>Aggiornato</th><th>Inviato</th></tr></thead><tbody>
    <?php foreach ($compilazioni as $c): ?><tr><td><?= h($c['titolo']) ?></td><td><?= h(trim($c['nome'].' '.$c['cognome'])) ?></td><td><?= (int)$c['numeroCompilazione'] ?></td><td><?= h($c['stato']) ?></td><td><?= h($c['aggiornatoIl']) ?></td><td><?= h($c['inviatoIl'] ?: '—') ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php renderEnd('<script>
(function(){
const add=document.getElementById("addDomandaForm"); const fb=document.getElementById("builderFeedback");
add?.addEventListener("submit", async function(e){e.preventDefault(); const form=new FormData(add); const payload=Object.fromEntries(form.entries()); const r=await fetch("../api/questionari/domanda_create.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)}); const d=await r.json(); fb.textContent=d.ok?"Domanda aggiunta. Ricarico...":(d.error||"Errore"); if(d.ok) location.reload();});
const ass=document.getElementById("assegnaForm"); ass?.addEventListener("submit", async function(e){e.preventDefault(); const sel=[...ass.querySelector("select").selectedOptions].map(o=>parseInt(o.value,10)); const payload={idQuestionario:parseInt(ass.querySelector("input[name=idQuestionario]").value,10),clienti:sel}; const r=await fetch("../api/questionari/assegna.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)}); const d=await r.json(); alert(d.ok?`Assegnati ${d.inserted} clienti.`:(d.error||"Errore"));});
})();
</script>'); ?>
