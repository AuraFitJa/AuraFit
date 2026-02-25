<?php

require_once __DIR__ . '/../../config/database.php';

class SupportoModel
{
    public static function findIdKeyByCode(string $code, bool $forUpdate = false): ?array
    {
        $sql = "SELECT idKey, codice, professionista, tipoKey, stato, clienteUtilizzatore, usataIl, creataIl
                FROM IdKey
                WHERE codice = ?
                LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $row = Database::exec($sql, [$code])->fetch();
        return $row ?: null;
    }

    public static function getClienteIdByUtenteId(int $idUtente): ?int
    {
        $row = Database::exec(
            'SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1',
            [$idUtente]
        )->fetch();

        if (!$row) {
            return null;
        }

        return (int)$row['idCliente'];
    }

    public static function getActiveAssociazione(int $cliente, string $tipo, bool $forUpdate = false): ?array
    {
        $sql = "SELECT idAssociazione, cliente, professionista, tipoAssociazione, stato, attivaFlag, iniziataIl, terminataIl
                FROM Associazioni
                WHERE cliente = ?
                  AND tipoAssociazione = ?
                  AND attivaFlag = 1
                LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $row = Database::exec($sql, [$cliente, $tipo])->fetch();
        return $row ?: null;
    }

    public static function terminateAssociazione(int $idAssociazione): void
    {
        Database::exec(
            "UPDATE Associazioni
             SET attivaFlag = 0,
                 stato = 'terminata',
                 terminataIl = NOW()
             WHERE idAssociazione = ?",
            [$idAssociazione]
        );
    }

    public static function createAssociazione(
        int $cliente,
        int $professionista,
        int $idKeyOrigine,
        string $tipoAssociazione
    ): int {
        Database::exec(
            "INSERT INTO Associazioni
             (cliente, professionista, idKeyOrigine, tipoAssociazione, stato, attivaFlag, iniziataIl)
             VALUES (?, ?, ?, ?, 'attiva', 1, NOW())",
            [$cliente, $professionista, $idKeyOrigine, $tipoAssociazione]
        );

        return (int)Database::pdo()->lastInsertId();
    }

    public static function markIdKeyUsed(int $idKey, int $idCliente): void
    {
        Database::exec(
            'UPDATE IdKey SET clienteUtilizzatore = ?, usataIl = NOW() WHERE idKey = ?',
            [$idCliente, $idKey]
        );
    }

    public static function ensureChatForAssociazione(int $idAssociazione, string $tipoChat): void
    {
        $existing = Database::exec(
            'SELECT idChat FROM Chat WHERE associazione = ? AND tipoChat = ? LIMIT 1',
            [$idAssociazione, $tipoChat]
        )->fetch();

        if ($existing) {
            return;
        }

        Database::exec(
            'INSERT INTO Chat (associazione, tipoChat, creataIl) VALUES (?, ?, NOW())',
            [$idAssociazione, $tipoChat]
        );
    }

    public static function listAssociazioniAttiveCliente(int $idCliente): array
    {
        $stmt = Database::exec(
            "SELECT a.idAssociazione,
                    a.tipoAssociazione,
                    a.iniziataIl,
                    a.professionista,
                    u.nome,
                    u.cognome,
                    u.email
             FROM Associazioni a
             INNER JOIN Professionisti p ON p.idProfessionista = a.professionista
             INNER JOIN Utenti u ON u.idUtente = p.idUtente
             WHERE a.cliente = ?
               AND a.attivaFlag = 1
             ORDER BY a.iniziataIl DESC",
            [$idCliente]
        );

        $byType = [
            'pt' => null,
            'nutrizionista' => null,
        ];

        while ($row = $stmt->fetch()) {
            $tipo = (string)$row['tipoAssociazione'];
            if (!array_key_exists($tipo, $byType)) {
                continue;
            }

            if ($byType[$tipo] !== null) {
                continue;
            }

            $byType[$tipo] = [
                'idAssociazione' => (int)$row['idAssociazione'],
                'professionista' => (int)$row['professionista'],
                'nomeCompleto' => trim((string)$row['nome'] . ' ' . (string)$row['cognome']),
                'email' => (string)$row['email'],
                'iniziataIl' => (string)$row['iniziataIl'],
                'tipoAssociazione' => $tipo,
            ];
        }

        return $byType;
    }

    public static function canSendMessagesForAssociazione(int $idAssociazione): bool
    {
        $row = Database::exec(
            'SELECT idAssociazione FROM Associazioni WHERE idAssociazione = ? AND attivaFlag = 1 AND terminataIl IS NULL LIMIT 1',
            [$idAssociazione]
        )->fetch();

        return (bool)$row;
    }
}
