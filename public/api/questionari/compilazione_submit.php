<?php
require_once __DIR__ . '/_helpers.php';
q_require_method('POST');
$ctx=q_require_cliente_context();
$data=q_parse_json_body();
$idComp=filter_var($data['idCompilazione']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$idComp) q_json_response(['ok'=>false,'error'=>'Compilazione non valida.'],422);
$comp=Database::exec("SELECT c.*, q.titolo, qa.professionista, u.email AS profEmail, u.nome AS profNome, u.cognome AS profCognome, uc.nome AS clienteNome, uc.cognome AS clienteCognome
FROM QuestionarioCompilazioni c
INNER JOIN QuestionarioAssegnazioni qa ON qa.idAssegnazioneQuestionario=c.assegnazione
INNER JOIN Questionari q ON q.idQuestionario=c.questionario
INNER JOIN Professionisti p ON p.idProfessionista=qa.professionista
INNER JOIN Utenti u ON u.idUtente=p.idUtente
INNER JOIN Clienti cl ON cl.idCliente=c.cliente
INNER JOIN Utenti uc ON uc.idUtente=cl.idUtente
WHERE c.idCompilazione=? AND c.cliente=? LIMIT 1",[$idComp,$ctx['clienteId']])->fetch();
if(!$comp||$comp['stato']!=='bozza') q_json_response(['ok'=>false,'error'=>'Compilazione non inviabile.'],409);
Database::exec("UPDATE QuestionarioCompilazioni SET stato='inviato', aggiornatoIl=NOW(), inviatoIl=NOW() WHERE idCompilazione=?",[$idComp]);
$to=(string)($comp['profEmail']??'');
if(filter_var($to,FILTER_VALIDATE_EMAIL)){
  $cliente=trim((string)$comp['clienteNome'].' '.(string)$comp['clienteCognome']);
  $subject='AuraFit • Nuovo questionario compilato';
  $msg="Ciao,\n\nIl cliente {$cliente} ha inviato il questionario \"{$comp['titolo']}\" (compilazione #{$comp['numeroCompilazione']}) in data ".date('d/m/Y H:i').".\n\nApri la dashboard professionista per vedere le risposte.\n\nAuraFit";
  @mail($to,$subject,$msg,'From: no-reply@aurafit.local');
}
q_json_response(['ok'=>true]);
