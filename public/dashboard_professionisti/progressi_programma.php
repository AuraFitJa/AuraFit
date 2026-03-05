<?php
require __DIR__ . '/common.php';

$idCliente = (int)($_GET['idCliente'] ?? 0);
$idProgramma = (int)($_GET['idProgramma'] ?? 0);

$error = null;
$clienteNome = 'Cliente';
$titoloProgramma = 'Programma';
$eserciziProgramma = [];
$progressi = [];
$sessioniTotali = 0;

if ($idCliente < 1 || $idProgramma < 1) {
  $error = 'Parametri non validi.';
} elseif (!$dbAvailable) {
  $error = $dbError ?? 'Database non disponibile.';
} else {
  try {
    $professionistaId = getProfessionistaId($userId);
    if (!$professionistaId) {
      $error = 'Profilo professionista non trovato per questo account.';
    } else {
      $tipiConsentiti = [];
      if ($isPt) {
        $tipiConsentiti[] = 'pt';
      }
      if ($isNutrizionista) {
        $tipiConsentiti[] = 'nutrizionista';
      }

      if (!$tipiConsentiti) {
        $error = 'Ruolo professionista non autorizzato.';
      } else {
        $placeholdersTipi = implode(',', array_fill(0, count($tipiConsentiti), '?'));
        $associazioneAttiva = Database::exec(
          "SELECT 1
           FROM Associazioni
           WHERE professionista = ?
             AND cliente = ?
             AND attivaFlag = 1
             AND tipoAssociazione IN ($placeholdersTipi)
           LIMIT 1",
          array_merge([$professionistaId, $idCliente], $tipiConsentiti)
        )->fetch();

        if (!$associazioneAttiva) {
          http_response_code(403);
          $error = 'Cliente non associato al tuo profilo.';
        } else {
          $programmaCliente = Database::exec(
            "SELECT p.idProgramma, p.titolo, u.nome, u.cognome
             FROM AssegnazioniProgramma ap
             INNER JOIN ProgrammiAllenamento p ON p.idProgramma = ap.programma
             INNER JOIN Clienti c ON c.idCliente = ap.cliente
             INNER JOIN Utenti u ON u.idUtente = c.idUtente
             WHERE ap.cliente = ?
               AND ap.programma = ?
               AND ap.stato IN ('attivo', 'attiva')
             ORDER BY ap.assegnatoIl DESC
             LIMIT 1",
            [$idCliente, $idProgramma]
          )->fetch();

          if (!$programmaCliente) {
            $error = 'Programma non disponibile.';
          } else {
            $titoloProgramma = (string)$programmaCliente['titolo'];
            $clienteNome = trim((string)$programmaCliente['nome'] . ' ' . (string)$programmaCliente['cognome']);

            $eserciziProgramma = Database::exec(
              'SELECT
                 eg.idEsercizioGiorno,
                 g.idGiorno,
                 g.nome AS nomeGiorno,
                 g.ordine AS ordineGiorno,
                 eg.ordine AS ordineEsercizio,
                 e.idEsercizio,
                 e.nome AS nomeEsercizio
               FROM GiorniAllenamento g
               INNER JOIN EserciziGiorno eg ON eg.giorno = g.idGiorno
               INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
               WHERE g.programma = ?
               ORDER BY g.ordine ASC, eg.ordine ASC, eg.idEsercizioGiorno ASC',
              [$idProgramma]
            )->fetchAll();

            $idEsercizi = [];
            foreach ($eserciziProgramma as $esercizio) {
              $idEsercizi[(int)$esercizio['idEsercizio']] = true;
            }

            if ($idEsercizi) {
              $exerciseIds = array_keys($idEsercizi);
              $placeholdersExercises = implode(',', array_fill(0, count($exerciseIds), '?'));

              $rows = Database::exec(
                "SELECT
                   sa.idSessione,
                   sa.svoltaIl,
                   COALESCE(ss.esercizio, egp.esercizio) AS idEsercizio,
                   ss.idSerieSvolta,
                   ss.numeroSerie,
                   ss.repsEffettive,
                   ss.caricoEffettivo,
                   ss.rpeEffettivo,
                   ss.completata,
                   ss.note
                 FROM SessioniAllenamento sa
                 INNER JOIN SerieSvolte ss ON ss.sessione = sa.idSessione
                 LEFT JOIN SeriePrescritte sp ON sp.idSeriePrescritta = ss.seriePrescritta
                 LEFT JOIN EserciziGiorno egp ON egp.idEsercizioGiorno = sp.esercizioGiorno
                 WHERE sa.cliente = ?
                   AND sa.programma = ?
                   AND COALESCE(ss.esercizio, egp.esercizio) IN ($placeholdersExercises)
                 ORDER BY
                   COALESCE(ss.esercizio, egp.esercizio) ASC,
                   sa.svoltaIl DESC,
                   ss.numeroSerie ASC,
                   ss.idSerieSvolta ASC",
                array_merge([$idCliente, $idProgramma], $exerciseIds)
              )->fetchAll();

              foreach ($rows as $row) {
                $exerciseId = (int)$row['idEsercizio'];
                $sessionId = (int)$row['idSessione'];

                if (!isset($progressi[$exerciseId])) {
                  $progressi[$exerciseId] = [];
                }
                if (!isset($progressi[$exerciseId][$sessionId])) {
                  $progressi[$exerciseId][$sessionId] = [
                    'svoltaIl' => (string)$row['svoltaIl'],
                    'serie' => [],
                  ];
                }

                $progressi[$exerciseId][$sessionId]['serie'][] = [
                  'idSerieSvolta' => (int)$row['idSerieSvolta'],
                  'numeroSerie' => $row['numeroSerie'] !== null ? (int)$row['numeroSerie'] : null,
                  'repsEffettive' => $row['repsEffettive'],
                  'caricoEffettivo' => $row['caricoEffettivo'],
                  'rpeEffettivo' => $row['rpeEffettivo'],
                  'completata' => $row['completata'],
                  'note' => (string)($row['note'] ?? ''),
                ];
              }

              $sessioniIds = [];
              foreach ($progressi as $sessioniEsercizio) {
                foreach ($sessioniEsercizio as $idSessione => $_) {
                  $sessioniIds[$idSessione] = true;
                }
              }
              $sessioniTotali = count($sessioniIds);
            }
          }
        }
      }
    }
  } catch (Throwable $e) {
    $error = 'Errore durante il caricamento dei progressi programma.';
  }
}

renderStart('Progressi programma', 'clienti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <style>
    .set-table-wrap { overflow-x: auto; }
    .set-table { width: 100%; min-width: 700px; }
    .exercise-progress { margin-top: 16px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,.08); }
    .session-block { margin-top: 10px; padding: 12px; border-radius: 12px; background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); }
  </style>

  <div class="toolbar" style="justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
    <a class="btn" href="scheda_cliente.php?idCliente=<?= (int)$idCliente ?>">← Torna alla scheda cliente</a>
    <h2 class="section-title" style="margin:0">Progressi - <?= h($titoloProgramma) ?> - <?= h($clienteNome) ?></h2>
  </div>

  <?php if ($error): ?>
    <div class="alert" style="margin-top:12px"><?= h($error) ?></div>
  <?php else: ?>
    <?php if (!$eserciziProgramma): ?>
      <p class="muted" style="margin-top:12px">Nessun esercizio presente nel programma.</p>
    <?php else: ?>
      <?php if ($sessioniTotali === 0): ?>
        <p class="muted" style="margin-top:12px">Nessuna sessione registrata per questo programma.</p>
      <?php endif; ?>

      <?php foreach ($eserciziProgramma as $esercizio): ?>
        <?php
          $idEsercizio = (int)$esercizio['idEsercizio'];
          $sessioniEsercizio = $progressi[$idEsercizio] ?? [];
        ?>
        <article class="exercise-progress">
          <h3 style="margin:0"><?= h((string)$esercizio['nomeEsercizio']) ?></h3>
          <p class="muted" style="margin:4px 0 0">
            <?= h((string)$esercizio['nomeGiorno']) ?> · Ordine giorno <?= h((string)$esercizio['ordineGiorno']) ?> · Ordine esercizio <?= h((string)$esercizio['ordineEsercizio']) ?>
          </p>

          <?php if (!$sessioniEsercizio): ?>
            <p class="muted" style="margin-top:8px">Nessun dato registrato.</p>
          <?php else: ?>
            <?php foreach ($sessioniEsercizio as $idSessione => $sessione): ?>
              <div class="session-block">
                <p style="margin:0 0 8px"><strong>Sessione #<?= (int)$idSessione ?></strong> · <?= h((string)$sessione['svoltaIl']) ?></p>
                <div class="set-table-wrap">
                  <table class="set-table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Reps</th>
                        <th>Kg</th>
                        <th>RPE</th>
                        <th>Completata</th>
                        <th>Note</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($sessione['serie'] as $index => $serie): ?>
                        <tr>
                          <td><?= $serie['numeroSerie'] !== null ? (int)$serie['numeroSerie'] : ($index + 1) ?></td>
                          <td><?= $serie['repsEffettive'] !== null ? h((string)$serie['repsEffettive']) : '—' ?></td>
                          <td><?= $serie['caricoEffettivo'] !== null ? h((string)$serie['caricoEffettivo']) : '—' ?></td>
                          <td><?= $serie['rpeEffettivo'] !== null ? h((string)$serie['rpeEffettivo']) : '—' ?></td>
                          <td><?= (int)$serie['completata'] === 1 ? 'Sì' : 'No' ?></td>
                          <td><?= h((string)$serie['note']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php renderEnd();
