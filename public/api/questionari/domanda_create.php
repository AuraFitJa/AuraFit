<?php
require_once __DIR__ . '/_helpers.php';
q_require_method('POST');
$ctx=q_require_professionista_context(); $data=q_parse_json_body();
$idQuestionario=filter_var($data['idQuestionario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$idQuestionario||!q_questionario_owned((int)$idQuestionario,$ctx['professionistaId'])) q_json_response(['ok'=>false,'error'=>'Questionario non autorizzato.'],403);
$tipo=trim((string)($data['tipoDomanda']??'short_text')); $testo=trim((string)($data['testoDomanda']??''));
if($testo==='') q_json_response(['ok'=>false,'error'=>'Testo domanda obbligatorio.'],422);
$max=Database::exec('SELECT COALESCE(MAX(ordine),0) as maxOrdine FROM QuestionarioDomande WHERE questionario=?',[$idQuestionario])->fetch();
$ordine=((int)($max['maxOrdine']??0))+1;
Database::exec('INSERT INTO QuestionarioDomande (questionario,tipoDomanda,testoDomanda,descrizione,placeholderText,ordine,impostazioniJson,creatoIl,aggiornatoIl) VALUES (?,?,?,?,?,?,?,NOW(),NOW())',[$idQuestionario,$tipo,$testo,trim((string)($data['descrizione']??'')),trim((string)($data['placeholderText']??'')),$ordine,json_encode($data['impostazioniJson']??new stdClass())]);
q_json_response(['ok'=>true,'idDomanda'=>(int)Database::pdo()->lastInsertId()]);
