<?php
require_once __DIR__ . '/_helpers.php';
q_require_method('POST');
$ctx=q_require_professionista_context(); $data=q_parse_json_body();
$idDomanda=filter_var($data['idDomanda']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$idDomanda) q_json_response(['ok'=>false,'error'=>'Domanda non valida.'],422);
$ok=Database::exec('DELETE d FROM QuestionarioDomande d INNER JOIN Questionari q ON q.idQuestionario=d.questionario WHERE d.idDomanda=? AND q.professionista=?',[$idDomanda,$ctx['professionistaId']]);
q_json_response(['ok'=>true,'deleted'=>$ok->rowCount()]);
