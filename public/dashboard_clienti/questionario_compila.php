<?php
require __DIR__ . '/common.php';
$errors=[]; $assegnazione=null; $domande=[]; $opzioni=[]; $answers=[]; $idCompilazione=0;
$isViewOnly = (($_GET['view'] ?? '') === '1');
if(!$dbAvailable){$errors[]=$dbError??'DB non disponibile.';} else {
  try{
    $idAssegnazione=(int)($_GET['idAssegnazione']??0);
    $idCompilazioneRequested=(int)($_GET['idCompilazione']??0);
    $cliente=Database::exec('SELECT idCliente FROM Clienti WHERE idUtente=? LIMIT 1',[$user['idUtente']])->fetch();
    if(!$cliente||$idAssegnazione<1){$errors[]='Parametri non validi.';} else {
      $assegnazione=Database::exec("SELECT qa.*,q.titolo,q.descrizione FROM QuestionarioAssegnazioni qa INNER JOIN Questionari q ON q.idQuestionario=qa.questionario WHERE qa.idAssegnazioneQuestionario=? AND qa.cliente=? AND qa.stato='attivo' LIMIT 1",[$idAssegnazione,$cliente['idCliente']])->fetch();
      if(!$assegnazione){$errors[]='Assegnazione non trovata.';} else {
        if($isViewOnly){
          if($idCompilazioneRequested>0){
            $compilazione=Database::exec("SELECT * FROM QuestionarioCompilazioni WHERE idCompilazione=? AND assegnazione=? AND cliente=? AND stato='inviato' LIMIT 1",[$idCompilazioneRequested,$idAssegnazione,$cliente['idCliente']])->fetch();
          } else {
            $compilazione=Database::exec("SELECT * FROM QuestionarioCompilazioni WHERE assegnazione=? AND cliente=? AND stato='inviato' ORDER BY numeroCompilazione DESC LIMIT 1",[$idAssegnazione,$cliente['idCliente']])->fetch();
          }
          if(!$compilazione){
            $errors[]='Nessuna compilazione inviata disponibile da visualizzare.';
          } else {
            $idCompilazione=(int)$compilazione['idCompilazione'];
          }
        } else {
          $draft=Database::exec("SELECT * FROM QuestionarioCompilazioni WHERE assegnazione=? AND cliente=? AND stato='bozza' ORDER BY aggiornatoIl DESC LIMIT 1",[$idAssegnazione,$cliente['idCliente']])->fetch();
          if(!$draft){
            $max=Database::exec('SELECT COALESCE(MAX(numeroCompilazione),0) maxNumero FROM QuestionarioCompilazioni WHERE assegnazione=?',[$idAssegnazione])->fetch();
            $num=((int)$max['maxNumero'])+1;
            Database::exec("INSERT INTO QuestionarioCompilazioni (assegnazione,questionario,cliente,numeroCompilazione,stato,iniziatoIl,aggiornatoIl) VALUES (?,?,?,?, 'bozza',NOW(),NOW())",[$idAssegnazione,$assegnazione['questionario'],$cliente['idCliente'],$num]);
            $idCompilazione=(int)Database::pdo()->lastInsertId();
          } else { $idCompilazione=(int)$draft['idCompilazione']; }
        }

        $domande=Database::exec('SELECT * FROM QuestionarioDomande WHERE questionario=? ORDER BY ordine ASC',[$assegnazione['questionario']])->fetchAll();
        if($domande){$ids=array_map(static function ($d) { return (int)$d['idDomanda']; },$domande);$in=implode(',',array_fill(0,count($ids),'?'));$ops=Database::exec("SELECT * FROM QuestionarioOpzioni WHERE domanda IN ($in) ORDER BY ordine ASC",$ids)->fetchAll();foreach($ops as $o){$opzioni[(int)$o['domanda']][]=$o;}}
        if($idCompilazione>0){
          $risp=Database::exec('SELECT * FROM QuestionarioRisposte WHERE compilazione=?',[$idCompilazione])->fetchAll();
          foreach($risp as $r){$answers[(int)$r['domanda']]=$r;}
        }
      }
    }
  }catch(Throwable $e){$errors[]='Errore apertura questionario.';}
}

$questionarioTitolo = trim((string)($assegnazione['titolo'] ?? '')) !== '' ? (string)$assegnazione['titolo'] : 'Anamnesi Nutrizione';
$questionarioDescrizione = trim((string)($assegnazione['descrizione'] ?? '')) !== '' ? (string)$assegnazione['descrizione'] : 'Compila le informazioni principali del cliente con un’interfaccia più ordinata, leggibile e coerente con la dashboard.';
$categoria = 'Nutrizione';
$statoCompilazione = $isViewOnly ? 'Sola lettura' : 'Bozza';

$numberQuestions = array_values(array_filter($domande, static function ($d) {
  return ($d['tipoDomanda'] ?? '') === 'number';
}));
$multipleChoiceQuestion = null;
$singleChoiceQuestion = null;
$otherQuestions = [];

foreach ($domande as $domanda) {
  $tipo = $domanda['tipoDomanda'] ?? '';
  if ($tipo === 'multiple_choice' && $multipleChoiceQuestion === null) {
    $multipleChoiceQuestion = $domanda;
    continue;
  }
  if ($tipo === 'single_choice' && $singleChoiceQuestion === null) {
    $singleChoiceQuestion = $domanda;
    continue;
  }
  if ($tipo !== 'number') {
    $otherQuestions[] = $domanda;
  }
}

renderStart('Compila questionario','questionari',$email);
?>
<section class="card questionnaire-shell">
  <style>
    .questionnaire-shell{padding:26px;border-radius:30px;background:linear-gradient(160deg,rgba(23,33,58,.95),rgba(12,18,34,.96));border:1px solid rgba(148,163,184,.2);box-shadow:0 24px 48px rgba(3,8,24,.5)}
    .questionnaire-header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;padding-bottom:20px;margin-bottom:22px;border-bottom:1px solid rgba(148,163,184,.2)}
    .questionnaire-badge{display:inline-flex;padding:6px 14px;border-radius:999px;font-size:11px;letter-spacing:.18em;text-transform:uppercase;font-weight:700;color:#67e8f9;background:rgba(6,182,212,.14);border:1px solid rgba(34,211,238,.35)}
    .questionnaire-title{margin:12px 0 10px;font-size:40px;line-height:1.06;letter-spacing:-.03em}
    .questionnaire-subtitle{max-width:760px;font-size:18px;color:rgba(226,232,240,.84);margin:0}
    .questionnaire-actions{display:grid;gap:10px;min-width:220px}
    .btn.gradient-primary{background:linear-gradient(90deg,#6366f1,#22d3ee);border:1px solid rgba(56,189,248,.4);color:#031422}
    .questionnaire-grid{display:grid;grid-template-columns:minmax(0,1.9fr) minmax(280px,1fr);gap:18px}
    .questionnaire-panel{background:linear-gradient(180deg,rgba(30,41,67,.78),rgba(15,23,42,.7));border:1px solid rgba(148,163,184,.2);border-radius:20px;padding:18px}
    .panel-title{margin:0 0 6px;font-size:28px;letter-spacing:-.01em}
    .panel-note{margin:0;color:rgba(203,213,225,.78)}
    .question-list{display:grid;gap:16px;margin-top:14px}
    .field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .question-field{display:grid;gap:7px}
    .question-field label{font-size:14px;font-weight:600;color:#f8fafc}
    .question-field input,.question-field textarea,.question-field select{width:100%;height:44px;padding:10px 14px;border-radius:12px;background:rgba(5,12,30,.85);border:1px solid rgba(148,163,184,.26);color:#f8fafc;outline:none;transition:border-color .2s, box-shadow .2s}
    .question-field textarea{min-height:120px;height:auto;resize:vertical}
    .question-field input:focus,.question-field textarea:focus,.question-field select:focus{border-color:rgba(56,189,248,.8);box-shadow:0 0 0 3px rgba(56,189,248,.2)}
    .helper-text{font-size:12px;color:rgba(148,163,184,.92)}
    .option-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px}
    .option-grid.single-col{grid-template-columns:1fr}
    .option-card{position:relative;display:flex;align-items:center;gap:10px;padding:13px 14px;border-radius:14px;background:rgba(8,15,35,.8);border:1px solid rgba(148,163,184,.24);cursor:pointer;transition:border-color .2s, background .2s, box-shadow .2s}
    .option-card input{width:16px;height:16px;accent-color:#22d3ee}
    .option-card.is-selected{background:linear-gradient(135deg,rgba(30,64,175,.3),rgba(8,47,73,.35));border-color:rgba(34,211,238,.65);box-shadow:0 10px 24px rgba(8,145,178,.2)}
    .sidebar-stack{display:grid;gap:14px}
    .summary-blocks{display:grid;gap:10px;margin-top:12px}
    .summary-item{padding:13px;border-radius:12px;background:rgba(5,12,30,.8);border:1px solid rgba(148,163,184,.2)}
    .summary-item small{display:block;font-size:12px;color:rgba(148,163,184,.9);margin-bottom:6px}
    .summary-item strong{font-size:17px;font-weight:700}
    .status-pill{display:inline-flex;padding:5px 12px;border-radius:999px;background:rgba(245,158,11,.18);border:1px solid rgba(251,191,36,.45);color:#fde68a;font-size:12px;font-weight:700}
    .quick-actions{display:grid;gap:10px;margin-top:10px}
    .quick-actions .btn{width:100%;justify-content:center}
    .info-card{padding:14px;border-radius:16px;border:1px dashed rgba(56,189,248,.35);background:rgba(2,8,23,.66);color:rgba(203,213,225,.85);font-size:14px;line-height:1.45}
    @media (max-width:1050px){.questionnaire-grid{grid-template-columns:1fr}.questionnaire-title{font-size:34px}.questionnaire-header{flex-direction:column}.questionnaire-actions{width:100%;grid-template-columns:repeat(2,minmax(0,1fr));min-width:0}}
    @media (max-width:760px){.questionnaire-shell{padding:18px;border-radius:24px}.questionnaire-title{font-size:30px}.questionnaire-subtitle{font-size:15px}.field-grid,.option-grid{grid-template-columns:1fr}.questionnaire-actions{grid-template-columns:1fr}.panel-title{font-size:24px}}
  </style>

  <header class="questionnaire-header">
    <div>
      <span class="questionnaire-badge">Questionario nutrizione</span>
      <h2 class="questionnaire-title"><?= h($questionarioTitolo) ?></h2>
      <p class="questionnaire-subtitle"><?= h($questionarioDescrizione) ?></p>
    </div>
    <div class="questionnaire-actions">
      <?php if($isViewOnly): ?>
      <a class="btn" href="questionari.php">Torna ai questionari</a>
      <?php else: ?>
      <button class="btn" type="button" id="btnAnteprima">Anteprima</button>
      <button class="btn gradient-primary" type="button" id="inviaQuestionarioTop">Invia questionario</button>
      <?php endif; ?>
    </div>
  </header>

  <?php foreach($errors as $e): ?><div class="alert"><?= h($e) ?></div><?php endforeach; ?>

  <?php if($assegnazione && $idCompilazione>0): ?>
  <form id="questionarioForm">
    <div class="questionnaire-grid">
      <div class="question-list">
        <article class="questionnaire-panel">
          <h3 class="section-title">Dati corporei</h3>
          <p class="panel-note">Inserisci peso e altezza del cliente per iniziare la valutazione.</p>
          <div class="field-grid" style="margin-top:14px">
            <?php for($i=0; $i<2; $i++):
              $d = $numberQuestions[$i] ?? null;
              $id = $d ? (int)$d['idDomanda'] : 0;
              $existing = $d ? ($answers[$id] ?? null) : null;
              $label = $i === 0 ? 'Quanto pesi' : 'Quanto sei alto?';
              $placeholder = $i === 0 ? '80' : '180';
              $helper = $i === 0 ? 'Valore espresso in chilogrammi.' : 'Valore espresso in centimetri.';
              $value = $existing['valoreNumero'] ?? ($placeholder);
            ?>
            <label class="question-field">
              <span><?= h($d['testoDomanda'] ?? $label) ?></span>
              <?php if($d): ?>
              <input type="number" step="0.01" name="risposte[<?= $id ?>]" value="<?= h((string)$value) ?>" placeholder="<?= h($placeholder) ?>" <?= $isViewOnly ? 'disabled' : '' ?>>
              <?php else: ?>
              <input type="number" step="0.01" value="<?= h((string)$value) ?>" placeholder="<?= h($placeholder) ?>" disabled>
              <?php endif; ?>
              <small class="helper-text"><?= h($helper) ?></small>
            </label>
            <?php endfor; ?>
          </div>
        </article>

        <?php if($multipleChoiceQuestion):
          $idMulti = (int)$multipleChoiceQuestion['idDomanda'];
          $multiValues = json_decode((string)($answers[$idMulti]['valoreJson'] ?? '[]'), true);
          if(!is_array($multiValues)){$multiValues=[];}
          if(!$multiValues){$multiValues=['carne'];}
        ?>
        <article class="questionnaire-panel">
          <h3 class="section-title">Abitudini alimentari</h3>
          <p class="panel-note">Seleziona gli alimenti che il cliente consuma abitualmente a pranzo.</p>
          <div class="option-grid" style="margin-top:14px">
            <?php foreach(($opzioni[$idMulti] ?? []) as $o):
              $isChecked = in_array(strtolower((string)$o['valoreOpzione']), array_map('strtolower',$multiValues), true);
            ?>
            <label class="option-card<?= $isChecked ? ' is-selected' : '' ?>">
              <input type="checkbox" name="risposte[<?= $idMulti ?>][]" value="<?= h($o['valoreOpzione']) ?>" <?= $isChecked ? 'checked' : '' ?> <?= $isViewOnly ? 'disabled' : '' ?>>
              <span><?= h($o['labelOpzione']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </article>
        <?php endif; ?>

        <?php if($singleChoiceQuestion):
          $idSingle = (int)$singleChoiceQuestion['idDomanda'];
          $singleValue = (string)($answers[$idSingle]['valoreTesto'] ?? '');
          if($singleValue===''){$singleValue='no';}
        ?>
        <article class="questionnaire-panel">
          <h3 class="section-title">Domanda aggiuntiva</h3>
          <p class="panel-note">Sezione radio resa più leggibile e chiara.</p>
          <div class="option-grid single-col" style="margin-top:14px">
            <?php foreach(($opzioni[$idSingle] ?? []) as $o):
              $isChecked = strtolower((string)$o['valoreOpzione']) === strtolower($singleValue);
            ?>
            <label class="option-card<?= $isChecked ? ' is-selected' : '' ?>">
              <input type="radio" name="risposte[<?= $idSingle ?>]" value="<?= h($o['valoreOpzione']) ?>" <?= $isChecked ? 'checked' : '' ?> <?= $isViewOnly ? 'disabled' : '' ?>>
              <span><?= h($o['labelOpzione']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </article>
        <?php endif; ?>

        <?php if($otherQuestions): ?>
        <article class="questionnaire-panel">
          <h3 class="section-title">Altre domande</h3>
          <p class="panel-note">Completa le sezioni aggiuntive del questionario.</p>
          <div class="question-list" style="margin-top:14px">
            <?php foreach($otherQuestions as $d): $id=(int)$d['idDomanda']; $tipo=$d['tipoDomanda']; $existing=$answers[$id]??null; ?>
            <div class="question-field">
              <label for="q_<?= $id ?>"><?= h($d['testoDomanda']) ?></label>
              <?php if(!empty($d['descrizione'])): ?><small class="helper-text"><?= h($d['descrizione']) ?></small><?php endif; ?>
              <?php if($tipo==='short_text'): ?><input id="q_<?= $id ?>" name="risposte[<?= $id ?>]" value="<?= h((string)($existing['valoreTesto']??'')) ?>" placeholder="<?= h($d['placeholderText']) ?>" <?= $isViewOnly ? 'disabled' : '' ?>>
              <?php elseif($tipo==='long_text'): ?><textarea id="q_<?= $id ?>" name="risposte[<?= $id ?>]" placeholder="<?= h($d['placeholderText']) ?>" <?= $isViewOnly ? 'disabled' : '' ?>><?= h((string)($existing['valoreTesto']??'')) ?></textarea>
              <?php elseif($tipo==='single_choice'): ?>
                <div class="option-grid single-col">
                <?php foreach(($opzioni[$id]??[]) as $o): $checked = (($existing['valoreTesto']??'')===$o['valoreOpzione']); ?>
                  <label class="option-card<?= $checked ? ' is-selected' : '' ?>"><input type="radio" name="risposte[<?= $id ?>]" value="<?= h($o['valoreOpzione']) ?>" <?= $checked?'checked':''; ?> <?= $isViewOnly ? 'disabled' : '' ?>> <span><?= h($o['labelOpzione']) ?></span></label>
                <?php endforeach; ?>
                </div>
              <?php elseif($tipo==='multiple_choice'): $curr=json_decode((string)($existing['valoreJson']??'[]'),true); if(!is_array($curr))$curr=[]; ?>
                <div class="option-grid">
                <?php foreach(($opzioni[$id]??[]) as $o): $checked=in_array($o['valoreOpzione'],$curr,true); ?>
                  <label class="option-card<?= $checked ? ' is-selected' : '' ?>"><input type="checkbox" name="risposte[<?= $id ?>][]" value="<?= h($o['valoreOpzione']) ?>" <?= $checked?'checked':''; ?> <?= $isViewOnly ? 'disabled' : '' ?>> <span><?= h($o['labelOpzione']) ?></span></label>
                <?php endforeach; ?>
                </div>
              <?php elseif($tipo==='number'): ?><input id="q_<?= $id ?>" type="number" step="0.01" name="risposte[<?= $id ?>]" value="<?= h((string)($existing['valoreNumero']??'')) ?>" <?= $isViewOnly ? 'disabled' : '' ?>>
              <?php elseif($tipo==='date'): ?><input id="q_<?= $id ?>" type="date" name="risposte[<?= $id ?>]" value="<?= h((string)($existing['valoreData']??'')) ?>" <?= $isViewOnly ? 'disabled' : '' ?>>
              <?php elseif($tipo==='consent_checkbox'): ?><label class="option-card<?= !empty($existing['valoreBoolean']) ? ' is-selected' : '' ?>"><input type="checkbox" name="risposte[<?= $id ?>]" value="1" <?= !empty($existing['valoreBoolean'])?'checked':''; ?> <?= $isViewOnly ? 'disabled' : '' ?>> <span>Acconsento</span></label><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </article>
        <?php endif; ?>
      </div>

      <aside class="sidebar-stack">
        <article class="questionnaire-panel">
          <h3 class="section-title">Riepilogo sezione</h3>
          <div class="summary-blocks">
            <div class="summary-item"><small>Categoria</small><strong><?= h($categoria) ?></strong></div>
            <div class="summary-item"><small>Descrizione</small><strong style="font-size:16px"><?= h('Questionario iniziale cliente') ?></strong></div>
            <div class="summary-item"><small>Stato compilazione</small><span class="status-pill"><?= h($statoCompilazione) ?></span></div>
          </div>
        </article>

        <article class="questionnaire-panel">
          <h3 class="section-title">Azioni rapide</h3>
          <div class="quick-actions">
            <?php if(!$isViewOnly): ?>
            <button class="btn" type="button" id="salvaBozza">Salva bozza</button>
            <button class="btn" type="button" id="resetCampi">Reimposta campi</button>
            <button class="btn gradient-primary" type="button" id="inviaQuestionario">Invia questionario</button>
            <?php else: ?>
            <a class="btn" href="questionari.php">Chiudi visualizzazione</a>
            <?php endif; ?>
          </div>
        </article>

        <article class="info-card">
          Questo redesign usa una singola section card centrale con gerarchia visiva più forte, componenti uniformi e CTA meglio posizionate.
        </article>
      </aside>
    </div>
    <p class="muted" id="saveFeedback" style="margin:14px 0 0"></p>
  </form>

  <?php if(!$isViewOnly): ?>
  <script>
  (function(){
    const form=document.getElementById('questionarioForm');
    const fb=document.getElementById('saveFeedback');
    const btnSave=document.getElementById('salvaBozza');
    const btnSubmit=document.getElementById('inviaQuestionario');
    const btnSubmitTop=document.getElementById('inviaQuestionarioTop');
    const btnReset=document.getElementById('resetCampi');
    const btnPreview=document.getElementById('btnAnteprima');

    function collect(){
      const fd=new FormData(form);
      const out={idCompilazione:<?= $idCompilazione ?>,risposte:{}};
      for(const [k,v] of fd.entries()){
        const m=k.match(/^risposte\[(\d+)\](\[\])?$/);
        if(!m)continue;
        const id=m[1];
        if(m[2]){
          if(!Array.isArray(out.risposte[id]))out.risposte[id]=[];
          out.risposte[id].push(v);
        }else{
          out.risposte[id]=v;
        }
      }
      return out;
    }

    async function call(url){
      const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(collect())});
      return r.json();
    }

    function refreshOptionCards(){
      form.querySelectorAll('.option-card').forEach((card)=>{
        const input=card.querySelector('input[type="checkbox"],input[type="radio"]');
        card.classList.toggle('is-selected', !!(input && input.checked));
      });
    }

    btnSave.addEventListener('click',async()=>{
      const d=await call('../api/questionari/compilazione_save.php');
      fb.textContent=d.ok?'Bozza salvata':(d.error||'Errore');
    });

    async function submitQuestionario(){
      const saved=await call('../api/questionari/compilazione_save.php');
      if(!saved.ok){
        fb.textContent=saved.error||'Errore salvataggio';
        return;
      }
      const d=await call('../api/questionari/compilazione_submit.php');
      if(d.ok){
        window.location='questionari.php';
      }else{
        fb.textContent=d.error||'Errore invio';
      }
    }

    btnSubmit.addEventListener('click',submitQuestionario);
    btnSubmitTop.addEventListener('click',submitQuestionario);

    btnPreview.addEventListener('click',()=>{
      fb.textContent='Anteprima pronta: controlla i dati e invia quando vuoi.';
    });

    btnReset.addEventListener('click',()=>{
      form.reset();
      refreshOptionCards();
      fb.textContent='Campi reimpostati ai valori iniziali.';
    });

    form.addEventListener('change',refreshOptionCards);
    refreshOptionCards();
  })();
  </script>
  <?php endif; ?>
  <?php endif; ?>
</section>
<?php renderEnd(); ?>
