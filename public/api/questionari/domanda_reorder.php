<?php
require_once __DIR__ . '/_helpers.php';
q_require_method('POST');
$ctx=q_require_professionista_context(); $data=q_parse_json_body();
$items=$data['domande']??null; if(!is_array($items)) q_json_response(['ok'=>false,'error'=>'Payload non valido.'],422);
Database::pdo()->beginTransaction();
try{
foreach($items as $item){
  $id=filter_var($item['idDomanda']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  $ordine=filter_var($item['ordine']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$id||!$ordine) continue;
  Database::exec('UPDATE QuestionarioDomande d INNER JOIN Questionari q ON q.idQuestionario=d.questionario SET d.ordine=? WHERE d.idDomanda=? AND q.professionista=?',[$ordine,$id,$ctx['professionistaId']]);
}
Database::pdo()->commit();
}catch(Throwable $e){Database::pdo()->rollBack();q_json_response(['ok'=>false,'error'=>'Riordino fallito.'],500);} 
q_json_response(['ok'=>true]);
