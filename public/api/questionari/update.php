<?php
require_once __DIR__ . '/_helpers.php';
q_require_method('POST');
$ctx = q_require_professionista_context();
$data = q_parse_json_body();
$idQuestionario = filter_var($data['idQuestionario'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$idQuestionario) q_json_response(['ok'=>false,'error'=>'Questionario non valido.'],422);
if (!q_questionario_owned((int)$idQuestionario, $ctx['professionistaId'])) q_json_response(['ok'=>false,'error'=>'Non autorizzato.'],403);
$titolo = trim((string)($data['titolo'] ?? ''));
$descrizione = trim((string)($data['descrizione'] ?? ''));
$categoria = trim((string)($data['categoria'] ?? 'generale'));
$stato = trim((string)($data['stato'] ?? 'attivo'));
if ($titolo === '') q_json_response(['ok'=>false,'error'=>'Titolo obbligatorio.'],422);
Database::exec('UPDATE Questionari SET titolo=?, descrizione=?, categoria=?, stato=?, aggiornatoIl=NOW() WHERE idQuestionario=? AND professionista=?', [$titolo,$descrizione,$categoria,$stato,$idQuestionario,$ctx['professionistaId']]);
q_json_response(['ok'=>true]);
