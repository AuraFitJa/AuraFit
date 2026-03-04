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
<style>
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
            <tr>
              <th>#</th><th>Kg</th><th>Reps</th><th>Min</th><th>Max</th><th>RPE</th><th>Rec</th><th>Tempo</th><th>Note</th>
            </tr>
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
            <th>Serie</th><th>Reps</th><th>Carico</th><th>RPE</th><th>Completata</th><th>Note</th>
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
            <th>#</th><th>Reps</th><th>Carico</th><th>RPE</th><th>Completata</th><th>Note</th><th></th>
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
    const feedback = document.getElementById('exerciseModalFeedback');

    const state = {
      open: false,
      current: null,
      data: null,
      extraToDelete: [],
    };

    const apiBase = 'api';

    function setFeedback(message, isError) {
      feedback.textContent = message || '';
      feedback.style.color = isError ? '#ff8585' : '#9fb3c8';
    }

    function openModal() {
      overlay.classList.add('open');
      overlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      state.open = true;
    }

    function closeModal() {
      overlay.classList.remove('open');
      overlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
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
        <td><input type="number" min="0" step="1" data-field="repsEffettive" value="${valOrDash(values.repsEffettive).replace('—', '')}"></td>
        <td><input type="number" min="0" step="0.5" data-field="caricoEffettivo" value="${valOrDash(values.caricoEffettivo).replace('—', '')}"></td>
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
        <td><input type="number" min="0" step="1" data-field="repsEffettive" value="${item.repsEffettive ?? ''}"></td>
        <td><input type="number" min="0" step="0.5" data-field="caricoEffettivo" value="${item.caricoEffettivo ?? ''}"></td>
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
      (payload.seriePrescritte || []).forEach((serie) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${escapeHtml(valOrDash(serie.numeroSerie))}</td>
          <td>${escapeHtml(valOrDash(serie.targetCarico))}</td>
          <td>${escapeHtml(valOrDash(serie.targetReps))}</td>
          <td>${escapeHtml(valOrDash(serie.repsMin))}</td>
          <td>${escapeHtml(valOrDash(serie.repsMax))}</td>
          <td>${escapeHtml(valOrDash(serie.targetRPE))}</td>
          <td>${escapeHtml(valOrDash(serie.recuperoSecondi))}</td>
          <td>${escapeHtml(valOrDash(serie.tempo))}</td>
          <td>${escapeHtml(valOrDash(serie.note))}</td>
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
</script>
<?php
renderEnd();
