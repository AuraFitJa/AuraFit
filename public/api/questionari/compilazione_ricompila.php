<?php
require_once __DIR__ . '/_helpers.php'; q_require_method('POST');
$ctx=q_require_cliente_context(); $data=q_parse_json_body();
$idAss=filter_var($data['idAssegnazioneQuestionario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$idAss) q_json_response(['ok'=>false,'error'=>'Assegnazione non valida.'],422);
$ass=Database::exec("SELECT idAssegnazioneQuestionario,questionario FROM QuestionarioAssegnazioni WHERE idAssegnazioneQuestionario=? AND cliente=? AND stato='attivo' LIMIT 1",[$idAss,$ctx['clienteId']])->fetch();
if(!$ass) q_json_response(['ok'=>false,'error'=>'Assegnazione non trovata.'],404);
$last=Database::exec("SELECT idCompilazione, numeroCompilazione FROM QuestionarioCompilazioni WHERE assegnazione=? AND cliente=? AND stato='inviato' ORDER BY numeroCompilazione DESC LIMIT 1",[$idAss,$ctx['clienteId']])->fetch();
if(!$last) q_json_response(['ok'=>false,'error'=>'Nessuna compilazione inviata da ricompilare.'],409);
$draft=Database::exec("SELECT idCompilazione FROM QuestionarioCompilazioni WHERE assegnazione=? AND cliente=? AND stato='bozza' LIMIT 1",[$idAss,$ctx['clienteId']])->fetch();
if($draft) Database::exec('DELETE FROM QuestionarioCompilazioni WHERE idCompilazione=?',[$draft['idCompilazione']]);
$newNum=((int)$last['numeroCompilazione'])+1;
Database::exec("INSERT INTO QuestionarioCompilazioni (assegnazione,questionario,cliente,numeroCompilazione,stato,iniziatoIl,aggiornatoIl,ricompilazioneDi) VALUES (?,?,?,?, 'bozza',NOW(),NOW(),?)",[$idAss,$ass['questionario'],$ctx['clienteId'],$newNum,$last['idCompilazione']]);
$newId=(int)Database::pdo()->lastInsertId();
$oldAnswers=Database::exec('SELECT domanda,valoreTesto,valoreNumero,valoreData,valoreBoolean,valoreJson FROM QuestionarioRisposte WHERE compilazione=?',[$last['idCompilazione']])->fetchAll();
foreach($oldAnswers as $ans){Database::exec('INSERT INTO QuestionarioRisposte (compilazione,domanda,valoreTesto,valoreNumero,valoreData,valoreBoolean,valoreJson,creatoIl,aggiornatoIl) VALUES (?,?,?,?,?,?,?,?,NOW())',[$newId,$ans['domanda'],$ans['valoreTesto'],$ans['valoreNumero'],$ans['valoreData'],$ans['valoreBoolean'],$ans['valoreJson'],date('Y-m-d H:i:s')]);}
q_json_response(['ok'=>true,'idCompilazione'=>$newId]);
