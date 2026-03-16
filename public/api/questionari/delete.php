<?php
require_once __DIR__ . '/_helpers.php';
q_require_method('POST');
$ctx = q_require_professionista_context();
$data = q_parse_json_body();
$idQuestionario = filter_var($data['idQuestionario'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$idQuestionario) q_json_response(['ok'=>false,'error'=>'Questionario non valido.'],422);
if (!q_questionario_owned((int)$idQuestionario, $ctx['professionistaId'])) q_json_response(['ok'=>false,'error'=>'Non autorizzato.'],403);
Database::exec('DELETE FROM Questionari WHERE idQuestionario=? AND professionista=?', [$idQuestionario,$ctx['professionistaId']]);
q_json_response(['ok'=>true]);
