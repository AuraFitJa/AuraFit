<?php
require_once __DIR__ . '/_helpers.php'; q_require_method('GET'); q_bootstrap();
$user=q_logged_user(); $roles=q_roles($user);
if(in_array('cliente',$roles,true)){
  $clienteId=q_get_cliente_id((int)$user['idUtente']);
  $rows=Database::exec("SELECT c.idCompilazione,c.numeroCompilazione,c.stato,c.aggiornatoIl,c.inviatoIl,q.titolo
    FROM QuestionarioCompilazioni c INNER JOIN Questionari q ON q.idQuestionario=c.questionario WHERE c.cliente=? ORDER BY c.aggiornatoIl DESC",[$clienteId])->fetchAll();
  q_json_response(['ok'=>true,'items'=>$rows]);
}
if(in_array('pt',$roles,true)||in_array('nutrizionista',$roles,true)){
  $profId=q_get_professionista_id((int)$user['idUtente']);
  $rows=Database::exec("SELECT c.idCompilazione,c.numeroCompilazione,c.stato,c.aggiornatoIl,c.inviatoIl,q.titolo,u.nome,u.cognome
    FROM QuestionarioCompilazioni c
    INNER JOIN Questionari q ON q.idQuestionario=c.questionario
    INNER JOIN Clienti cl ON cl.idCliente=c.cliente
    INNER JOIN Utenti u ON u.idUtente=cl.idUtente
    WHERE q.professionista=? ORDER BY c.aggiornatoIl DESC",[$profId])->fetchAll();
  q_json_response(['ok'=>true,'items'=>$rows]);
}
q_json_response(['ok'=>false,'error'=>'Permesso negato.'],403);
