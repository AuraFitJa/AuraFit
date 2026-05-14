<?php
class Database
{
    private static $pdo = null;

    private static $config = [
        'host'    => 'localhost',
        'dbname'  => 'INSERISCI_DBNAME',
        'user'    => 'INSERISCI_USER',
        'pass'    => 'INSERISCI_PASSWORD',
        'charset' => 'utf8mb4',
    ];

    public static function pdo()
    {
        if (self::$pdo !== null) return self::$pdo;

        $c = self::$config;
        $dsn = "mysql:host={$c['host']};dbname={$c['dbname']};charset={$c['charset']}";

        self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$pdo;
    }

    public static function exec($sql, $params = [])
    {
        self::blockDemoWriteIfNeeded((string)$sql);
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private static function isDemoMode(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        return !empty($_SESSION['demo_mode']);
    }

    private static function isWriteQuery(string $sql): bool
    {
        $clean = trim($sql);
        if ($clean === '') {
            return false;
        }

        // Rimuove commenti iniziali (/* ... */ e righe che iniziano con --)
        do {
            $previous = $clean;
            $clean = preg_replace('/^\s*\/\*.*?\*\//s', '', $clean) ?? $clean;
            $clean = preg_replace('/^\s*--[^\r\n]*(?:\r?\n|$)/m', '', $clean) ?? $clean;
            $clean = ltrim($clean);
        } while ($clean !== $previous && $clean !== '');

        if (!preg_match('/^([a-zA-Z]+)/', $clean, $match)) {
            return false;
        }

        $command = strtoupper($match[1]);
        $writeCommands = [
            'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'TRUNCATE', 'CREATE',
            'ALTER', 'DROP', 'RENAME', 'GRANT', 'REVOKE', 'LOCK',
            'UNLOCK', 'CALL'
        ];

        return in_array($command, $writeCommands, true);
    }

    private static function blockDemoWriteIfNeeded(string $sql): void
    {
        if (!self::isDemoMode() || !self::isWriteQuery($sql)) {
            return;
        }

        http_response_code(403);

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        $isJsonRequest = strpos($accept, 'application/json') !== false
            || $requestedWith === 'xmlhttprequest'
            || strpos($contentType, 'application/json') !== false;

        if ($isJsonRequest) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'Modalità demo: le modifiche non vengono salvate.'
            ]);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Modalità demo</title></head><body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#070A12;color:#EAF0FF;padding:24px;"><h1 style="margin-top:0;">Modalità demo</h1><p>Questo account è dimostrativo. Puoi esplorare la piattaforma, ma non puoi modificare o cancellare i dati demo.</p></body></html>';
        exit;
    }
}
