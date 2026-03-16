<?php
require_once __DIR__ . '/_helpers.php'; q_require_method('POST');
$ctx=q_require_cliente_context(); $data=q_parse_json_body();
$idAss=filter_var($data['idAssegnazioneQuestionario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$idAss) q_json_response(['ok'=>false,'error'=>'Assegnazione non valida.'],422);
$ass=Database::exec("SELECT idAssegnazioneQuestionario,questionario FROM QuestionarioAssegnazioni WHERE idAssegnazioneQuestionario=? AND cliente=? AND stato='attivo' LIMIT 1",[$idAss,$ctx['clienteId']])->fetch();
if(!$ass) q_json_response(['ok'=>false,'error'=>'Assegnazione non trovata.'],404);
$idComp=q_get_or_create_draft((int)$ass['idAssegnazioneQuestionario'],(int)$ass['questionario'],$ctx['clienteId']);
q_json_response(['ok'=>true,'idCompilazione'=>$idComp]);
