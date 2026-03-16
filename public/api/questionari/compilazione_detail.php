<?php
require_once __DIR__ . '/_helpers.php'; q_require_method('GET'); q_bootstrap();
$user=q_logged_user(); $roles=q_roles($user);
$idComp=filter_var($_GET['idCompilazione']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$idComp) q_json_response(['ok'=>false,'error'=>'Compilazione non valida.'],422);
$where=''; $params=[$idComp];
if(in_array('cliente',$roles,true)){$clienteId=q_get_cliente_id((int)$user['idUtente']); $where=' AND c.cliente=? '; $params[]=$clienteId;}
elseif(in_array('pt',$roles,true)||in_array('nutrizionista',$roles,true)){$profId=q_get_professionista_id((int)$user['idUtente']); $where=' AND q.professionista=? '; $params[]=$profId;}
else q_json_response(['ok'=>false,'error'=>'Permesso negato.'],403);
$head=Database::exec("SELECT c.*,q.titolo FROM QuestionarioCompilazioni c INNER JOIN Questionari q ON q.idQuestionario=c.questionario WHERE c.idCompilazione=? $where LIMIT 1",$params)->fetch();
if(!$head) q_json_response(['ok'=>false,'error'=>'Compilazione non trovata.'],404);
$answers=Database::exec("SELECT d.idDomanda,d.testoDomanda,d.tipoDomanda,r.valoreTesto,r.valoreNumero,r.valoreData,r.valoreBoolean,r.valoreJson FROM QuestionarioDomande d LEFT JOIN QuestionarioRisposte r ON r.domanda=d.idDomanda AND r.compilazione=? WHERE d.questionario=? ORDER BY d.ordine ASC",[$idComp,$head['questionario']])->fetchAll();
q_json_response(['ok'=>true,'compilazione'=>$head,'risposte'=>$answers]);
