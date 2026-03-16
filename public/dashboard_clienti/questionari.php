<?php
require __DIR__ . '/common.php';
$errors=[]; $assegnazioni=[];
if(!$dbAvailable){$errors[]=$dbError??'Database non disponibile.';} else {
  try{
    $cliente=Database::exec('SELECT idCliente FROM Clienti WHERE idUtente=? LIMIT 1',[$user['idUtente']])->fetch();
    if(!$cliente){$errors[]='Profilo cliente non trovato.';} else {
      $assegnazioni=Database::exec("SELECT qa.idAssegnazioneQuestionario,qa.assegnatoIl,q.titolo,q.descrizione,
      (SELECT COUNT(*) FROM QuestionarioCompilazioni c WHERE c.assegnazione=qa.idAssegnazioneQuestionario) AS numeroCompilazioni,
      (SELECT c.stato FROM QuestionarioCompilazioni c WHERE c.assegnazione=qa.idAssegnazioneQuestionario ORDER BY c.numeroCompilazione DESC LIMIT 1) AS statoUltima,
      (SELECT c.inviatoIl FROM QuestionarioCompilazioni c WHERE c.assegnazione=qa.idAssegnazioneQuestionario AND c.stato='inviato' ORDER BY c.numeroCompilazione DESC LIMIT 1) AS ultimaDataInvio,
      (SELECT c.idCompilazione FROM QuestionarioCompilazioni c WHERE c.assegnazione=qa.idAssegnazioneQuestionario AND c.stato='bozza' ORDER BY c.aggiornatoIl DESC LIMIT 1) AS bozzaId,
      (SELECT c.idCompilazione FROM QuestionarioCompilazioni c WHERE c.assegnazione=qa.idAssegnazioneQuestionario AND c.stato='inviato' ORDER BY c.numeroCompilazione DESC LIMIT 1) AS ultimaInviata
      FROM QuestionarioAssegnazioni qa INNER JOIN Questionari q ON q.idQuestionario=qa.questionario
      WHERE qa.cliente=? AND qa.stato='attivo' ORDER BY qa.assegnatoIl DESC",[$cliente['idCliente']])->fetchAll();
    }
  }catch(Throwable $e){$errors[]='Errore caricamento questionari.';}
}
renderStart('Questionari','questionari',$email);
?>
<section class="card">
<h2 class="section-title">I miei questionari</h2>
<?php foreach($errors as $e): ?><div class="alert"><?= h($e) ?></div><?php endforeach; ?>
<div style="overflow:auto"><table><thead><tr><th>Questionario</th><th>Stato ultima</th><th>Ultimo invio</th><th># Compilazioni</th><th>Azioni</th></tr></thead><tbody>
<?php foreach($assegnazioni as $a): ?>
<tr>
<td><strong><?= h($a['titolo']) ?></strong><div class="muted"><?= h($a['descrizione']) ?></div></td>
<td><?= h($a['statoUltima'] ?: 'mai compilato') ?></td>
<td><?= h($a['ultimaDataInvio'] ?: '—') ?></td>
<td><?= (int)$a['numeroCompilazioni'] ?></td>
<td>
<?php if(!empty($a['bozzaId'])): ?><a class="btn" href="questionario_compila.php?idAssegnazione=<?= (int)$a['idAssegnazioneQuestionario'] ?>">Continua bozza</a>
<?php elseif(!empty($a['ultimaInviata'])): ?><button class="btn" data-ricompila="<?= (int)$a['idAssegnazioneQuestionario'] ?>">Ricompila</button>
<?php else: ?><a class="btn primary" href="questionario_compila.php?idAssegnazione=<?= (int)$a['idAssegnazioneQuestionario'] ?>">Compila</a><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
<?php renderEnd('<script>document.querySelectorAll("[data-ricompila]").forEach(function(btn){btn.addEventListener("click",async function(){const id=parseInt(btn.dataset.ricompila,10);const r=await fetch("../api/questionari/compilazione_ricompila.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({idAssegnazioneQuestionario:id})});const d=await r.json();if(d.ok){location.href="questionario_compila.php?idAssegnazione="+id;}else{alert(d.error||"Errore ricompilazione");}});});</script>'); ?>
