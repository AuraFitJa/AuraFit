<?php
require_once __DIR__ . '/_helpers.php'; q_require_method('POST');
$ctx=q_require_cliente_context(); $data=q_parse_json_body();
$idComp=filter_var($data['idCompilazione']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$risposte=is_array($data['risposte']??null)?$data['risposte']:[];
if(!$idComp) q_json_response(['ok'=>false,'error'=>'Compilazione non valida.'],422);
$comp=Database::exec("SELECT c.idCompilazione,c.questionario,c.stato FROM QuestionarioCompilazioni c WHERE c.idCompilazione=? AND c.cliente=? LIMIT 1",[$idComp,$ctx['clienteId']])->fetch();
if(!$comp||$comp['stato']!=='bozza') q_json_response(['ok'=>false,'error'=>'Bozza non disponibile.'],409);
Database::pdo()->beginTransaction();
try{
 foreach($risposte as $idDomandaRaw=>$value){$idDomanda=(int)$idDomandaRaw; if($idDomanda<1) continue;
  $dom=Database::exec('SELECT idDomanda,tipoDomanda FROM QuestionarioDomande WHERE idDomanda=? AND questionario=? LIMIT 1',[$idDomanda,$comp['questionario']])->fetch(); if(!$dom) continue;
  $map=['valoreTesto'=>null,'valoreNumero'=>null,'valoreData'=>null,'valoreBoolean'=>null,'valoreJson'=>null];
  switch($dom['tipoDomanda']){case 'short_text':case 'long_text':case 'single_choice': $map['valoreTesto']=is_scalar($value)?trim((string)$value):''; break; case 'multiple_choice': $arr=is_array($value)?array_values($value):[]; $map['valoreJson']=json_encode($arr); break; case 'number': $map['valoreNumero']=($value===''||$value===null)?null:(float)$value; break; case 'date': $map['valoreData']=($value===''||$value===null)?null:(string)$value; break; case 'consent_checkbox': $map['valoreBoolean']=!empty($value)?1:0; break; }
  $exists=Database::exec('SELECT idRisposta FROM QuestionarioRisposte WHERE compilazione=? AND domanda=? LIMIT 1',[$idComp,$idDomanda])->fetch();
  if($exists){Database::exec('UPDATE QuestionarioRisposte SET valoreTesto=?, valoreNumero=?, valoreData=?, valoreBoolean=?, valoreJson=?, aggiornatoIl=NOW() WHERE idRisposta=?',[$map['valoreTesto'],$map['valoreNumero'],$map['valoreData'],$map['valoreBoolean'],$map['valoreJson'],$exists['idRisposta']]);}
  else{Database::exec('INSERT INTO QuestionarioRisposte (compilazione,domanda,valoreTesto,valoreNumero,valoreData,valoreBoolean,valoreJson,creatoIl,aggiornatoIl) VALUES (?,?,?,?,?,?,?,?,NOW())',[$idComp,$idDomanda,$map['valoreTesto'],$map['valoreNumero'],$map['valoreData'],$map['valoreBoolean'],$map['valoreJson'],date('Y-m-d H:i:s')]);}
 }
 Database::exec('UPDATE QuestionarioCompilazioni SET aggiornatoIl=NOW() WHERE idCompilazione=?',[$idComp]);
 Database::pdo()->commit();
}catch(Throwable $e){Database::pdo()->rollBack(); q_json_response(['ok'=>false,'error'=>'Salvataggio bozza fallito.'],500);} 
q_json_response(['ok'=>true]);
