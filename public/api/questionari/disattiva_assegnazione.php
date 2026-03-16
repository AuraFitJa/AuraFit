<?php
require_once __DIR__ . '/_helpers.php'; q_require_method('POST');
$ctx=q_require_professionista_context(); $data=q_parse_json_body();
$id=filter_var($data['idAssegnazioneQuestionario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id) q_json_response(['ok'=>false,'error'=>'Assegnazione non valida.'],422);
Database::exec("UPDATE QuestionarioAssegnazioni SET stato='disattivato', disattivatoIl=NOW() WHERE idAssegnazioneQuestionario=? AND professionista=?",[$id,$ctx['professionistaId']]);
q_json_response(['ok'=>true]);
