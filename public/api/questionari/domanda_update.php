<?php
require_once __DIR__ . '/_helpers.php';
q_require_method('POST');
$ctx=q_require_professionista_context(); $data=q_parse_json_body();
$idDomanda=filter_var($data['idDomanda']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$idDomanda) q_json_response(['ok'=>false,'error'=>'Domanda non valida.'],422);
$row=Database::exec('SELECT d.idDomanda,d.questionario FROM QuestionarioDomande d INNER JOIN Questionari q ON q.idQuestionario=d.questionario WHERE d.idDomanda=? AND q.professionista=? LIMIT 1',[$idDomanda,$ctx['professionistaId']])->fetch();
if(!$row) q_json_response(['ok'=>false,'error'=>'Non autorizzato.'],403);
Database::exec('UPDATE QuestionarioDomande SET tipoDomanda=?, testoDomanda=?, descrizione=?, placeholderText=?, impostazioniJson=?, aggiornatoIl=NOW() WHERE idDomanda=?',[trim((string)($data['tipoDomanda']??'short_text')),trim((string)($data['testoDomanda']??'')),trim((string)($data['descrizione']??'')),trim((string)($data['placeholderText']??'')),json_encode($data['impostazioniJson']??new stdClass()),$idDomanda]);
q_json_response(['ok'=>true]);
