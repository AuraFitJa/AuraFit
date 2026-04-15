<?php
require_once __DIR__ . '/_helpers.php';
q_require_method('POST');
$ctx=q_require_professionista_context(); $data=q_parse_json_body();
$idQuestionario=filter_var($data['idQuestionario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$idQuestionario||!q_questionario_owned((int)$idQuestionario,$ctx['professionistaId'])) q_json_response(['ok'=>false,'error'=>'Questionario non autorizzato.'],403);
$tipo=trim((string)($data['tipoDomanda']??'short_text')); $testo=trim((string)($data['testoDomanda']??''));
if($testo==='') q_json_response(['ok'=>false,'error'=>'Testo domanda obbligatorio.'],422);
$tipiScelta=['single_choice','multiple_choice'];
$opzioni=array_values(array_filter(array_map(static function($item){ return trim((string)$item); }, is_array($data['opzioni']??null)?$data['opzioni']:[]), static function($item){ return $item!==''; }));
if(in_array($tipo,$tipiScelta,true) && count($opzioni)<2) q_json_response(['ok'=>false,'error'=>'Inserisci almeno 2 opzioni.'],422);
$max=Database::exec('SELECT COALESCE(MAX(ordine),0) as maxOrdine FROM QuestionarioDomande WHERE questionario=?',[$idQuestionario])->fetch();
$ordine=((int)($max['maxOrdine']??0))+1;
Database::exec('INSERT INTO QuestionarioDomande (questionario,tipoDomanda,testoDomanda,descrizione,placeholderText,ordine,impostazioniJson,creatoIl,aggiornatoIl) VALUES (?,?,?,?,?,?,?,NOW(),NOW())',[$idQuestionario,$tipo,$testo,'','',$ordine,json_encode($data['impostazioniJson']??new stdClass())]);
$idDomanda=(int)Database::pdo()->lastInsertId();
if(in_array($tipo,$tipiScelta,true)){
  foreach($opzioni as $indice=>$opzione){
    Database::exec(
      'INSERT INTO QuestionarioOpzioni (domanda,labelOpzione,valoreOpzione,ordine) VALUES (?,?,?,?)',
      [$idDomanda,$opzione,$opzione,$indice+1]
    );
  }
}
q_json_response(['ok'=>true,'idDomanda'=>$idDomanda]);
