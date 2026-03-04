<?php
require __DIR__ . '/common.php';

$programId = (int)($_GET['id'] ?? 0);
$error = null;
$program = null;
$days = [];

if ($programId <= 0) {
  $error = 'Programma non valido.';
} elseif (!$dbAvailable) {
  $error = $dbError ?? 'Database non disponibile.';
} else {
  try {
    $cliente = Database::exec(
      'SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1',
      [(int)$user['idUtente']]
    )->fetch();

    if (!$cliente) {
      $error = 'Profilo cliente non trovato.';
    } else {
      $program = Database::exec(
        "SELECT p.idProgramma, p.titolo, p.descrizione,
                ap.stato AS statoAssegnazione, ap.assegnatoIl,
                u.nome, u.cognome
         FROM AssegnazioniProgramma ap
         INNER JOIN ProgrammiAllenamento p ON p.idProgramma = ap.programma
         INNER JOIN Associazioni a ON a.cliente = ap.cliente AND a.tipoAssociazione = 'pt' AND a.attivaFlag = 1
         INNER JOIN Professionisti pr ON pr.idProfessionista = a.professionista
         INNER JOIN Utenti u ON u.idUtente = pr.idUtente
         WHERE ap.programma = ?
           AND ap.cliente = ?
           AND p.stato <> 'archiviato'
         ORDER BY ap.assegnatoIl DESC
         LIMIT 1",
        [$programId, (int)$cliente['idCliente']]
      )->fetch();

      if (!$program) {
        $error = 'Scheda non disponibile o non assegnata al tuo profilo.';
      } else {
        $days = Database::exec(
          'SELECT idGiorno, nome, ordine, note
           FROM GiorniAllenamento
           WHERE programma = ?
           ORDER BY ordine ASC, idGiorno ASC',
          [$programId]
        )->fetchAll();

        foreach ($days as &$day) {
          $exercises = Database::exec(
            'SELECT eg.idEsercizioGiorno, eg.ordine, eg.istruzioni, eg.urlVideo, e.nome
             FROM EserciziGiorno eg
             INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
             WHERE eg.giorno = ?
             ORDER BY eg.ordine ASC, eg.idEsercizioGiorno ASC',
            [(int)$day['idGiorno']]
          )->fetchAll();

          foreach ($exercises as &$exercise) {
            $exercise['serie'] = Database::exec(
              'SELECT numeroSerie, targetReps, repsMin, repsMax, targetCarico, targetRPE, recuperoSecondi, tempo, note
               FROM SeriePrescritte
               WHERE esercizioGiorno = ?
               ORDER BY numeroSerie ASC',
              [(int)$exercise['idEsercizioGiorno']]
            )->fetchAll();
          }

          $day['exercises'] = $exercises;
        }
      }
    }
  } catch (Throwable $e) {
    $error = 'Errore durante il caricamento della scheda assegnata.';
  }
}

renderStart('Scheda assegnata', 'allenamenti', $email);
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell">
  <div class="library-toolbar" style="justify-content:space-between">
    <a class="link-btn" href="allenamenti.php">← Torna alle cartelle</a>
    <span class="pill">Scheda assegnata</span>
  </div>

  <?php if ($error): ?>
    <article class="card"><p><?= h($error) ?></p></article>
  <?php else: ?>
    <article class="card">
      <h1 style="margin-top:0"><?= h((string)$program['titolo']) ?></h1>
      <p class="muted"><?= h((string)($program['descrizione'] ?? '')) ?></p>
      <div class="program-meta">
        <span class="status ok">Assegnato</span>
        <span>Stato: <?= h((string)$program['statoAssegnazione']) ?></span>
        <span>Data: <?= h((string)$program['assegnatoIl']) ?></span>
        <span>PT: <?= h(trim((string)$program['nome'] . ' ' . (string)$program['cognome'])) ?></span>
      </div>
    </article>

    <?php foreach ($days as $day): ?>
      <article class="card" style="margin-top:12px">
        <h3 class="section-title" style="margin-top:0"><?= h((string)$day['nome']) ?></h3>
        <?php if (!empty($day['note'])): ?>
          <p class="muted"><?= h((string)$day['note']) ?></p>
        <?php endif; ?>

        <?php if (empty($day['exercises'])): ?>
          <p class="muted">Nessun esercizio in questa giornata.</p>
        <?php else: ?>
          <?php foreach ($day['exercises'] as $exercise): ?>
            <div class="exercise-block">
              <div class="exercise-head">
                <strong><?= h((string)$exercise['nome']) ?></strong>
              </div>
              <?php if (!empty($exercise['istruzioni'])): ?>
                <p class="muted" style="margin:8px 0 0"><?= h((string)$exercise['istruzioni']) ?></p>
              <?php endif; ?>
              <?php if (!empty($exercise['urlVideo'])): ?>
                <p style="margin:6px 0 0"><a class="link-btn" href="<?= h((string)$exercise['urlVideo']) ?>" target="_blank" rel="noopener">Video esercizio</a></p>
              <?php endif; ?>

              <?php if (!empty($exercise['serie'])): ?>
                <div class="set-table-wrap" style="margin-top:10px">
                  <table class="set-table">
                    <thead>
                      <tr>
                        <th>#</th><th>Kg</th><th>Reps</th><th>Min</th><th>Max</th><th>RPE</th><th>Rec</th><th>Tempo</th><th>Note</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($exercise['serie'] as $serie): ?>
                        <tr>
                          <td><?= h((string)$serie['numeroSerie']) ?></td>
                          <td><?= h((string)($serie['targetCarico'] ?? '—')) ?></td>
                          <td><?= h((string)($serie['targetReps'] ?? '—')) ?></td>
                          <td><?= h((string)($serie['repsMin'] ?? '—')) ?></td>
                          <td><?= h((string)($serie['repsMax'] ?? '—')) ?></td>
                          <td><?= h((string)($serie['targetRPE'] ?? '—')) ?></td>
                          <td><?= h((string)($serie['recuperoSecondi'] ?? '—')) ?></td>
                          <td><?= h((string)($serie['tempo'] ?? '—')) ?></td>
                          <td><?= h((string)($serie['note'] ?? '')) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php
renderEnd();
