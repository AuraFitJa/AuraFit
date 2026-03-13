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
           AND ap.stato IN ('attivo', 'attiva')
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
            'SELECT eg.idEsercizioGiorno, eg.ordine, eg.istruzioni, eg.urlVideo, e.idEsercizio, e.nome
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
          unset($exercise);

          $day['exercises'] = $exercises;
        }
        unset($day);
      }
    }
  } catch (Throwable $e) {
    $error = 'Errore durante il caricamento della scheda assegnata.';
  }
}

renderStart('Scheda assegnata', 'allenamenti', $email);
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<style>
  html,
  body {
    max-width: 100%;
    overflow-x: hidden;
  }
  .card,
  .workout-shell {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }
  .program-meta,
  .exercise-block,
  .toolbar {
    min-width: 0;
  }
  .set-table-wrap {
    width: 100%;
    overflow-x: auto;
  }
  .set-table {
    width: 100%;
    min-width: 600px;
  }
  .set-table .notes-col {
    min-width: 280px;
    width: 40%;
  }
  .exercise-block {
    cursor: pointer;
    transition: transform .18s ease, box-shadow .18s ease;
  }
  .exercise-block:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 18px rgba(0, 0, 0, .22);
  }
  .exercise-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .75);
    z-index: 999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    opacity: 0;
    transition: opacity .2s ease;
  }
  .exercise-modal-overlay.open {
    display: flex;
    opacity: 1;
  }
  .exercise-modal {
    width: min(980px, 100%);
    max-height: 92vh;
    overflow-y: auto;
    border-radius: 16px;
    background: #1a2026;
    border: 1px solid rgba(255, 255, 255, .08);
    padding: 18px;
    color: #eef3f7;
  }
  .exercise-modal-header, .exercise-modal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
  }
  .exercise-modal-close {
    border: 0;
    background: #303a45;
    color: #eef3f7;
    border-radius: 8px;
    padding: 8px 11px;
    cursor: pointer;
  }
  .exercise-modal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px;
    margin-top: 12px;
  }
  .exercise-modal-label {
    font-size: .84rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #96a5b5;
  }
  .exercise-modal-value {
    margin-top: 2px;
    font-weight: 600;
  }
  .exercise-form-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .exercise-form-table th,
  .exercise-form-table td {
    border-bottom: 1px solid rgba(255, 255, 255, .08);
    padding: 8px;
    text-align: left;
  }
  .exercise-form-table input[type='number'],
  .exercise-form-table input[type='text'] {
    width: 100%;
    border: 1px solid rgba(255, 255, 255, .14);
    border-radius: 6px;
    background: #0f151b;
    color: #eef3f7;
    padding: 6px 8px;
  }
  .exercise-modal-toolbar {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
  }
  .exercise-inline-feedback { margin: 0; }
  .sessioni-list {
    display: grid;
    gap: 10px;
  }
  .sessione-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 10px;
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 10px;
    background: rgba(255, 255, 255, .02);
  }
  .sessione-meta {
    display: grid;
    gap: 2px;
  }
  .sessione-title-link {
    font-weight: 700;
    color: #eef3f7;
    cursor: pointer;
    text-decoration: underline;
    text-decoration-color: rgba(238, 243, 247, .35);
  }
  .sessione-title-link:hover {
    text-decoration-color: rgba(238, 243, 247, .9);
  }
  .sessione-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .75);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
  }
  .sessione-modal-overlay.open {
    display: flex;
  }
  .sessione-modal {
    width: min(980px, 100%);
    max-height: 80vh;
    overflow-y: auto;
    border-radius: 16px;
    background: #1a2026;
    border: 1px solid rgba(255, 255, 255, .08);
    padding: 18px;
    color: #eef3f7;
  }
  .sessione-modal-head,
  .sessione-modal-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
  }
  .sessione-modal-close {
    border: 0;
    background: #303a45;
    color: #eef3f7;
    border-radius: 8px;
    padding: 8px 11px;
    cursor: pointer;
  }
  .sessione-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
    margin-top: 10px;
  }

  .confirm-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .75);
    z-index: 1100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
  }
  .confirm-modal-overlay.open {
    display: flex;
  }
  .confirm-modal {
    width: min(480px, 100%);
    border-radius: 14px;
    background: #1a2026;
    border: 1px solid rgba(255, 255, 255, .1);
    padding: 18px;
    color: #eef3f7;
  }
  .confirm-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 14px;
  }

</style>
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

    <article class="card" style="margin-top:12px">
      <h3 class="section-title" style="margin-top:0">Storico sessioni</h3>
      <div id="sessioniList" class="sessioni-list"></div>
      <p class="muted" id="sessioniError" style="display:none">Errore caricamento sessioni.</p>
      <p class="muted" id="sessioniEmpty" style="display:none">Nessuna sessione registrata.</p>
      <button class="btn" id="loadMoreSessioni" type="button" style="margin-top:10px">Carica altre</button>
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
            <div
              class="exercise-block"
              data-program-id="<?= (int)$programId ?>"
              data-giorno-id="<?= (int)$day['idGiorno'] ?>"
              data-esercizio-giorno-id="<?= (int)$exercise['idEsercizioGiorno'] ?>"
              data-esercizio-id="<?= (int)$exercise['idEsercizio'] ?>"
              tabindex="0"
              role="button"
            >
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
                <?php
                  $hasTargetReps = false;
                  $hasRepsMin = false;
                  $hasRepsMax = false;
                  foreach ($exercise['serie'] as $serieRow) {
                    if ($serieRow['targetReps'] !== null && $serieRow['targetReps'] !== '') {
                      $hasTargetReps = true;
                    }
                    if ($serieRow['repsMin'] !== null && $serieRow['repsMin'] !== '') {
                      $hasRepsMin = true;
                    }
                    if ($serieRow['repsMax'] !== null && $serieRow['repsMax'] !== '') {
                      $hasRepsMax = true;
                    }
                  }
                ?>
                <div class="set-table-wrap" style="margin-top:10px">
                  <table class="set-table">
                    <thead>
                      <tr>
                        <th>#</th><th>Kg</th>
                        <?php if ($hasTargetReps): ?><th>Reps</th><?php endif; ?>
                        <?php if ($hasRepsMin): ?><th>Rep Min</th><?php endif; ?>
                        <?php if ($hasRepsMax): ?><th>Rep Max</th><?php endif; ?>
                        <th>RPE</th><th>Rec</th><th class="notes-col">Note</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($exercise['serie'] as $serie): ?>
                        <tr>
                          <td><?= h((string)$serie['numeroSerie']) ?></td>
                          <td><?= h((string)($serie['targetCarico'] ?? '—')) ?></td>
                          <?php if ($hasTargetReps): ?><td><?= h((string)($serie['targetReps'] ?? '')) ?></td><?php endif; ?>
                          <?php if ($hasRepsMin): ?><td><?= h((string)($serie['repsMin'] ?? '')) ?></td><?php endif; ?>
                          <?php if ($hasRepsMax): ?><td><?= h((string)($serie['repsMax'] ?? '')) ?></td><?php endif; ?>
                          <td><?= h((string)($serie['targetRPE'] ?? '—')) ?></td>
                          <td><?= h((string)($serie['recuperoSecondi'] ?? '—')) ?></td>
                          <td class="notes-col"><?= h((string)($serie['note'] ?? '')) ?></td>
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

<div id="exerciseModalOverlay" class="exercise-modal-overlay" aria-hidden="true">
  <div id="exerciseModal" class="exercise-modal" role="dialog" aria-modal="true" aria-labelledby="exerciseModalTitle">
    <div class="exercise-modal-header">
      <h2 id="exerciseModalTitle" style="margin:0">Dettaglio esercizio</h2>
      <button type="button" class="exercise-modal-close" id="exerciseModalClose">✕</button>
    </div>

    <p id="exerciseModalFeedback" class="muted exercise-inline-feedback"></p>

    <section>
      <h3 class="section-title">Dettagli</h3>
      <div id="exerciseModalDetails" class="exercise-modal-grid"></div>
    </section>

    <section style="margin-top:16px">
      <h3 class="section-title">Serie prescritte</h3>
      <div class="set-table-wrap">
        <table class="set-table" id="exerciseModalPrescribedTable">
          <thead>
            <tr></tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </section>

    <section style="margin-top:16px">
      <h3 class="section-title">Serie svolte</h3>
      <table class="exercise-form-table" id="performedPrescribedTable">
        <thead>
          <tr>
            <th>Serie</th><th>Carico</th><th>Reps</th><th>RPE</th><th>Completata</th><th>Note</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>

      <div class="exercise-modal-toolbar">
        <h4 style="margin:10px 0 0">Serie extra</h4>
        <button type="button" class="btn" id="addExtraSetBtn">+ Aggiungi serie extra</button>
      </div>

      <table class="exercise-form-table" id="performedExtraTable">
        <thead>
          <tr>
            <th>#</th><th>Carico</th><th>Reps</th><th>RPE</th><th>Completata</th><th>Note</th><th></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </section>

    <div class="exercise-modal-footer" style="margin-top:16px">
      <button type="button" class="btn" id="exerciseModalCancel">Chiudi</button>
      <button type="button" class="btn primary" id="exerciseModalSave">Salva</button>
    </div>
  </div>
</div>

<div id="sessioneModalOverlay" class="sessione-modal-overlay" aria-hidden="true">
  <div id="sessioneModal" class="sessione-modal" role="dialog" aria-modal="true" aria-labelledby="sessioneModalTitle">
    <div class="sessione-modal-head">
      <h2 id="sessioneModalTitle" style="margin:0">Storico esercizio</h2>
      <button type="button" class="sessione-modal-close" id="sessioneModalClose">✕</button>
    </div>

    <p class="muted" id="sessioneModalFeedback" style="margin-top:8px"></p>
    <h3 class="section-title" id="sessioneModalExerciseTitle" style="margin:0 0 8px 0"></h3>
    <div class="set-table-wrap">
      <table class="set-table" id="sessioneStoricoTable">
        <thead>
          <tr>
            <th>Data</th><th>Reps</th><th>Kg</th><th></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <div class="sessione-modal-foot" style="margin-top:16px">
      <span></span>
      <button type="button" class="btn" id="sessioneModalCancel">Chiudi</button>
    </div>
  </div>
</div>

<div id="addSessioneOverlay" class="sessione-modal-overlay" aria-hidden="true">
  <div class="sessione-modal" role="dialog" aria-modal="true" aria-labelledby="addSessioneTitle">
    <div class="sessione-modal-head">
      <h2 id="addSessioneTitle" style="margin:0">Aggiungi sessione</h2>
      <button type="button" class="sessione-modal-close" id="addSessioneClose">✕</button>
    </div>

    <p id="addSessioneFeedback" class="muted" style="margin-top:8px"></p>

    <div class="field" style="margin-top:10px">
      <label for="addSessioneEsercizio">Esercizio</label>
      <select id="addSessioneEsercizio"></select>
    </div>

    <div class="field" style="margin-top:10px">
      <label for="addSessioneData">Data e ora</label>
      <input type="datetime-local" id="addSessioneData" />
    </div>

    <section class="card" style="margin-top:12px; padding:12px">
      <h3 class="section-title" style="margin-top:0">Serie</h3>
      <table class="exercise-form-table" id="addSessioneSerieTable">
        <thead>
          <tr><th>#</th><th>Reps</th><th>Kg</th><th>RPE</th><th>Note</th><th></th></tr>
        </thead>
        <tbody></tbody>
      </table>
      <button type="button" class="btn" id="addSessioneAddSerie" style="margin-top:10px">+ Aggiungi serie</button>
    </section>

    <div class="sessione-modal-foot" style="margin-top:16px">
      <button type="button" class="btn" id="addSessioneCancel">Chiudi</button>
      <button type="button" class="btn primary" id="addSessioneSave">Salva</button>
    </div>
  </div>
</div>


<div id="confirmModalOverlay" class="confirm-modal-overlay" aria-hidden="true">
  <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <h3 id="confirmModalTitle" class="section-title" style="margin:0">Conferma rimozione</h3>
    <p id="confirmModalMessage" class="muted" style="margin:10px 0 0">Vuoi davvero rimuovere questa serie?</p>
    <div class="confirm-modal-actions">
      <button type="button" class="btn" id="confirmModalCancel">Annulla</button>
      <button type="button" class="btn primary" id="confirmModalOk">Rimuovi</button>
    </div>
  </div>
</div>

<script>
  (function () {
    const overlay = document.getElementById('exerciseModalOverlay');
    if (!overlay) return;

    const modalCloseBtn = document.getElementById('exerciseModalClose');
    const modalCancelBtn = document.getElementById('exerciseModalCancel');
    const modalSaveBtn = document.getElementById('exerciseModalSave');
    const addExtraBtn = document.getElementById('addExtraSetBtn');
    const detailsWrap = document.getElementById('exerciseModalDetails');
    const prescribedTableBody = document.querySelector('#exerciseModalPrescribedTable tbody');
    const performedPrescribedBody = document.querySelector('#performedPrescribedTable tbody');
    const performedExtraBody = document.querySelector('#performedExtraTable tbody');
    const prescribedTableHeadRow = document.querySelector('#exerciseModalPrescribedTable thead tr');
    const feedback = document.getElementById('exerciseModalFeedback');

    const state = {
      open: false,
      current: null,
      data: null,
      extraToDelete: [],
    };

    const scrollLockState = {
      locked: false,
      bodyOverflow: '',
      htmlOverflow: '',
    };

    const apiBase = 'api';

    function setFeedback(message, isError) {
      feedback.textContent = message || '';
      feedback.style.color = isError ? '#ff8585' : '#9fb3c8';
    }

    function openModal() {
      overlay.classList.add('open');
      overlay.setAttribute('aria-hidden', 'false');
      lockBackgroundScroll();
      state.open = true;
    }

    function closeModal() {
      overlay.classList.remove('open');
      overlay.setAttribute('aria-hidden', 'true');
      unlockBackgroundScroll();
      state.open = false;
      state.current = null;
      state.data = null;
      state.extraToDelete = [];
      setFeedback('');
      detailsWrap.innerHTML = '';
      prescribedTableBody.innerHTML = '';
      performedPrescribedBody.innerHTML = '';
      performedExtraBody.innerHTML = '';
    }

    function lockBackgroundScroll() {
      if (scrollLockState.locked) return;
      scrollLockState.bodyOverflow = document.body.style.overflow;
      scrollLockState.htmlOverflow = document.documentElement.style.overflow;
      document.body.style.overflow = 'hidden';
      document.documentElement.style.overflow = 'hidden';
      scrollLockState.locked = true;
    }

    function unlockBackgroundScroll() {
      if (!scrollLockState.locked) return;
      document.body.style.overflow = scrollLockState.bodyOverflow;
      document.documentElement.style.overflow = scrollLockState.htmlOverflow;
      scrollLockState.locked = false;
    }

    function valOrDash(value) {
      return value === null || value === undefined || value === '' ? '—' : String(value);
    }

    function escapeHtml(value) {
      return String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
      }[char]));
    }

    function toNumberOrNull(value) {
      if (value === '' || value === null || value === undefined) return null;
      const n = Number(value);
      return Number.isFinite(n) ? n : null;
    }

    function renderDetails(payload) {
      const ex = payload.esercizio || {};
      const assignment = payload.assegnazione || {};
      const videoHtml = assignment.urlVideo
        ? `<a class="link-btn" href="${escapeHtml(assignment.urlVideo)}" target="_blank" rel="noopener">Apri video</a>`
        : '—';

      detailsWrap.innerHTML = `
        <div><div class="exercise-modal-label">Nome</div><div class="exercise-modal-value">${escapeHtml(ex.nome || '')}</div></div>
        <div><div class="exercise-modal-label">Categoria</div><div class="exercise-modal-value">${escapeHtml(valOrDash(ex.categoria))}</div></div>
        <div><div class="exercise-modal-label">Muscolo principale</div><div class="exercise-modal-value">${escapeHtml(valOrDash(ex.muscoloPrincipale))}</div></div>
        <div><div class="exercise-modal-label">Unità predefinita</div><div class="exercise-modal-value">${escapeHtml(valOrDash(ex.unitaPredefinita))}</div></div>
        <div style="grid-column:1/-1"><div class="exercise-modal-label">Istruzioni</div><div class="exercise-modal-value">${escapeHtml(valOrDash(assignment.istruzioni))}</div></div>
        <div style="grid-column:1/-1"><div class="exercise-modal-label">Video</div><div class="exercise-modal-value">${videoHtml}</div></div>
      `;
    }

    function buildPerformedRow(label, serieId, currentValues) {
      const values = currentValues || {};
      const row = document.createElement('tr');
      row.dataset.seriePrescrittaId = String(serieId);
      row.innerHTML = `
        <td>${escapeHtml(label)}</td>
        <td><input type="number" min="0" step="0.5" data-field="caricoEffettivo" value="${valOrDash(values.caricoEffettivo).replace('—', '')}"></td>
        <td><input type="number" min="0" step="1" data-field="repsEffettive" value="${valOrDash(values.repsEffettive).replace('—', '')}"></td>
        <td><input type="number" min="0" step="0.5" data-field="rpeEffettivo" value="${valOrDash(values.rpeEffettivo).replace('—', '')}"></td>
        <td><input type="checkbox" data-field="completata" ${values.completata === undefined || Number(values.completata) === 1 ? 'checked' : ''}></td>
        <td><input type="text" data-field="note" value="${(values.note || '').replace(/"/g, '&quot;')}"></td>
      `;
      return row;
    }

    function buildExtraRow(item) {
      const row = document.createElement('tr');
      if (item.idSerieSvolta) {
        row.dataset.idSerieSvolta = String(item.idSerieSvolta);
      }
      row.innerHTML = `
        <td><input type="number" min="1" step="1" data-field="numeroSerie" value="${item.numeroSerie || ''}"></td>
        <td><input type="number" min="0" step="0.5" data-field="caricoEffettivo" value="${item.caricoEffettivo ?? ''}"></td>
        <td><input type="number" min="0" step="1" data-field="repsEffettive" value="${item.repsEffettive ?? ''}"></td>
        <td><input type="number" min="0" step="0.5" data-field="rpeEffettivo" value="${item.rpeEffettivo ?? ''}"></td>
        <td><input type="checkbox" data-field="completata" ${item.completata === undefined || Number(item.completata) === 1 ? 'checked' : ''}></td>
        <td><input type="text" data-field="note" value="${(item.note || '').replace(/"/g, '&quot;')}"></td>
        <td><button type="button" class="btn" data-action="remove-extra">Rimuovi</button></td>
      `;
      return row;
    }

    function renderModal(payload) {
      state.data = payload;
      state.extraToDelete = [];
      renderDetails(payload);

      prescribedTableBody.innerHTML = '';
      performedPrescribedBody.innerHTML = '';
      performedExtraBody.innerHTML = '';

      const prescritteById = (payload.svolte && payload.svolte.prescritteById) || {};
      const seriePrescritte = payload.seriePrescritte || [];
      const hasTargetReps = seriePrescritte.some((serie) => serie.targetReps !== null && serie.targetReps !== undefined && String(serie.targetReps) !== '');
      const hasRepsMin = seriePrescritte.some((serie) => serie.repsMin !== null && serie.repsMin !== undefined && String(serie.repsMin) !== '');
      const hasRepsMax = seriePrescritte.some((serie) => serie.repsMax !== null && serie.repsMax !== undefined && String(serie.repsMax) !== '');

      prescribedTableHeadRow.innerHTML = `
        <th>#</th>
        <th>Kg</th>
        ${hasTargetReps ? '<th>Reps</th>' : ''}
        ${hasRepsMin ? '<th>Rep Min</th>' : ''}
        ${hasRepsMax ? '<th>Rep Max</th>' : ''}
        <th>RPE</th>
        <th>Rec</th>
        <th class="notes-col">Note</th>
      `;

      seriePrescritte.forEach((serie) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${escapeHtml(valOrDash(serie.numeroSerie))}</td>
          <td>${escapeHtml(valOrDash(serie.targetCarico))}</td>
          ${hasTargetReps ? `<td>${escapeHtml(serie.targetReps ?? '')}</td>` : ''}
          ${hasRepsMin ? `<td>${escapeHtml(serie.repsMin ?? '')}</td>` : ''}
          ${hasRepsMax ? `<td>${escapeHtml(serie.repsMax ?? '')}</td>` : ''}
          <td>${escapeHtml(valOrDash(serie.targetRPE))}</td>
          <td>${escapeHtml(valOrDash(serie.recuperoSecondi))}</td>
          <td class="notes-col">${escapeHtml(valOrDash(serie.note))}</td>
        `;
        prescribedTableBody.appendChild(tr);

        performedPrescribedBody.appendChild(
          buildPerformedRow(
            `Serie ${valOrDash(serie.numeroSerie)}`,
            serie.idSeriePrescritta,
            prescritteById[String(serie.idSeriePrescritta)] || {}
          )
        );
      });

      ((payload.svolte && payload.svolte.extra) || []).forEach((item) => {
        performedExtraBody.appendChild(buildExtraRow(item));
      });
    }

    async function fetchModalData(dataset) {
      const params = new URLSearchParams({
        programId: dataset.programId,
        giornoId: dataset.giornoId,
        esercizioGiornoId: dataset.esercizioGiornoId,
      });
      const response = await fetch(`${apiBase}/get_esercizio_modal.php?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error(payload.error || 'Errore caricamento dettagli esercizio.');
      }
      return payload;
    }

    async function openForBlock(block) {
      state.current = {
        programId: Number(block.dataset.programId),
        giornoId: Number(block.dataset.giornoId),
        esercizioGiornoId: Number(block.dataset.esercizioGiornoId),
        esercizioId: Number(block.dataset.esercizioId),
      };
      setFeedback('Caricamento...');
      openModal();
      try {
        const payload = await fetchModalData(block.dataset);
        renderModal(payload);
        setFeedback('');
      } catch (error) {
        setFeedback(error.message || 'Errore.', true);
      }
    }

    function collectRows(selector) {
      return Array.from(selector.querySelectorAll('tr'));
    }

    async function saveCurrent() {
      if (!state.current || !state.data) return;
      const prescritte = collectRows(performedPrescribedBody).map((row) => ({
        seriePrescrittaId: Number(row.dataset.seriePrescrittaId),
        repsEffettive: toNumberOrNull(row.querySelector('[data-field="repsEffettive"]').value),
        caricoEffettivo: toNumberOrNull(row.querySelector('[data-field="caricoEffettivo"]').value),
        rpeEffettivo: toNumberOrNull(row.querySelector('[data-field="rpeEffettivo"]').value),
        completata: row.querySelector('[data-field="completata"]').checked,
        note: row.querySelector('[data-field="note"]').value.trim() || null,
      }));

      const extra = collectRows(performedExtraBody).map((row) => ({
        idSerieSvolta: row.dataset.idSerieSvolta ? Number(row.dataset.idSerieSvolta) : null,
        numeroSerie: toNumberOrNull(row.querySelector('[data-field="numeroSerie"]').value),
        repsEffettive: toNumberOrNull(row.querySelector('[data-field="repsEffettive"]').value),
        caricoEffettivo: toNumberOrNull(row.querySelector('[data-field="caricoEffettivo"]').value),
        rpeEffettivo: toNumberOrNull(row.querySelector('[data-field="rpeEffettivo"]').value),
        completata: row.querySelector('[data-field="completata"]').checked,
        note: row.querySelector('[data-field="note"]').value.trim() || null,
      }));

      const requestPayload = {
        programId: state.current.programId,
        giornoId: state.current.giornoId,
        esercizioGiornoId: state.current.esercizioGiornoId,
        esercizioId: state.current.esercizioId,
        prescritte,
        extra,
        extraToDelete: state.extraToDelete,
      };

      setFeedback('Salvataggio...');
      const response = await fetch(`${apiBase}/save_serie_svolte.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(requestPayload),
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error(payload.error || 'Errore durante il salvataggio.');
      }

      setFeedback('Salvato con successo.');
      const refreshed = await fetchModalData({
        programId: String(state.current.programId),
        giornoId: String(state.current.giornoId),
        esercizioGiornoId: String(state.current.esercizioGiornoId),
      });
      renderModal(refreshed);
    }

    document.querySelectorAll('.exercise-block').forEach((block) => {
      block.addEventListener('click', () => openForBlock(block));
      block.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openForBlock(block);
        }
      });
    });

    addExtraBtn.addEventListener('click', () => {
      performedExtraBody.appendChild(buildExtraRow({ completata: 1 }));
    });

    performedExtraBody.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action="remove-extra"]');
      if (!button) return;
      const row = button.closest('tr');
      if (!row) return;
      if (row.dataset.idSerieSvolta) {
        state.extraToDelete.push(Number(row.dataset.idSerieSvolta));
      }
      row.remove();
    });

    modalSaveBtn.addEventListener('click', async () => {
      try {
        await saveCurrent();
      } catch (error) {
        setFeedback(error.message || 'Errore.', true);
      }
    });

    modalCloseBtn.addEventListener('click', closeModal);
    modalCancelBtn.addEventListener('click', closeModal);
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        closeModal();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && state.open) {
        closeModal();
      }
    });
  })();

  (function () {
    const PROGRAM_ID = <?= (int)$programId ?>;
    const listWrap = document.getElementById('sessioniList');
    const loadMoreBtn = document.getElementById('loadMoreSessioni');
    const emptyNode = document.getElementById('sessioniEmpty');
    const errorNode = document.getElementById('sessioniError');

    const overlay = document.getElementById('sessioneModalOverlay');
    const modalClose = document.getElementById('sessioneModalClose');
    const modalCancel = document.getElementById('sessioneModalCancel');
    const modalFeedback = document.getElementById('sessioneModalFeedback');
    const modalExerciseTitle = document.getElementById('sessioneModalExerciseTitle');
    const storicoTableBody = document.querySelector('#sessioneStoricoTable tbody');

    const addOverlay = document.getElementById('addSessioneOverlay');
    const addClose = document.getElementById('addSessioneClose');
    const addCancel = document.getElementById('addSessioneCancel');
    const addFeedback = document.getElementById('addSessioneFeedback');
    const addSelect = document.getElementById('addSessioneEsercizio');
    const addDate = document.getElementById('addSessioneData');
    const addSerieBody = document.querySelector('#addSessioneSerieTable tbody');
    const addSerieBtn = document.getElementById('addSessioneAddSerie');
    const addSaveBtn = document.getElementById('addSessioneSave');
    const confirmOverlay = document.getElementById('confirmModalOverlay');
    const confirmMessage = document.getElementById('confirmModalMessage');
    const confirmCancel = document.getElementById('confirmModalCancel');
    const confirmOk = document.getElementById('confirmModalOk');

    if (!listWrap || !loadMoreBtn || !overlay || !addOverlay || !confirmOverlay || !PROGRAM_ID) return;

    const EXERCISES = <?= json_encode(array_values(array_reduce($days, function ($carry, $day) {
      foreach (($day['exercises'] ?? []) as $exercise) {
        $id = (int)($exercise['idEsercizio'] ?? 0);
        if ($id > 0 && !isset($carry[$id])) {
          $carry[$id] = ['idEsercizio' => $id, 'nome' => (string)($exercise['nome'] ?? '')];
        }
      }
      return $carry;
    }, [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function valOrDash(value) {
      return value === null || value === undefined || value === '' ? '—' : String(value);
    }

    function setSessionError(message) {
      errorNode.style.display = message ? '' : 'none';
      errorNode.textContent = message || '';
    }

    function setAddFeedback(message, isError) {
      addFeedback.textContent = message || '';
      addFeedback.style.color = isError ? '#ff8585' : '#9fb3c8';
    }

    function toNumberOrNull(value) {
      if (value === '' || value === null || value === undefined) return null;
      const n = Number(value);
      return Number.isFinite(n) ? n : null;
    }

    function openDetailModal() {
      overlay.classList.add('open');
      overlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeDetailModal() {
      overlay.classList.remove('open');
      overlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      modalFeedback.textContent = '';
      modalExerciseTitle.textContent = '';
      storicoTableBody.innerHTML = '';
    }

    function openAddModal() {
      addOverlay.classList.add('open');
      addOverlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeAddModal() {
      addOverlay.classList.remove('open');
      addOverlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      setAddFeedback('');
      addSerieBody.innerHTML = '';
      appendSerieRow();
      const now = new Date();
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
      addDate.value = now.toISOString().slice(0, 16);
    }

    function openConfirmModal(message) {
      confirmMessage.textContent = message;
      confirmOverlay.classList.add('open');
      confirmOverlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeConfirmModal() {
      confirmOverlay.classList.remove('open');
      confirmOverlay.setAttribute('aria-hidden', 'true');
      if (!overlay.classList.contains('open') && !addOverlay.classList.contains('open')) {
        document.body.style.overflow = '';
      }
    }

    function confirmAction(message) {
      return new Promise((resolve) => {
        openConfirmModal(message);
        const handleCancel = () => {
          cleanup();
          resolve(false);
        };
        const handleConfirm = () => {
          cleanup();
          resolve(true);
        };
        const handleOverlayClick = (event) => {
          if (event.target === confirmOverlay) {
            handleCancel();
          }
        };
        const cleanup = () => {
          confirmCancel.removeEventListener('click', handleCancel);
          confirmOk.removeEventListener('click', handleConfirm);
          confirmOverlay.removeEventListener('click', handleOverlayClick);
          closeConfirmModal();
        };

        confirmCancel.addEventListener('click', handleCancel);
        confirmOk.addEventListener('click', handleConfirm);
        confirmOverlay.addEventListener('click', handleOverlayClick);
      });
    }

    async function removeSerieStorico(idSerieSvolta) {
      const confirmed = await confirmAction('Vuoi davvero rimuovere questa serie dallo storico?');
      if (!confirmed) return;

      modalFeedback.textContent = 'Rimozione serie...';
      try {
        const res = await fetch('api/delete_sessione_serie.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            programId: PROGRAM_ID,
            idSerieSvolta,
          }),
        });
        const payload = await res.json();
        if (!res.ok || !payload.ok) {
          throw new Error(payload.error || 'Errore rimozione serie');
        }

        modalFeedback.textContent = 'Serie rimossa con successo.';
        await apriDettaglioEsercizio(Number(modalExerciseTitle.dataset.esercizioId || 0));
        await loadSessioni();
      } catch (error) {
        modalFeedback.textContent = error.message || 'Errore rimozione serie';
      }
    }

    function appendSerieRow() {
      const tr = document.createElement('tr');
      const index = addSerieBody.children.length + 1;
      tr.innerHTML = `
        <td>${index}</td>
        <td><input type="number" min="0" step="1" data-field="repsEffettive"></td>
        <td><input type="number" min="0" step="0.5" data-field="caricoEffettivo"></td>
        <td><input type="number" min="0" step="0.5" data-field="rpeEffettivo"></td>
        <td><input type="text" data-field="note"></td>
        <td><button type="button" class="btn" data-action="remove-serie">Rimuovi</button></td>
      `;
      addSerieBody.appendChild(tr);
    }

    function resetExerciseSelect() {
      addSelect.innerHTML = '';
      EXERCISES.forEach((exercise) => {
        const opt = document.createElement('option');
        opt.value = String(exercise.idEsercizio);
        opt.textContent = exercise.nome;
        addSelect.appendChild(opt);
      });
    }

    function renderSessioneRow(item) {
      const row = document.createElement('div');
      row.className = 'sessione-item';

      const meta = document.createElement('div');
      meta.className = 'sessione-meta';

      const title = document.createElement('span');
      title.className = 'sessione-title-link';
      title.textContent = item.nomeEsercizio || 'Esercizio';
      title.dataset.esercizioId = String(item.idEsercizio || 0);

      const preview = document.createElement('span');
      preview.className = 'muted';
      preview.textContent = `Ultima serie: Reps ${valOrDash(item.lastReps)} • Kg ${valOrDash(item.lastKg)}`;

      meta.appendChild(title);
      meta.appendChild(preview);
      row.appendChild(meta);
      listWrap.appendChild(row);
    }

    async function loadSessioni() {
      setSessionError('');
      try {
        const params = new URLSearchParams({
          programId: String(PROGRAM_ID),
          cursor: '0',
          limit: '30',
        });
        const res = await fetch(`api/get_sessioni.php?${params.toString()}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await res.json();
        if (!res.ok || !payload.ok) {
          throw new Error(payload.error || 'Errore caricamento sessioni');
        }

        listWrap.innerHTML = '';
        (payload.items || []).forEach(renderSessioneRow);
        emptyNode.style.display = listWrap.children.length ? 'none' : '';
      } catch (error) {
        setSessionError(error.message || 'Errore caricamento sessioni');
      }
    }

    async function apriDettaglioEsercizio(esercizioId) {
      modalFeedback.textContent = 'Caricamento...';
      openDetailModal();
      try {
        const params = new URLSearchParams({
          programId: String(PROGRAM_ID),
          esercizioId: String(esercizioId),
        });
        const res = await fetch(`api/get_sessione_dettaglio.php?${params.toString()}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await res.json();
        if (!res.ok || !payload.ok) {
          throw new Error(payload.error || 'Errore caricamento dettaglio');
        }

        modalFeedback.textContent = '';
        modalExerciseTitle.textContent = payload.esercizio?.nome || 'Esercizio';
        modalExerciseTitle.dataset.esercizioId = String(payload.esercizio?.idEsercizio || 0);
        storicoTableBody.innerHTML = '';
        (payload.serie || []).forEach((serie) => {
          const tr = document.createElement('tr');
          const tdData = document.createElement('td');
          const tdReps = document.createElement('td');
          const tdKg = document.createElement('td');
          const tdActions = document.createElement('td');
          tdData.textContent = valOrDash(serie.svoltaIl);
          tdReps.textContent = valOrDash(serie.repsEffettive);
          tdKg.textContent = valOrDash(serie.caricoEffettivo);
          if (serie.idSerieSvolta) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn';
            removeBtn.textContent = 'Rimuovi';
            removeBtn.dataset.action = 'remove-serie-storico';
            removeBtn.dataset.idSerieSvolta = String(serie.idSerieSvolta);
            tdActions.appendChild(removeBtn);
          }
          tr.appendChild(tdData);
          tr.appendChild(tdReps);
          tr.appendChild(tdKg);
          tr.appendChild(tdActions);
          storicoTableBody.appendChild(tr);
        });
      } catch (error) {
        modalFeedback.textContent = error.message || 'Errore caricamento dettaglio';
      }
    }

    async function saveStoricoSessione() {
      const esercizioId = Number(addSelect.value || 0);
      if (!esercizioId) {
        setAddFeedback('Seleziona un esercizio.', true);
        return;
      }
      if (!addDate.value) {
        setAddFeedback('Inserisci data e ora sessione.', true);
        return;
      }

      const serie = Array.from(addSerieBody.querySelectorAll('tr')).map((row) => ({
        repsEffettive: toNumberOrNull(row.querySelector('[data-field="repsEffettive"]').value),
        caricoEffettivo: toNumberOrNull(row.querySelector('[data-field="caricoEffettivo"]').value),
        rpeEffettivo: toNumberOrNull(row.querySelector('[data-field="rpeEffettivo"]').value),
        note: row.querySelector('[data-field="note"]').value.trim() || null,
      })).filter((item) => item.repsEffettive !== null || item.caricoEffettivo !== null || item.rpeEffettivo !== null || item.note);

      if (!serie.length) {
        setAddFeedback('Inserisci almeno una serie con valori.', true);
        return;
      }

      setAddFeedback('Salvataggio...');
      const res = await fetch('api/save_sessione_storico.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          programId: PROGRAM_ID,
          esercizioId,
          svoltaIl: addDate.value,
          serie,
        }),
      });
      const payload = await res.json();
      if (!res.ok || !payload.ok) {
        throw new Error(payload.error || 'Errore salvataggio sessione');
      }

      setAddFeedback('Sessione salvata con successo.');
      await loadSessioni();
      closeAddModal();
    }

    loadMoreBtn.addEventListener('click', () => {
      openAddModal();
    });

    listWrap.addEventListener('click', (event) => {
      const link = event.target.closest('.sessione-title-link');
      if (!link) return;
      apriDettaglioEsercizio(Number(link.dataset.esercizioId || 0));
    });

    storicoTableBody.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action="remove-serie-storico"]');
      if (!button) return;
      removeSerieStorico(Number(button.dataset.idSerieSvolta || 0));
    });

    modalClose.addEventListener('click', closeDetailModal);
    modalCancel.addEventListener('click', closeDetailModal);
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) closeDetailModal();
    });

    addClose.addEventListener('click', closeAddModal);
    addCancel.addEventListener('click', closeAddModal);
    addOverlay.addEventListener('click', (event) => {
      if (event.target === addOverlay) closeAddModal();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && overlay.classList.contains('open')) {
        closeDetailModal();
      }
      if (event.key === 'Escape' && addOverlay.classList.contains('open')) {
        closeAddModal();
      }
      if (event.key === 'Escape' && confirmOverlay.classList.contains('open')) {
        closeConfirmModal();
      }
    });

    addSerieBtn.addEventListener('click', appendSerieRow);
    addSerieBody.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action="remove-serie"]');
      if (!button) return;
      const row = button.closest('tr');
      if (!row) return;
      row.remove();
      Array.from(addSerieBody.querySelectorAll('tr')).forEach((tr, index) => {
        const numCell = tr.querySelector('td');
        if (numCell) numCell.textContent = String(index + 1);
      });
    });

    addSaveBtn.addEventListener('click', async () => {
      try {
        await saveStoricoSessione();
      } catch (error) {
        setAddFeedback(error.message || 'Errore salvataggio sessione', true);
      }
    });

    resetExerciseSelect();
    closeAddModal();
    loadSessioni();
  })();
</script>
<?php
renderEnd();
