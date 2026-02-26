<?php

require_once __DIR__ . '/../../config/database.php';

class RoutineModel
{
    public static function getRoutineEditorData(int $giornoId, int $userId): ?array
    {
        $giorno = Database::exec(
            'SELECT g.idGiorno, g.programma, g.nome, g.ordine, g.note, p.titolo AS programmaTitolo
             FROM GiorniAllenamento g
             INNER JOIN ProgrammiAllenamento p ON p.idProgramma = g.programma
             WHERE g.idGiorno = ?
               AND p.creatoreUtente = ?
             LIMIT 1',
            [$giornoId, $userId]
        )->fetch();

        if (!$giorno) {
            return null;
        }

        $exercisesStmt = Database::exec(
            'SELECT eg.idEsercizioGiorno, eg.giorno, eg.esercizio, eg.ordine, eg.istruzioni, eg.urlVideo,
                    e.nome AS esercizioNome, e.categoria, e.muscoloPrincipale,
                    (
                        SELECT GROUP_CONCAT(m.nome ORDER BY m.nome SEPARATOR ", ")
                        FROM EserciziMuscoliSecondari ems
                        INNER JOIN Muscoli m ON m.idMuscolo = ems.muscolo
                        WHERE ems.esercizio = e.idEsercizio
                    ) AS muscoliSecondari
             FROM EserciziGiorno eg
             INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
             WHERE eg.giorno = ?
             ORDER BY eg.ordine ASC, eg.idEsercizioGiorno ASC',
            [$giornoId]
        );

        $giorno['esercizi'] = [];

        while ($exercise = $exercisesStmt->fetch()) {
            $sets = Database::exec(
                'SELECT idSeriePrescritta, numeroSerie, targetReps, repsMin, repsMax, targetCarico, targetRPE, recuperoSecondi, tempo, note
                 FROM SeriePrescritte
                 WHERE esercizioGiorno = ?
                 ORDER BY numeroSerie ASC',
                [(int)$exercise['idEsercizioGiorno']]
            )->fetchAll();

            $exercise['serie'] = $sets;
            $giorno['esercizi'][] = $exercise;
        }

        return $giorno;
    }

    public static function searchExercises(string $query, ?string $equipment, ?string $muscle): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $searchLike = '%' . $query . '%';
        $prefixLike = $query . '%';

        $sql = 'SELECT idEsercizio, nome, categoria, muscoloPrincipale,
                       (
                           SELECT GROUP_CONCAT(m.nome ORDER BY m.nome SEPARATOR ", ")
                           FROM EserciziMuscoliSecondari ems
                           INNER JOIN Muscoli m ON m.idMuscolo = ems.muscolo
                           WHERE ems.esercizio = Esercizi.idEsercizio
                       ) AS muscoliSecondari
                FROM Esercizi
                WHERE nome LIKE ?';
        $params = [$searchLike];

        if ($equipment !== null && $equipment !== '') {
            $sql .= ' AND categoria = ?';
            $params[] = $equipment;
        }

        if ($muscle !== null && $muscle !== '') {
            $sql .= ' AND muscoloPrincipale = ?';
            $params[] = $muscle;
        }

        $sql .= ' ORDER BY (nome LIKE ?) DESC, CHAR_LENGTH(nome) ASC, nome ASC LIMIT 30';
        $params[] = $prefixLike;

        return Database::exec($sql, $params)->fetchAll();
    }

    public static function addExerciseToDay(int $giornoId, int $esercizioId): int
    {
        $row = Database::exec(
            'SELECT COALESCE(MAX(ordine), 0) AS maxOrdine FROM EserciziGiorno WHERE giorno = ?',
            [$giornoId]
        )->fetch();
        $ordine = ((int)($row['maxOrdine'] ?? 0)) + 1;

        Database::exec(
            'INSERT INTO EserciziGiorno (giorno, esercizio, ordine, istruzioni, urlVideo)
             VALUES (?, ?, ?, NULL, NULL)',
            [$giornoId, $esercizioId, $ordine]
        );

        return (int)Database::pdo()->lastInsertId();
    }

    public static function removeExerciseFromDay(int $esercizioGiornoId): void
    {
        $row = Database::exec(
            'SELECT giorno, ordine FROM EserciziGiorno WHERE idEsercizioGiorno = ? LIMIT 1',
            [$esercizioGiornoId]
        )->fetch();

        if (!$row) {
            return;
        }

        Database::exec('DELETE FROM SeriePrescritte WHERE esercizioGiorno = ?', [$esercizioGiornoId]);
        Database::exec('DELETE FROM EserciziGiorno WHERE idEsercizioGiorno = ?', [$esercizioGiornoId]);

        self::normalizeExerciseOrder((int)$row['giorno']);
    }

    public static function reorderExercises(int $giornoId, array $orderedIds): void
    {
        $position = 1;
        foreach ($orderedIds as $id) {
            Database::exec(
                'UPDATE EserciziGiorno SET ordine = ? WHERE idEsercizioGiorno = ? AND giorno = ?',
                [$position, (int)$id, $giornoId]
            );
            $position++;
        }
    }

    public static function saveSetsForExercise(int $esercizioGiornoId, array $sets): void
    {
        foreach ($sets as $set) {
            $numeroSerie = (int)($set['numeroSerie'] ?? 0);
            if ($numeroSerie < 1) {
                continue;
            }

            $exists = Database::exec(
                'SELECT idSeriePrescritta FROM SeriePrescritte WHERE esercizioGiorno = ? AND numeroSerie = ? LIMIT 1',
                [$esercizioGiornoId, $numeroSerie]
            )->fetch();

            $payload = [
                self::nullableInt($set['targetReps'] ?? null),
                self::nullableInt($set['repsMin'] ?? null),
                self::nullableInt($set['repsMax'] ?? null),
                self::nullableDecimal($set['targetCarico'] ?? null),
                self::nullableDecimal($set['targetRPE'] ?? null),
                self::nullableInt($set['recuperoSecondi'] ?? null),
                self::nullableString($set['tempo'] ?? null),
                self::nullableString($set['note'] ?? null),
                $esercizioGiornoId,
                $numeroSerie,
            ];

            if ($exists) {
                Database::exec(
                    'UPDATE SeriePrescritte
                     SET targetReps = ?, repsMin = ?, repsMax = ?, targetCarico = ?, targetRPE = ?, recuperoSecondi = ?, tempo = ?, note = ?
                     WHERE esercizioGiorno = ? AND numeroSerie = ?',
                    $payload
                );
            } else {
                Database::exec(
                    'INSERT INTO SeriePrescritte
                     (targetReps, repsMin, repsMax, targetCarico, targetRPE, recuperoSecondi, tempo, note, esercizioGiorno, numeroSerie)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    $payload
                );
            }
        }
    }


    public static function updateRoutineNotes(int $giornoId, ?string $note): void
    {
        Database::exec(
            'UPDATE GiorniAllenamento SET note = ? WHERE idGiorno = ?',
            [self::nullableString($note), $giornoId]
        );
    }

    public static function updateExerciseNotesRestVideo(int $esercizioGiornoId, ?string $istruzioni, ?string $urlVideo): void
    {
        Database::exec(
            'UPDATE EserciziGiorno SET istruzioni = ?, urlVideo = ? WHERE idEsercizioGiorno = ?',
            [self::nullableString($istruzioni), self::nullableString($urlVideo), $esercizioGiornoId]
        );
    }

    public static function removeSet(int $esercizioGiornoId, int $numeroSerie): void
    {
        Database::exec(
            'DELETE FROM SeriePrescritte WHERE esercizioGiorno = ? AND numeroSerie = ?',
            [$esercizioGiornoId, $numeroSerie]
        );

        self::normalizeSetOrder($esercizioGiornoId);
    }

    public static function addEmptySet(int $esercizioGiornoId): void
    {
        $row = Database::exec(
            'SELECT COALESCE(MAX(numeroSerie), 0) AS maxNumero FROM SeriePrescritte WHERE esercizioGiorno = ?',
            [$esercizioGiornoId]
        )->fetch();
        $next = ((int)($row['maxNumero'] ?? 0)) + 1;

        Database::exec(
            'INSERT INTO SeriePrescritte (esercizioGiorno, numeroSerie, targetReps, repsMin, repsMax, targetCarico, targetRPE, recuperoSecondi, tempo, note)
             VALUES (?, ?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)',
            [$esercizioGiornoId, $next]
        );
    }

    public static function isDayOwnedByUser(int $giornoId, int $userId): bool
    {
        $row = Database::exec(
            'SELECT g.idGiorno
             FROM GiorniAllenamento g
             INNER JOIN ProgrammiAllenamento p ON p.idProgramma = g.programma
             WHERE g.idGiorno = ? AND p.creatoreUtente = ?
             LIMIT 1',
            [$giornoId, $userId]
        )->fetch();

        return (bool)$row;
    }

    public static function isExerciseDayOwnedByUser(int $esercizioGiornoId, int $userId): bool
    {
        $row = Database::exec(
            'SELECT eg.idEsercizioGiorno
             FROM EserciziGiorno eg
             INNER JOIN GiorniAllenamento g ON g.idGiorno = eg.giorno
             INNER JOIN ProgrammiAllenamento p ON p.idProgramma = g.programma
             WHERE eg.idEsercizioGiorno = ? AND p.creatoreUtente = ?
             LIMIT 1',
            [$esercizioGiornoId, $userId]
        )->fetch();

        return (bool)$row;
    }

    private static function normalizeSetOrder(int $esercizioGiornoId): void
    {
        $stmt = Database::exec(
            'SELECT idSeriePrescritta FROM SeriePrescritte WHERE esercizioGiorno = ? ORDER BY numeroSerie ASC',
            [$esercizioGiornoId]
        );

        $i = 1;
        while ($row = $stmt->fetch()) {
            Database::exec(
                'UPDATE SeriePrescritte SET numeroSerie = ? WHERE idSeriePrescritta = ?',
                [$i, (int)$row['idSeriePrescritta']]
            );
            $i++;
        }
    }

    private static function normalizeExerciseOrder(int $giornoId): void
    {
        $stmt = Database::exec(
            'SELECT idEsercizioGiorno FROM EserciziGiorno WHERE giorno = ? ORDER BY ordine ASC, idEsercizioGiorno ASC',
            [$giornoId]
        );

        $i = 1;
        while ($row = $stmt->fetch()) {
            Database::exec(
                'UPDATE EserciziGiorno SET ordine = ? WHERE idEsercizioGiorno = ?',
                [$i, (int)$row['idEsercizioGiorno']]
            );
            $i++;
        }
    }

    private static function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }

    private static function nullableDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float)$value;
    }

    private static function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string)$value);
        return $normalized === '' ? null : $normalized;
    }
}
