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
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
