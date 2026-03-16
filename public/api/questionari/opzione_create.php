<?php
require_once __DIR__ . '/_helpers.php'; q_require_method('POST');
$ctx=q_require_professionista_context(); $data=q_parse_json_body();
$idDomanda=filter_var($data['idDomanda']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$idDomanda) q_json_response(['ok'=>false,'error'=>'Domanda non valida.'],422);
$row=Database::exec('SELECT d.idDomanda FROM QuestionarioDomande d INNER JOIN Questionari q ON q.idQuestionario=d.questionario WHERE d.idDomanda=? AND q.professionista=? LIMIT 1',[$idDomanda,$ctx['professionistaId']])->fetch();
if(!$row) q_json_response(['ok'=>false,'error'=>'Non autorizzato.'],403);
$label=trim((string)($data['labelOpzione']??'')); if($label==='') q_json_response(['ok'=>false,'error'=>'Label obbligatoria.'],422);
$max=Database::exec('SELECT COALESCE(MAX(ordine),0) AS maxOrdine FROM QuestionarioOpzioni WHERE domanda=?',[$idDomanda])->fetch();
$ordine=((int)($max['maxOrdine']??0))+1;
Database::exec('INSERT INTO QuestionarioOpzioni (domanda,labelOpzione,valoreOpzione,ordine) VALUES (?,?,?,?)',[$idDomanda,$label,trim((string)($data['valoreOpzione']??$label)),$ordine]);
q_json_response(['ok'=>true,'idOpzione'=>(int)Database::pdo()->lastInsertId()]);
