<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/lib/security.php';

aurafit_start_secure_session();

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
}

$token = aurafit_get_csrf_token();
assert_true(strlen($token) === 64, 'CSRF token deve essere lungo 64 caratteri hex.');
assert_true(aurafit_validate_csrf_token($token), 'CSRF token generato deve validare correttamente.');
assert_true(!aurafit_validate_csrf_token('invalid-token'), 'CSRF token invalido non deve validare.');

$loginCode = file_get_contents(__DIR__ . '/../public/login.php') ?: '';
$registerCode = file_get_contents(__DIR__ . '/../public/register.php') ?: '';
$logoutCode = file_get_contents(__DIR__ . '/../public/logout.php') ?: '';
$clienteCommonCode = file_get_contents(__DIR__ . '/../public/dashboard_clienti/common.php') ?: '';
$professionistaCommonCode = file_get_contents(__DIR__ . '/../public/dashboard_professionisti/common.php') ?: '';
$professionistaClientiCode = file_get_contents(__DIR__ . '/../public/dashboard_professionisti/clienti.php') ?: '';
$dbSampleCode = file_get_contents(__DIR__ . '/../config/database.sample.php') ?: '';

assert_true(strpos($loginCode, "Errore: ") === false, 'Login non deve esporre eccezioni raw all\'utente.');
assert_true(strpos($registerCode, "Errore: ") === false, 'Register non deve esporre eccezioni raw all\'utente.');
assert_true(strpos($logoutCode, '$_SERVER[\'REQUEST_METHOD\']') !== false, 'Logout deve verificare HTTP method.');
assert_true(strpos($logoutCode, 'aurafit_validate_csrf_token') !== false, 'Logout deve validare CSRF token.');
assert_true(strpos($clienteCommonCode, 'emailNormalizzata') !== false, 'Profilo cliente deve aggiornare emailNormalizzata.');
assert_true(strpos($professionistaCommonCode, 'emailNormalizzata') !== false, 'Profilo professionista deve aggiornare emailNormalizzata.');
assert_true(strpos($loginCode, "\$_SESSION['demo_mode']") !== false, 'Il login deve impostare la flag demo_mode in sessione.');
assert_true(strpos($dbSampleCode, 'blockDemoWriteIfNeeded') !== false, 'Database::exec deve invocare il blocco scritture in demo mode.');
assert_true(strpos($dbSampleCode, "'UPDATE'") !== false, 'Le query UPDATE devono essere riconosciute come scritture.');
assert_true(strpos($dbSampleCode, "'SELECT'") === false, 'Le query SELECT non devono essere marcate come scritture bloccate.');
assert_true(strpos($professionistaClientiCode, "Modalità demo: le modifiche non vengono salvate.") !== false, 'Terminazione associazione deve essere bloccata in demo mode.');

echo "All security regression checks passed.\n";
