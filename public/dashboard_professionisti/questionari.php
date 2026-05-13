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
$selectedCompilazioneId = 0;
$selectedQuestionario = null;
$assegnazioniAttiveByCliente = [];

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
      if ($selectedQuestionario) {
        $assegnazioniAttive = Database::exec(
          "SELECT cliente FROM QuestionarioAssegnazioni WHERE professionista = ? AND questionario = ? AND stato = 'attivo'",
          [$professionistaId, (int)$selectedQuestionario['idQuestionario']]
        )->fetchAll();
        foreach ($assegnazioniAttive as $assegnazioneAttiva) {
          $assegnazioniAttiveByCliente[(int)$assegnazioneAttiva['cliente']] = true;
        }
      }

      $compilazioni = Database::exec("SELECT qc.*, q.titolo, u.nome, u.cognome FROM QuestionarioCompilazioni qc INNER JOIN Questionari q ON q.idQuestionario=qc.questionario INNER JOIN Clienti c ON c.idCliente=qc.cliente INNER JOIN Utenti u ON u.idUtente=c.idUtente WHERE q.professionista = ? ORDER BY qc.aggiornatoIl DESC LIMIT 100", [$professionistaId])->fetchAll();
      $selectedCompilazioneId = (int)($_GET['idCompilazione'] ?? 0);
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

  <section class="mobile-q-header" aria-label="Questionari mobile">
    <div class="mobile-q-header__top">
      <p class="mobile-q-header__eyebrow">Questionari</p>
      <button class="mobile-toggle-btn" type="button" data-mobile-toggle="mobile-new-questionario" aria-expanded="false" aria-controls="mobile-new-questionario">+</button>
    </div>
    <h3 class="mobile-q-header__title">Libreria mobile</h3>
    <p class="mobile-q-header__subtitle">Gestisci moduli, invii e risposte senza tabelle schiacciate.</p>
    <div class="mobile-q-stats">
      <article><span>Totali</span><strong><?= count($questionari) ?></strong></article>
      <article><span>Assegnati</span><strong><?= array_sum(array_map(static fn($item)=>(int)$item['assegnazioniAttive'], $questionari)) ?></strong></article>
      <article><span>Ricevuti</span><strong><?= array_sum(array_map(static fn($item)=>(int)$item['compilazioniRicevute'], $questionari)) ?></strong></article>
    </div>
  </section>

  <section class="mobile-collapsible" id="mobile-new-questionario" data-mobile-collapsible hidden>
    <div class="mobile-create-head"><h4>Nuovo questionario</h4><span>rapido</span></div>
  <h3 class="mobile-create-title">Libreria Questionari</h3>
  <form method="post" class="toolbar mobile-form" style="gap:8px;align-items:flex-end">
    <input type="hidden" name="createQuestionario" value="1">
    <label class="field"><span>Titolo</span><input name="titolo" required></label>
    <label class="field" style="min-width:320px"><span>Descrizione</span><input name="descrizione"></label>
    <button class="btn primary" type="submit">Nuovo questionario</button>
  </form>
  </section>
  <div style="overflow:auto" class="desktop-table"><table><thead><tr><th>Titolo</th><th>Stato</th><th>Assegnazioni</th><th>Compilazioni</th><th>Azioni</th></tr></thead><tbody>
  <?php foreach ($questionari as $q): ?><tr>
    <td><strong><?= h($q['titolo']) ?></strong><div class="muted"><?= h($q['descrizione']) ?></div></td>
    <td><?= h($q['stato']) ?></td>
    <td><?= (int)$q['assegnazioniAttive'] ?></td>
    <td><?= (int)$q['compilazioniRicevute'] ?></td>
    <td><a class="btn" href="?idQuestionario=<?= (int)$q['idQuestionario'] ?>">Modifica</a>
      <form method="post" style="display:inline"><input type="hidden" name="duplicaQuestionario" value="1"><input type="hidden" name="idQuestionario" value="<?= (int)$q['idQuestionario'] ?>"><button class="btn" type="submit">Duplica</button></form>
    </td>
  </tr><?php endforeach; ?></tbody></table></div>
  <section class="mobile-library">
      <div class="mobile-library__head"><h3>Libreria</h3><span><?= count($questionari) ?> elementi</span></div>
    <div class="mobile-search"><input type="text" placeholder="Cerca questionario"></div>
    <?php foreach ($questionari as $q): ?>
      <article class="mobile-q-card">
        <div class="mobile-q-card__title"><strong><?= h($q['titolo']) ?></strong><span class="mobile-tag"><?= strtoupper(h((string)$q['categoria'] ?: 'GEN')) ?></span><span class="mobile-pill <?= h($q['stato'])==='attivo' ? 'is-active' : '' ?>"><?= h($q['stato']) ?></span></div>
        <p><?= h($q['descrizione']) ?></p>
        <div class="mobile-q-card__stats"><div><span>Assegnazioni</span><strong><?= (int)$q['assegnazioniAttive'] ?></strong></div><div><span>Compilazioni</span><strong><?= (int)$q['compilazioniRicevute'] ?></strong></div></div>
        <div class="mobile-q-card__actions">
          <a class="btn" href="?idQuestionario=<?= (int)$q['idQuestionario'] ?>">Modifica</a>
          <form method="post"><input type="hidden" name="duplicaQuestionario" value="1"><input type="hidden" name="idQuestionario" value="<?= (int)$q['idQuestionario'] ?>"><button class="btn" type="submit">Duplica</button></form>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

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
        <div class="builder-options" data-builder-options hidden>
          <div class="builder-options-header">
            <strong>Opzioni risposta</strong>
            <button class="btn" type="button" data-add-option>Aggiungi opzione</button>
          </div>
          <div class="builder-options-list" data-builder-options-list></div>
          <p class="muted" style="margin:0">Aggiungi almeno 2 opzioni per domande a scelta.</p>
        </div>
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
            <input type="checkbox" data-assign-cliente value="<?= (int)$c['idCliente'] ?>"<?= isset($assegnazioniAttiveByCliente[(int)$c['idCliente']]) ? ' checked' : '' ?>>
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

  <section class="mobile-submissions-shell">
    <button class="mobile-submissions-toggle" type="button" data-mobile-toggle="mobile-compilazioni" aria-expanded="false" aria-controls="mobile-compilazioni">
      <h3>Compilazioni ricevute</h3><span><?= count($compilazioni) ?></span>
    </button>
    <section class="mobile-collapsible mobile-collapsible--compilazioni" data-mobile-collapsible id="mobile-compilazioni" hidden>
      <p class="muted" style="margin:0 0 8px">Tap su una scheda per aprire il dettaglio risposte.</p>
      <?php foreach ($compilazioni as $c): ?>
        <article class="mobile-sub-card compilazione-row" data-compilazione-row data-id-compilazione="<?= (int)$c['idCompilazione'] ?>" role="button" tabindex="0" aria-label="Apri risposte compilazione #<?= (int)$c['numeroCompilazione'] ?>">
          <div><strong><?= h($c['titolo']) ?></strong><p><?= h(trim($c['nome'].' '.$c['cognome'])) ?></p></div>
          <div><span>#<?= (int)$c['numeroCompilazione'] ?></span><small>Agg. <?= h(substr((string)$c['aggiornatoIl'],0,10)) ?></small></div>
        </article>
      <?php endforeach; ?>
    </section>
  </section>

  <h3 style="margin-top:18px" class="desktop-only">Compilazioni ricevute</h3>
  <div style="overflow:auto" class="desktop-table"><table><thead><tr><th>Questionario</th><th>Cliente</th><th>#</th><th>Stato</th><th>Aggiornato</th><th>Inviato</th></tr></thead><tbody>
    <?php foreach ($compilazioni as $c): ?>
      <tr class="compilazione-row desktop-row" data-compilazione-row data-id-compilazione="<?= (int)$c['idCompilazione'] ?>" role="button" tabindex="0" aria-label="Apri risposte compilazione #<?= (int)$c['numeroCompilazione'] ?>">
        <td><strong><?= h($c['titolo']) ?></strong></td>
        <td><?= h(trim($c['nome'].' '.$c['cognome'])) ?></td>
        <td><?= (int)$c['numeroCompilazione'] ?></td>
        <td><?= h($c['stato']) ?></td>
        <td><?= h($c['aggiornatoIl']) ?></td>
        <td><?= h($c['inviatoIl'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <section class="compilazione-panel" data-compilazione-panel hidden>
    <div class="compilazione-panel__header">
      <div>
        <h4 style="margin:0" data-compilazione-title>Risposte compilazione</h4>
        <p class="muted compilazione-panel__meta" data-compilazione-meta></p>
      </div>
      <span class="compilazione-panel__badge" data-compilazione-stato></span>
    </div>
    <div class="compilazione-panel__content" data-compilazione-content></div>
  </section>
  </section>
</section>
<style>
  .compilazione-row {
    cursor: pointer;
    transition: background-color .18s ease;
  }
  .compilazione-row:hover {
    background: rgba(99, 102, 241, 0.12);
  }
  .compilazione-row:focus-visible {
    outline: 2px solid rgba(99, 102, 241, 0.9);
    outline-offset: -2px;
  }
  .compilazione-row.is-active {
    background: rgba(56, 189, 248, 0.14);
  }
  .compilazione-panel {
    margin-top: 14px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: linear-gradient(180deg, rgba(17, 24, 39, 0.9), rgba(8, 12, 22, 0.9));
    padding: 16px;
  }
  .compilazione-panel__header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 12px;
  }
  .compilazione-panel__meta {
    margin: 6px 0 0;
    font-size: 13px;
  }
  .compilazione-panel__badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(148, 163, 184, 0.16);
    border: 1px solid rgba(148, 163, 184, 0.3);
  }
  .compilazione-panel__content {
    display: grid;
    gap: 10px;
  }
  .compilazione-answer {
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.09);
    background: rgba(255, 255, 255, 0.02);
    padding: 10px 12px;
  }
  .compilazione-answer__question {
    margin: 0 0 6px;
    font-weight: 600;
  }
  .compilazione-answer__value {
    margin: 0;
    color: rgba(231, 239, 255, 0.92);
    white-space: pre-wrap;
  }
  .builder-layout {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    align-items: stretch;
  }
  .builder-card {
    width: 100%;
    min-width: 0;
  }
  .builder-card-questions {
    min-width: 0;
  }
  .builder-layout > .builder-card {
    width: 100%;
  }
  @media (max-width: 820px) {
    .builder-layout {
      grid-template-columns: minmax(0, 1fr);
    }
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
  .builder-options {
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.03);
  }
  .builder-options-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
  }
  .builder-options-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 8px;
  }
  .builder-option-row {

    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
  }

  .mobile-q-header,.mobile-library,.mobile-collapsible,.mobile-toggle-btn--inline,.mobile-submissions-shell{display:none}
  .desktop-only{display:block}
  @media (max-width:820px){
    .desktop-only,.desktop-table{display:none}
    .mobile-q-header,.mobile-library,.mobile-collapsible,.mobile-submissions-shell{display:block}
    .mobile-q-header{padding:14px;border:1px solid rgba(95,127,192,.35);border-radius:20px;background:radial-gradient(140% 120% at 100% 0%,rgba(36,126,196,.26),transparent 45%),linear-gradient(180deg,#112244 0%,#0b1737 100%);box-shadow:0 20px 40px rgba(0,0,0,.35)}
    .mobile-q-header__top{display:flex;justify-content:space-between;align-items:flex-start}
    .mobile-q-header__eyebrow{margin:0;text-transform:uppercase;letter-spacing:.18em;font-size:12px;font-weight:700;color:#b7cffc}
    .mobile-q-header__title{margin:6px 0 3px;font-size:44px;line-height:.95;font-weight:900}
    .mobile-q-header__subtitle{margin:0 0 12px;font-size:14px;color:rgba(235,243,255,.86)}
    .mobile-toggle-btn{width:40px;height:40px;border-radius:14px;border:0;background:#f6f9ff;color:#0a1228;font-size:30px;font-weight:400}
    .mobile-q-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.mobile-q-stats article{border:1px solid rgba(145,176,255,.22);border-radius:14px;background:rgba(5,13,31,.32);padding:10px}.mobile-q-stats span{font-size:10px;color:#9eb7e8}.mobile-q-stats strong{display:block;margin-top:2px;font-size:34px;line-height:1}
    .mobile-collapsible{margin-top:12px;border:1px solid rgba(255,255,255,.12);border-radius:18px;background:#101a37;padding:14px}
    .mobile-create-head{display:flex;justify-content:space-between;align-items:center}.mobile-create-head h4{margin:0;font-size:31px}.mobile-create-head span{font-size:11px;background:rgba(99,102,241,.22);padding:4px 10px;border-radius:999px}.mobile-create-title{display:none}
    .mobile-form{display:block !important}.mobile-form .field{display:block;width:100% !important;min-width:0 !important;margin-bottom:10px}.mobile-form input{width:100%}.mobile-form .btn.primary{width:100%;border-radius:14px;background:linear-gradient(90deg,#6c63ff,#1bb5f3)}
    .mobile-library{margin-top:14px}.mobile-library__head{display:flex;justify-content:space-between;align-items:center}.mobile-library__head h3{font-size:35px;margin:0}.mobile-library__head span{font-size:12px;color:rgba(232,240,255,.65)}.mobile-search input{width:100%;border-radius:14px;border:1px solid rgba(255,255,255,.14);background:#08132f;color:#eaf1ff;padding:11px 12px}
    .mobile-q-card{margin-top:10px;padding:13px;border-radius:16px;border:1px solid rgba(255,255,255,.12);background:linear-gradient(180deg,rgba(255,255,255,.035),rgba(255,255,255,.02))}.mobile-q-card__title{display:flex;align-items:center;gap:7px}.mobile-q-card__title strong{font-size:32px;line-height:1;flex:1}
    .mobile-tag{padding:2px 9px;border-radius:999px;border:1px solid rgba(148,206,255,.38);background:rgba(69,130,175,.2);font-size:10px;font-weight:700;color:#d7eeff}
    .mobile-pill{margin-left:auto;padding:4px 10px;border-radius:999px;border:1px solid rgba(99,255,188,.4);background:rgba(75,212,165,.14);font-size:12px;font-weight:700;color:#8af8cd}.mobile-q-card p{font-size:14px;color:rgba(239,247,255,.76)}
    .mobile-q-card__stats{display:grid;grid-template-columns:1fr 1fr;gap:8px}.mobile-q-card__stats>div{border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:8px}.mobile-q-card__actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}.mobile-q-card__actions .btn{width:100%;border-radius:11px;background:rgba(255,255,255,.08)}
    .mobile-submissions-shell{margin-top:14px;border:1px solid rgba(255,255,255,.11);border-radius:18px;padding:10px;background:rgba(255,255,255,.02)}.mobile-submissions-toggle{width:100%;display:flex;justify-content:space-between;align-items:center;padding:4px;border-radius:12px;border:0;background:transparent;color:#fff}.mobile-submissions-toggle h3{margin:0;font-size:35px;line-height:1}
    .mobile-sub-card{margin-top:8px;padding:11px;border-radius:12px;border:1px solid rgba(255,255,255,.12);display:flex;justify-content:space-between;background:rgba(255,255,255,.03)}
  }


</style>
<?php renderEnd('<script>
(function(){

const mobileToggles=[...document.querySelectorAll("[data-mobile-toggle]")];
mobileToggles.forEach((btn)=>{btn.addEventListener("click",()=>{const id=btn.getAttribute("data-mobile-toggle");const target=document.getElementById(id);if(!target) return;const open=target.hidden;target.hidden=!open;btn.textContent=open?"×":"+";btn.setAttribute("aria-expanded", String(open));});});

const add=document.getElementById("addDomandaForm"); const fb=document.getElementById("builderFeedback");
const tipoDomanda=add?.querySelector(\'select[name="tipoDomanda"]\');
const optionsBox=add?.querySelector("[data-builder-options]");
const optionsList=add?.querySelector("[data-builder-options-list]");
const addOptionBtn=add?.querySelector("[data-add-option]");

function createOptionRow(value=""){
  if(!optionsList) return;
  const row=document.createElement("div");
  row.className="builder-option-row";
  row.innerHTML=`<input class="builder-input" type="text" value="${value.replace(/"/g,"&quot;")}" placeholder="Testo opzione">
    <button class="btn" type="button" data-remove-option>Rimuovi</button>`;
  row.querySelector("[data-remove-option]")?.addEventListener("click", ()=>{ row.remove(); ensureMinRows(); });
  optionsList.appendChild(row);
}

function ensureMinRows(){
  if(!optionsList || !optionsBox || optionsBox.hidden) return;
  if(optionsList.children.length===0){ createOptionRow(""); createOptionRow(""); }
}

function syncOptionsVisibility(){
  if(!tipoDomanda || !optionsBox || !optionsList) return;
  const needsOptions=tipoDomanda.value==="single_choice"||tipoDomanda.value==="multiple_choice";
  optionsBox.hidden=!needsOptions;
  if(needsOptions){ ensureMinRows(); }
}

tipoDomanda?.addEventListener("change", syncOptionsVisibility);
addOptionBtn?.addEventListener("click", ()=>createOptionRow(""));
syncOptionsVisibility();

add?.addEventListener("submit", async function(e){
  e.preventDefault();
  const form=new FormData(add);
  const payload=Object.fromEntries(form.entries());
  if(tipoDomanda && (tipoDomanda.value==="single_choice"||tipoDomanda.value==="multiple_choice")){
    const opzioni=[...optionsList.querySelectorAll("input")].map((i)=>i.value.trim()).filter(Boolean);
    if(opzioni.length<2){ fb.textContent="Inserisci almeno 2 opzioni."; return; }
    payload.opzioni=opzioni;
  }
  const r=await fetch("../api/questionari/domanda_create.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)});
  const d=await r.json();
  fb.textContent=d.ok?"Domanda aggiunta. Ricarico...":(d.error||"Errore");
  if(d.ok) location.reload();
});
const modal=document.querySelector("[data-assign-questionario-modal]");
const openBtn=document.querySelector("[data-open-assign-modal]");
const closeBtn=document.querySelector("[data-close-assign-modal]");
const submitBtn=document.querySelector("[data-submit-assign]");
const toggleAll=document.querySelector("[data-assign-toggle-all]");
const feedback=document.querySelector("[data-assign-feedback]");
const idQuestionario=' . (int)$selectedQuestionario['idQuestionario'] . ';

function setFeedback(message, ok){ if(!feedback) return; feedback.textContent=message||""; feedback.classList.toggle("ok",!!ok); }
function syncToggleAll(){
  if(!toggleAll) return;
  const checkboxes=[...document.querySelectorAll("[data-assign-cliente]")];
  if(checkboxes.length===0){ toggleAll.checked=false; return; }
  toggleAll.checked=checkboxes.every((el)=>el.checked);
}
function toggleModal(open){ if(!modal) return; modal.classList.toggle("open", !!open); if(open){ syncToggleAll(); } if(!open) setFeedback("", false); }
function selectedClienti(){ return [...document.querySelectorAll("[data-assign-cliente]:checked")].map(i=>parseInt(i.value,10)).filter(Number.isFinite); }

openBtn?.addEventListener("click", ()=>toggleModal(true));
closeBtn?.addEventListener("click", ()=>toggleModal(false));
modal?.addEventListener("click", (e)=>{ if(e.target===modal) toggleModal(false); });
toggleAll?.addEventListener("change", function(){ document.querySelectorAll("[data-assign-cliente]").forEach((el)=>{ el.checked=toggleAll.checked; }); });
document.querySelectorAll("[data-assign-cliente]").forEach((el)=>el.addEventListener("change", syncToggleAll));
submitBtn?.addEventListener("click", async function(){
  const clienti=selectedClienti();
  if(clienti.length===0){ setFeedback("Seleziona almeno un cliente.", false); return; }
  const r=await fetch("../api/questionari/assegna.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({idQuestionario, clienti})});
  const d=await r.json();
  if(d.ok){ setFeedback(`Assegnati ${d.inserted} clienti.`, true); setTimeout(()=>location.reload(), 500); return; }
  setFeedback(d.error||"Errore durante assegnazione.", false);
});

const compilazioneRows=[...document.querySelectorAll("[data-compilazione-row]")];
const compilazionePanel=document.querySelector("[data-compilazione-panel]");
const compilazioneTitle=document.querySelector("[data-compilazione-title]");
const compilazioneMeta=document.querySelector("[data-compilazione-meta]");
const compilazioneStato=document.querySelector("[data-compilazione-stato]");
const compilazioneContent=document.querySelector("[data-compilazione-content]");
const selectedCompilazioneId=' . $selectedCompilazioneId . ';
let openedCompilazioneId=0;

function htmlesc(value){
  return String(value ?? "").replace(/[&<>"\']/g, function(ch){
    if(ch==="&") return "&amp;";
    if(ch==="<") return "&lt;";
    if(ch===">") return "&gt;";
    if(ch==="\"") return "&quot;";
    if(ch==="\'") return "&#039;";
    return ch;
  });
}
function fmtAnswer(risposta){
  if(!risposta) return "—";
  if(risposta.tipoDomanda==="multiple_choice"){
    try{
      const parsed=JSON.parse(risposta.valoreJson || "[]");
      if(Array.isArray(parsed) && parsed.length){ return parsed.join(", "); }
    }catch(_){}
    return "—";
  }
  if(risposta.tipoDomanda==="number"){ return risposta.valoreNumero ?? "—"; }
  if(risposta.tipoDomanda==="date"){ return risposta.valoreData || "—"; }
  if(risposta.tipoDomanda==="consent_checkbox"){ return Number(risposta.valoreBoolean)===1 ? "Sì" : "No"; }
  return risposta.valoreTesto || "—";
}
function setActiveRow(id){
  compilazioneRows.forEach((row)=>row.classList.toggle("is-active", Number(row.dataset.idCompilazione)===Number(id)));
}
function updateUrl(idCompilazione){
  const url=new URL(window.location.href);
  if(Number(idCompilazione)>0){
    url.searchParams.set("idCompilazione", String(idCompilazione));
  }else{
    url.searchParams.delete("idCompilazione");
  }
  history.replaceState({}, "", url.toString());
}
function closeCompilazione(updateHistory=true){
  if(!compilazionePanel || !compilazioneContent) return;
  compilazionePanel.hidden=true;
  compilazioneContent.innerHTML="";
  setActiveRow(0);
  openedCompilazioneId=0;
  if(updateHistory) updateUrl(0);
}
async function openCompilazione(idCompilazione, updateHistory=true){
  if(!Number.isFinite(Number(idCompilazione)) || Number(idCompilazione)<1) return;
  if(!compilazionePanel || !compilazioneContent || !compilazioneTitle || !compilazioneMeta || !compilazioneStato) return;
  if(openedCompilazioneId===Number(idCompilazione) && !compilazionePanel.hidden){
    closeCompilazione(updateHistory);
    return;
  }
  compilazionePanel.hidden=false;
  compilazioneContent.innerHTML="<p class=\"muted\" style=\"margin:0\">Caricamento risposte...</p>";
  setActiveRow(idCompilazione);
  openedCompilazioneId=Number(idCompilazione);
  try{
    const response=await fetch(`../api/questionari/compilazione_detail.php?idCompilazione=${idCompilazione}`);
    const payload=await response.json();
    if(!payload.ok){ throw new Error(payload.error || "Errore caricamento."); }
    const comp=payload.compilazione || {};
    compilazioneTitle.textContent=`${comp.titolo || "Questionario"} • Compilazione #${comp.numeroCompilazione || "—"}`;
    compilazioneMeta.textContent=`Iniziato: ${comp.iniziatoIl || "—"} · Aggiornato: ${comp.aggiornatoIl || "—"} · Inviato: ${comp.inviatoIl || "—"}`;
    compilazioneStato.textContent=comp.stato || "—";
    const answers=(payload.risposte || []).map((r)=>`
      <article class="compilazione-answer">
        <p class="compilazione-answer__question">${htmlesc(r.testoDomanda || "Domanda")}</p>
        <p class="compilazione-answer__value">${htmlesc(fmtAnswer(r))}</p>
      </article>
    `).join("");
    compilazioneContent.innerHTML=answers || "<p class=\"muted\" style=\"margin:0\">Nessuna risposta disponibile.</p>";
    if(updateHistory) updateUrl(idCompilazione);
  }catch(err){
    openedCompilazioneId=0;
    compilazioneContent.innerHTML=`<p class="muted" style="margin:0">${htmlesc(err?.message || "Errore nel caricamento risposte.")}</p>`;
  }
}

compilazioneRows.forEach((row)=>{
  row.addEventListener("click", ()=>openCompilazione(Number(row.dataset.idCompilazione)));
  row.addEventListener("keydown", (event)=>{
    if(event.key==="Enter" || event.key===" "){
      event.preventDefault();
      openCompilazione(Number(row.dataset.idCompilazione));
    }
  });
});
if(selectedCompilazioneId>0){ openCompilazione(selectedCompilazioneId, false); }
})();
</script>'); ?>
