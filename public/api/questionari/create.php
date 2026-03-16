<?php
require_once __DIR__ . '/_helpers.php';
q_require_method('POST');
$ctx = q_require_professionista_context();
$data = q_parse_json_body();
$titolo = trim((string)($data['titolo'] ?? ''));
$descrizione = trim((string)($data['descrizione'] ?? ''));
$categoria = trim((string)($data['categoria'] ?? 'generale'));
$stato = trim((string)($data['stato'] ?? 'attivo'));
if ($titolo === '') q_json_response(['ok'=>false,'error'=>'Titolo obbligatorio.'],422);
Database::exec('INSERT INTO Questionari (professionista,titolo,descrizione,categoria,stato,creatoIl,aggiornatoIl) VALUES (?,?,?,?,?,NOW(),NOW())', [$ctx['professionistaId'],$titolo,$descrizione,$categoria,$stato]);
q_json_response(['ok'=>true,'idQuestionario'=>(int)Database::pdo()->lastInsertId()]);
