<?php
require_once __DIR__ . '/_helpers.php'; q_require_method('POST');
$ctx=q_require_professionista_context(); $data=q_parse_json_body();
$idQuestionario=filter_var($data['idQuestionario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$clienti=is_array($data['clienti']??null)?$data['clienti']:[];
if(!$idQuestionario||!q_questionario_owned((int)$idQuestionario,$ctx['professionistaId'])) q_json_response(['ok'=>false,'error'=>'Questionario non autorizzato.'],403);
$inserted=0; foreach($clienti as $raw){$id=filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if(!$id||!q_cliente_associato($ctx['professionistaId'],(int)$id)) continue;
  $exists=Database::exec("SELECT idAssegnazioneQuestionario FROM QuestionarioAssegnazioni WHERE questionario=? AND cliente=? AND professionista=? AND stato='attivo' LIMIT 1",[$idQuestionario,$id,$ctx['professionistaId']])->fetch();
  if($exists) continue;
  Database::exec("INSERT INTO QuestionarioAssegnazioni (questionario,cliente,professionista,stato,assegnatoIl) VALUES (?,?,?,'attivo',NOW())",[$idQuestionario,$id,$ctx['professionistaId']]);
  $inserted++;
}
q_json_response(['ok'=>true,'inserted'=>$inserted]);
