<?php
require_once __DIR__ . '/_helpers.php'; q_require_method('POST');
$ctx=q_require_professionista_context(); $data=q_parse_json_body();
$idOpzione=filter_var($data['idOpzione']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$idOpzione) q_json_response(['ok'=>false,'error'=>'Opzione non valida.'],422);
Database::exec('UPDATE QuestionarioOpzioni o INNER JOIN QuestionarioDomande d ON d.idDomanda=o.domanda INNER JOIN Questionari q ON q.idQuestionario=d.questionario SET o.labelOpzione=?, o.valoreOpzione=?, o.ordine=? WHERE o.idOpzione=? AND q.professionista=?',[trim((string)($data['labelOpzione']??'')),trim((string)($data['valoreOpzione']??'')),(int)($data['ordine']??1),$idOpzione,$ctx['professionistaId']]);
q_json_response(['ok'=>true]);
