<?php

require_once __DIR__ . '/../../config/database.php';

class ProgrammiModel
{
    public static function getProfessionistaIdByUserId(int $idUtente): ?int
    {
        $row = Database::exec(
            'SELECT idProfessionista FROM Professionisti WHERE idUtente = ? LIMIT 1',
            [$idUtente]
        )->fetch();

        return $row ? (int)$row['idProfessionista'] : null;
    }

    public static function listFolders(int $professionistaId): array
    {
        $stmt = Database::exec(
            'SELECT idCartella, professionista, nome, ordine, creataIl
             FROM ProgrammiCartelle
             WHERE professionista = ?
             ORDER BY ordine ASC, nome ASC',
            [$professionistaId]
        );

        return $stmt->fetchAll();
    }

    public static function listProgramTemplates(int $userId): array
    {
        $stmt = Database::exec(
            'SELECT p.idProgramma, p.titolo, p.descrizione, p.aggiornatoIl, p.cartellaId,
                    c.nome AS cartellaNome,
                    (SELECT COUNT(*) FROM GiorniAllenamento g WHERE g.programma = p.idProgramma) AS totaleGiorni
             FROM ProgrammiAllenamento p
             LEFT JOIN ProgrammiCartelle c ON c.idCartella = p.cartellaId
             WHERE p.creatoreUtente = ?
               AND p.isTemplate = 1
             ORDER BY p.aggiornatoIl DESC, p.idProgramma DESC',
            [$userId]
        );

        return $stmt->fetchAll();
    }

    public static function listAssignedByProgram(int $programId): array
    {
        $stmt = Database::exec(
            'SELECT a.idAssegnazione, a.programma, a.cliente, a.stato, a.assegnatoIl,
                    u.nome, u.cognome
             FROM AssegnazioniProgramma a
             INNER JOIN Clienti c ON c.idCliente = a.cliente
             INNER JOIN Utenti u ON u.idUtente = c.idUtente
             WHERE a.programma = ?
             ORDER BY a.assegnatoIl DESC',
            [$programId]
        );

        return $stmt->fetchAll();
    }

    public static function createFolder(int $professionistaId, string $nome): int
    {
        $maxOrdine = Database::exec(
            'SELECT COALESCE(MAX(ordine), 0) AS maxOrdine FROM ProgrammiCartelle WHERE professionista = ?',
            [$professionistaId]
        )->fetch();

        $ordine = ((int)($maxOrdine['maxOrdine'] ?? 0)) + 1;

        Database::exec(
            'INSERT INTO ProgrammiCartelle (professionista, nome, ordine, creataIl)
             VALUES (?, ?, ?, NOW())',
            [$professionistaId, $nome, $ordine]
        );

        return (int)Database::pdo()->lastInsertId();
    }

    public static function createProgramTemplate(int $userId, ?int $cartellaId, string $titolo, string $descrizione): int
    {
        Database::exec(
            "INSERT INTO ProgrammiAllenamento
             (cliente, creatoreUtente, origine, stato, titolo, descrizione, versione, programmaPrecedente, creatoIl, aggiornatoIl, isTemplate, cartellaId)
             VALUES (NULL, ?, 'manuale', 'bozza', ?, ?, 1, NULL, NOW(), NOW(), 1, ?)",
            [$userId, $titolo, $descrizione, $cartellaId]
        );

        return (int)Database::pdo()->lastInsertId();
    }

    public static function isProgramOwnedByUser(int $programId, int $userId): bool
    {
        $row = Database::exec(
            'SELECT idProgramma FROM ProgrammiAllenamento WHERE idProgramma = ? AND creatoreUtente = ? LIMIT 1',
            [$programId, $userId]
        )->fetch();

        return (bool)$row;
    }

    public static function getProgramDetails(int $programId, int $userId): ?array
    {
        $program = Database::exec(
            'SELECT idProgramma, titolo, descrizione, aggiornatoIl, creatoreUtente, isTemplate, cartellaId
             FROM ProgrammiAllenamento
             WHERE idProgramma = ? AND creatoreUtente = ?
             LIMIT 1',
            [$programId, $userId]
        )->fetch();

        if (!$program) {
            return null;
        }

        $giorniStmt = Database::exec(
            'SELECT g.idGiorno, g.programma, g.nome, g.ordine, g.note,
                    (
                        SELECT GROUP_CONCAT(e.nome ORDER BY eg.ordine ASC SEPARATOR ", ")
                        FROM EserciziGiorno eg
                        INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
                        WHERE eg.giorno = g.idGiorno
                    ) AS previewEsercizi
             FROM GiorniAllenamento g
             WHERE g.programma = ?
             ORDER BY g.ordine ASC, g.idGiorno ASC',
            [$programId]
        );

        $program['giorni'] = $giorniStmt->fetchAll();
        $program['assegnazioni'] = self::listAssignedByProgram($programId);

        return $program;
    }

    public static function addGiornoToProgram(int $programId, string $nome): int
    {
        $row = Database::exec(
            'SELECT COALESCE(MAX(ordine), 0) AS maxOrdine FROM GiorniAllenamento WHERE programma = ?',
            [$programId]
        )->fetch();
        $ordine = ((int)($row['maxOrdine'] ?? 0)) + 1;

        Database::exec(
            'INSERT INTO GiorniAllenamento (programma, nome, ordine, note)
             VALUES (?, ?, ?, NULL)',
            [$programId, $nome, $ordine]
        );

        return (int)Database::pdo()->lastInsertId();
    }

    public static function renameProgram(int $programId, string $titolo, string $descrizione): void
    {
        Database::exec(
            'UPDATE ProgrammiAllenamento
             SET titolo = ?, descrizione = ?, aggiornatoIl = NOW()
             WHERE idProgramma = ?',
            [$titolo, $descrizione, $programId]
        );
    }

    public static function deleteProgram(int $programId): void
    {
        Database::exec(
            "UPDATE ProgrammiAllenamento
             SET stato = 'archiviato', aggiornatoIl = NOW()
             WHERE idProgramma = ?",
            [$programId]
        );
    }

    public static function assignProgramToClient(int $programId, int $clientId, string $stato): int
    {
        Database::exec(
            'INSERT INTO AssegnazioniProgramma (programma, cliente, assegnatoIl, stato)
             VALUES (?, ?, NOW(), ?)',
            [$programId, $clientId, $stato]
        );

        return (int)Database::pdo()->lastInsertId();
    }

    public static function duplicateProgram(int $programId, int $userId, string $newTitle): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $source = Database::exec(
                'SELECT titolo, descrizione, cartellaId FROM ProgrammiAllenamento WHERE idProgramma = ? AND creatoreUtente = ? LIMIT 1',
                [$programId, $userId]
            )->fetch();

            if (!$source) {
                throw new RuntimeException('Programma non trovato.');
            }

            $newProgramId = self::createProgramTemplate(
                $userId,
                isset($source['cartellaId']) ? (int)$source['cartellaId'] : null,
                $newTitle,
                (string)($source['descrizione'] ?? '')
            );

            $giorniStmt = Database::exec(
                'SELECT idGiorno, nome, ordine, note
                 FROM GiorniAllenamento
                 WHERE programma = ?
                 ORDER BY ordine ASC, idGiorno ASC',
                [$programId]
            );

            while ($giorno = $giorniStmt->fetch()) {
                Database::exec(
                    'INSERT INTO GiorniAllenamento (programma, nome, ordine, note)
                     VALUES (?, ?, ?, ?)',
                    [$newProgramId, $giorno['nome'], (int)$giorno['ordine'], $giorno['note']]
                );
                $newGiornoId = (int)Database::pdo()->lastInsertId();

                $exStmt = Database::exec(
                    'SELECT idEsercizioGiorno, esercizio, ordine, istruzioni, urlVideo
                     FROM EserciziGiorno
                     WHERE giorno = ?
                     ORDER BY ordine ASC, idEsercizioGiorno ASC',
                    [(int)$giorno['idGiorno']]
                );

                while ($exercise = $exStmt->fetch()) {
                    Database::exec(
                        'INSERT INTO EserciziGiorno (giorno, esercizio, ordine, istruzioni, urlVideo)
                         VALUES (?, ?, ?, ?, ?)',
                        [$newGiornoId, (int)$exercise['esercizio'], (int)$exercise['ordine'], $exercise['istruzioni'], $exercise['urlVideo']]
                    );
                    $newEsercizioGiornoId = (int)Database::pdo()->lastInsertId();

                    $setsStmt = Database::exec(
                        'SELECT numeroSerie, targetReps, repsMin, repsMax, targetCarico, targetRPE, recuperoSecondi, tempo, note
                         FROM SeriePrescritte
                         WHERE esercizioGiorno = ?
                         ORDER BY numeroSerie ASC',
                        [(int)$exercise['idEsercizioGiorno']]
                    );

                    while ($set = $setsStmt->fetch()) {
                        Database::exec(
                            'INSERT INTO SeriePrescritte
                             (esercizioGiorno, numeroSerie, targetReps, repsMin, repsMax, targetCarico, targetRPE, recuperoSecondi, tempo, note)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                            [
                                $newEsercizioGiornoId,
                                (int)$set['numeroSerie'],
                                $set['targetReps'],
                                $set['repsMin'],
                                $set['repsMax'],
                                $set['targetCarico'],
                                $set['targetRPE'],
                                $set['recuperoSecondi'],
                                $set['tempo'],
                                $set['note'],
                            ]
                        );
                    }
                }
            }

            $pdo->commit();
            return $newProgramId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function listPtClients(int $professionistaId): array
    {
        $stmt = Database::exec(
            "SELECT c.idCliente, u.nome, u.cognome
             FROM Associazioni a
             INNER JOIN Clienti c ON c.idCliente = a.cliente
             INNER JOIN Utenti u ON u.idUtente = c.idUtente
             WHERE a.professionista = ?
               AND a.tipoAssociazione = 'pt'
               AND a.attivaFlag = 1
             ORDER BY u.nome ASC, u.cognome ASC",
            [$professionistaId]
        );

        return $stmt->fetchAll();
    }
}
