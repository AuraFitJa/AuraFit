<?php
require __DIR__ . '/common.php';
$errors=[]; $assegnazione=null; $domande=[]; $opzioni=[]; $answers=[]; $idCompilazione=0;
if(!$dbAvailable){$errors[]=$dbError??'DB non disponibile.';} else {
  try{
    $idAssegnazione=(int)($_GET['idAssegnazione']??0);
    $cliente=Database::exec('SELECT idCliente FROM Clienti WHERE idUtente=? LIMIT 1',[$user['idUtente']])->fetch();
    if(!$cliente||$idAssegnazione<1){$errors[]='Parametri non validi.';} else {
      $assegnazione=Database::exec("SELECT qa.*,q.titolo,q.descrizione FROM QuestionarioAssegnazioni qa INNER JOIN Questionari q ON q.idQuestionario=qa.questionario WHERE qa.idAssegnazioneQuestionario=? AND qa.cliente=? AND qa.stato='attivo' LIMIT 1",[$idAssegnazione,$cliente['idCliente']])->fetch();
      if(!$assegnazione){$errors[]='Assegnazione non trovata.';} else {
        $draft=Database::exec("SELECT * FROM QuestionarioCompilazioni WHERE assegnazione=? AND cliente=? AND stato='bozza' ORDER BY aggiornatoIl DESC LIMIT 1",[$idAssegnazione,$cliente['idCliente']])->fetch();
        if(!$draft){
          $max=Database::exec('SELECT COALESCE(MAX(numeroCompilazione),0) maxNumero FROM QuestionarioCompilazioni WHERE assegnazione=?',[$idAssegnazione])->fetch();
          $num=((int)$max['maxNumero'])+1;
          Database::exec("INSERT INTO QuestionarioCompilazioni (assegnazione,questionario,cliente,numeroCompilazione,stato,iniziatoIl,aggiornatoIl) VALUES (?,?,?,?, 'bozza',NOW(),NOW())",[$idAssegnazione,$assegnazione['questionario'],$cliente['idCliente'],$num]);
          $idCompilazione=(int)Database::pdo()->lastInsertId();
        } else { $idCompilazione=(int)$draft['idCompilazione']; }

        $domande=Database::exec('SELECT * FROM QuestionarioDomande WHERE questionario=? ORDER BY ordine ASC',[$assegnazione['questionario']])->fetchAll();
        if($domande){$ids=array_map(static function ($d) { return (int)$d['idDomanda']; },$domande);$in=implode(',',array_fill(0,count($ids),'?'));$ops=Database::exec("SELECT * FROM QuestionarioOpzioni WHERE domanda IN ($in) ORDER BY ordine ASC",$ids)->fetchAll();foreach($ops as $o){$opzioni[(int)$o['domanda']][]=$o;}}
        $risp=Database::exec('SELECT * FROM QuestionarioRisposte WHERE compilazione=?',[$idCompilazione])->fetchAll();
        foreach($risp as $r){$answers[(int)$r['domanda']]=$r;}
      }
    }
  }catch(Throwable $e){$errors[]='Errore apertura questionario.';}
}
renderStart('Compila questionario','questionari',$email);
?>
<section class="card">
<h2 class="section-title"><?= h((string)($assegnazione['titolo'] ?? 'Questionario')) ?></h2>
<p class="muted"><?= h((string)($assegnazione['descrizione'] ?? '')) ?></p>
<?php foreach($errors as $e): ?><div class="alert"><?= h($e) ?></div><?php endforeach; ?>
<?php if($assegnazione && $idCompilazione>0): ?>
<form id="questionarioForm" style="display:flex;flex-direction:column;gap:12px">
<?php foreach($domande as $d): $id=(int)$d['idDomanda']; $tipo=$d['tipoDomanda']; $existing=$answers[$id]??null; ?>
<div class="stat"><strong><?= h($d['testoDomanda']) ?></strong><div class="muted"><?= h($d['descrizione']) ?></div>
<?php if($tipo==='short_text'): ?><input name="risposte[<?= $id ?>]" value="<?= h((string)($existing['valoreTesto']??'')) ?>" placeholder="<?= h($d['placeholderText']) ?>">
<?php elseif($tipo==='long_text'): ?><textarea name="risposte[<?= $id ?>]" placeholder="<?= h($d['placeholderText']) ?>"><?= h((string)($existing['valoreTesto']??'')) ?></textarea>
<?php elseif($tipo==='single_choice'): ?><?php foreach(($opzioni[$id]??[]) as $o): ?><label><input type="radio" name="risposte[<?= $id ?>]" value="<?= h($o['valoreOpzione']) ?>" <?= (($existing['valoreTesto']??'')===$o['valoreOpzione'])?'checked':''; ?>> <?= h($o['labelOpzione']) ?></label><br><?php endforeach; ?>
<?php elseif($tipo==='multiple_choice'): $curr=json_decode((string)($existing['valoreJson']??'[]'),true); if(!is_array($curr))$curr=[]; ?><?php foreach(($opzioni[$id]??[]) as $o): ?><label><input type="checkbox" name="risposte[<?= $id ?>][]" value="<?= h($o['valoreOpzione']) ?>" <?= in_array($o['valoreOpzione'],$curr,true)?'checked':''; ?>> <?= h($o['labelOpzione']) ?></label><br><?php endforeach; ?>
<?php elseif($tipo==='number'): ?><input type="number" step="0.01" name="risposte[<?= $id ?>]" value="<?= h((string)($existing['valoreNumero']??'')) ?>">
<?php elseif($tipo==='date'): ?><input type="date" name="risposte[<?= $id ?>]" value="<?= h((string)($existing['valoreData']??'')) ?>">
<?php elseif($tipo==='consent_checkbox'): ?><label><input type="checkbox" name="risposte[<?= $id ?>]" value="1" <?= !empty($existing['valoreBoolean'])?'checked':''; ?>> Acconsento</label><?php endif; ?>
</div>
<?php endforeach; ?>
<div class="toolbar"><button class="btn" type="button" id="salvaBozza">Salva bozza</button><button class="btn primary" type="button" id="inviaQuestionario">Invia questionario</button></div>
<p class="muted" id="saveFeedback"></p>
</form>
<script>
(function(){const form=document.getElementById('questionarioForm');const fb=document.getElementById('saveFeedback');
function collect(){const fd=new FormData(form);const out={idCompilazione:<?= $idCompilazione ?>,risposte:{}};for(const [k,v] of fd.entries()){const m=k.match(/^risposte\[(\d+)\](\[\])?$/);if(!m)continue;const id=m[1];if(m[2]){if(!Array.isArray(out.risposte[id]))out.risposte[id]=[];out.risposte[id].push(v);}else{out.risposte[id]=v;}}return out;}
async function call(url){const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(collect())});return r.json();}
document.getElementById('salvaBozza').addEventListener('click',async()=>{const d=await call('../api/questionari/compilazione_save.php');fb.textContent=d.ok?'Bozza salvata':(d.error||'Errore');});
document.getElementById('inviaQuestionario').addEventListener('click',async()=>{const saved=await call('../api/questionari/compilazione_save.php');if(!saved.ok){fb.textContent=saved.error||'Errore salvataggio';return;}const d=await call('../api/questionari/compilazione_submit.php');if(d.ok){window.location='questionari.php';}else{fb.textContent=d.error||'Errore invio';}});
})();
</script>
<?php endif; ?>
</section>
<?php renderEnd(); ?>
