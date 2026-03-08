<?php
require __DIR__ . '/common.php';
require_once __DIR__ . '/../models/programmi_model.php';
require_once __DIR__ . '/../models/routine_model.php';

if (!$isPt) {
    header('Location: overview.php');
    exit;
}

$idProgramma = (int)($_GET['id'] ?? 0);
if ($idProgramma < 1) {
    header('Location: allenamenti.php');
    exit;
}

$program = ProgrammiModel::getProgramDetails($idProgramma, $userId);
if (!$program) {
    header('Location: allenamenti.php');
    exit;
}

$professionistaId = ProgrammiModel::getProfessionistaIdByUserId($userId);

$programFolderId = (int)($program['cartellaId'] ?? 0);
$selectedFolderId = (int)($_GET['cartella'] ?? 0);
if ($selectedFolderId < 1 && $programFolderId > 0) {
    $selectedFolderId = $programFolderId;
}
$selectedGiornoId = (int)($_GET['giorno'] ?? 0);
$selectedRoutine = null;

if ($selectedGiornoId > 0 && RoutineModel::isDayOwnedByUser($selectedGiornoId, $userId)) {
    $candidateRoutine = RoutineModel::getRoutineEditorData($selectedGiornoId, $userId);
    if ($candidateRoutine && (int)$candidateRoutine['programma'] === (int)$program['idProgramma']) {
        $selectedRoutine = $candidateRoutine;
    }
}

if (!$selectedRoutine && !empty($program['giorni'])) {
    $firstDayId = (int)$program['giorni'][0]['idGiorno'];
    if ($firstDayId > 0) {
        $selectedRoutine = RoutineModel::getRoutineEditorData($firstDayId, $userId);
    }
}

$switchableDays = array_values(array_filter(
    $program['giorni'],
    static function (array $giorno) use ($selectedRoutine): bool {
        if (!$selectedRoutine) {
            return true;
        }

        return (int)$giorno['idGiorno'] !== (int)$selectedRoutine['idGiorno'];
    }
));

renderStart('Programma', 'allenamenti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell">
  <div class="program-toolbar">
    <a href="allenamenti.php<?= $selectedFolderId > 0 ? '?cartella=' . $selectedFolderId : '' ?>" class="link-btn">← Libreria</a>
    <h2 class="section-title" style="margin:0"><?= h((string)$program['titolo']) ?></h2>
    <button class="btn primary" type="button" data-open-assign-modal>Assegna scheda</button>
    <button class="btn" data-duplicate-program="<?= (int)$program['idProgramma'] ?>">Duplica</button>
    <button class="btn danger" data-delete-program="<?= (int)$program['idProgramma'] ?>" data-folder-id="<?= (int)$selectedFolderId ?>">Elimina</button>
  </div>

  <?php if ($selectedRoutine): ?>
    <?php $routineDescription = trim((string)($selectedRoutine['note'] ?? ''));
    if ($routineDescription === '') {
        $routineDescription = trim((string)($program['descrizione'] ?? ''));
    }
    ?>
    <textarea
      class="dark-textarea"
      data-routine-note
      placeholder="Descrizione del giorno di allenamento..."
      style="margin-bottom:20px"
    ><?= h($routineDescription) ?></textarea>
  <?php else: ?>
    <p class="muted"><?= h((string)($program['descrizione'] ?? '')) ?></p>
  <?php endif; ?>

  <?php if (!empty($switchableDays)): ?>
    <div class="day-list">
      <?php foreach ($switchableDays as $giorno): ?>
        <article class="day-card">
          <h3><?= h((string)$giorno['nome']) ?></h3>
          <p class="muted-sm"><?= h((string)($giorno['previewEsercizi'] ?? 'Nessun esercizio')) ?></p>
          <a class="btn" href="programma.php?id=<?= (int)$program['idProgramma'] ?>&cartella=<?= $selectedFolderId ?>&giorno=<?= (int)$giorno['idGiorno'] ?>">Apri workout builder</a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($selectedRoutine): ?>
    <div class="divider"></div>

    <section class="card workout-shell" data-routine-editor data-giorno="<?= (int)$selectedRoutine['idGiorno'] ?>" style="padding:0;border:none;background:transparent;box-shadow:none">
      <div class="program-toolbar">
        <div class="exercise-header-row">
          <h3 class="section-title" style="margin:0">Workout Builder · <?= h((string)$selectedRoutine['nome']) ?></h3>
          <button
            type="button"
            class="action-mini"
            data-toggle-all-exercises
            aria-label="Minimizza o espandi tutti gli esercizi"
            title="Minimizza o espandi tutti gli esercizi"
          >👁</button>
        </div>
      </div>

      <div class="routine-layout">
        <div class="routine-exercises">
          <div data-exercise-list></div>
        </div>

        <aside class="picker">
          <h3 style="margin-top:0">Exercise Picker</h3>
          <input class="dark-input" data-exercise-search placeholder="Search exercise" />
          <div class="exercise-search-results" data-search-results></div>
        </aside>
      </div>
    </section>
  <?php endif; ?>

  <div class="divider"></div>

  <h3>Assegnazioni correnti</h3>

  <ul>
    <?php foreach ($program['assegnazioni'] as $assegnazione): ?>
      <li><?= h(trim((string)$assegnazione['nome'] . ' ' . (string)$assegnazione['cognome'])) ?> — <?= h((string)$assegnazione['stato']) ?></li>
    <?php endforeach; ?>
  </ul>
</section>

<div class="modal-layer" data-delete-program-modal>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="delete-program-modal-title">
    <h3 id="delete-program-modal-title">Conferma eliminazione</h3>
    <p class="muted">Questa azione eliminerà definitivamente il programma. Vuoi continuare?</p>
    <div class="library-toolbar" style="justify-content:flex-end">
      <button class="btn" type="button" data-cancel-delete-program>Annulla</button>
      <button class="btn danger" type="button" data-confirm-delete-program>Elimina programma</button>
    </div>
  </div>
</div>

<div class="assign-modal-layer" data-assign-program-modal>
  <div class="assign-modal-card" role="dialog" aria-modal="true" aria-labelledby="assign-program-modal-title">
    <h3 id="assign-program-modal-title" style="margin:0">Assegna scheda</h3>
    <p class="muted" style="margin:8px 0 16px">Seleziona i clienti attivi associati al tuo profilo PT.</p>

    <label class="assign-toggle-all">
      <input type="checkbox" data-assign-toggle-all />
      <span>Seleziona tutti / Deseleziona tutti</span>
    </label>

    <div class="assign-client-list" data-assign-client-list></div>
    <p class="assign-feedback" data-assign-feedback></p>

    <div class="library-toolbar" style="justify-content:flex-end">
      <button class="btn" type="button" data-close-assign-modal>Chiudi</button>
      <button class="btn primary" type="button" data-submit-assign>Assegna</button>
    </div>
  </div>
</div>

<style>
  .assign-modal-layer {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 18, 0.84);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1200;
    padding: 16px;
  }
  .assign-modal-layer.open { display: flex; }
  .assign-modal-card {
    width: min(680px, 100%);
    max-height: min(82vh, 720px);
    overflow: hidden;
    border-radius: 18px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: linear-gradient(180deg, rgba(18, 24, 41, 0.98), rgba(9, 13, 24, 0.98));
    box-shadow: 0 22px 56px rgba(0, 0, 0, 0.55);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .assign-toggle-all {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(235, 243, 255, 0.92);
    font-size: 14px;
  }
  .assign-client-list {
    max-height: 330px;
    overflow-y: auto;
    padding: 8px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.02);
  }
  .assign-client-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 6px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  }
  .assign-client-item:last-child { border-bottom: none; }
  .assign-feedback {
    min-height: 20px;
    margin: 0;
    font-size: 14px;
    color: #fda4af;
  }
  .assign-feedback.ok { color: #86efac; }
  .exercise-header-row {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .exercise-head-actions {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  [data-routine-editor] .routine-layout {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  [data-routine-editor] .routine-exercises,
  [data-routine-editor] [data-exercise-list],
  [data-routine-editor] .exercise-card,
  [data-routine-editor] .exercise-block,
  [data-routine-editor] .picker {
    width: 100%;
    max-width: none;
  }
  [data-routine-editor] .picker {
    position: static;
    align-self: stretch;
  }
  .exercise-card.is-collapsed {
    padding: 10px 12px;
    min-height: 80px;
    overflow: hidden;
    cursor: pointer;
  }
  .exercise-card.is-collapsed .exercise-card-body {
    display: none;
  }
  .exercise-card.is-collapsed .exercise-head {
    margin: 0;
    align-items: center;
  }
  .exercise-card.is-collapsed .exercise-head h4 {
    margin: 0;
  }
</style>
<?php
renderEnd('<script src="../assets/js/program_library.js"></script><script src="../assets/js/routine_editor.js"></script>
<script>
(function () {
  const modal = document.querySelector("[data-assign-program-modal]");
  const openBtn = document.querySelector("[data-open-assign-modal]");
  const closeBtn = document.querySelector("[data-close-assign-modal]");
  const submitBtn = document.querySelector("[data-submit-assign]");
  const listEl = document.querySelector("[data-assign-client-list]");
  const feedbackEl = document.querySelector("[data-assign-feedback]");
  const toggleAll = document.querySelector("[data-assign-toggle-all]");
  const idProgramma = ' . (int)$program['idProgramma'] . ';

  function setFeedback(message, isSuccess) {
    if (!feedbackEl) return;
    feedbackEl.textContent = message || "";
    feedbackEl.classList.toggle("ok", !!isSuccess);
  }

  function toggleModal(open) {
    if (!modal) return;
    modal.classList.toggle("open", !!open);
  }

  function renderClienti(clienti) {
    if (!listEl) return;
    if (!Array.isArray(clienti) || clienti.length === 0) {
      listEl.innerHTML = "<p class=\"muted\" style=\"margin:6px\">Nessun cliente attivo associato.</p>";
      return;
    }

    listEl.innerHTML = clienti.map((cliente) => {
      const checked = cliente.giaAssegnato ? "checked" : "";
      const fullName = `${cliente.nome || ""} ${cliente.cognome || ""}`.trim();
      return `<label class=\"assign-client-item\"><input type=\"checkbox\" data-assign-cliente value=\"${Number(cliente.idCliente)}\" ${checked} /><span>${fullName}</span></label>`;
    }).join("");
  }

  async function loadClienti() {
    setFeedback("", false);
    if (toggleAll) {
      toggleAll.checked = false;
    }

    if (listEl) {
      listEl.innerHTML = "<p class=\"muted\" style=\"margin:6px\">Caricamento clienti...</p>";
    }

    const res = await fetch(`api/get_clienti_assegnazione_programma.php?idProgramma=${encodeURIComponent(idProgramma)}`);
    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error(data.error || "Impossibile caricare i clienti.");
    }

    renderClienti(data.clienti || []);
  }

  openBtn?.addEventListener("click", async () => {
    toggleModal(true);
    try {
      await loadClienti();
    } catch (error) {
      setFeedback(error.message || "Errore caricamento clienti.", false);
    }
  });

  closeBtn?.addEventListener("click", () => {
    toggleModal(false);
  });

  modal?.addEventListener("click", (event) => {
    if (event.target === modal) {
      toggleModal(false);
    }
  });

  toggleAll?.addEventListener("change", () => {
    const checked = toggleAll.checked;
    document.querySelectorAll("[data-assign-cliente]").forEach((input) => {
      input.checked = checked;
    });
  });

  submitBtn?.addEventListener("click", async () => {
    const selected = Array.from(document.querySelectorAll("[data-assign-cliente]:checked"))
      .map((input) => Number(input.value))
      .filter((value) => Number.isInteger(value) && value > 0);

    if (selected.length === 0) {
      setFeedback("Seleziona almeno un cliente.", false);
      return;
    }

    submitBtn.disabled = true;
    setFeedback("", false);

    try {
      const res = await fetch("api/assegna_programma_clienti.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ idProgramma, clienti: selected })
      });

      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.error || data.message || "Errore durante assegnazione.");
      }

      setFeedback(data.message || "Scheda assegnata con successo.", true);
    } catch (error) {
      setFeedback(error.message || "Errore durante assegnazione.", false);
    } finally {
      submitBtn.disabled = false;
    }
  });
})();

(function () {
  const toggleAllBtn = document.querySelector("[data-toggle-all-exercises]");
  const exerciseList = document.querySelector("[data-exercise-list]");
  if (!toggleAllBtn || !exerciseList) return;

  const cards = () => Array.from(document.querySelectorAll(".exercise-card, .exercise-block"));

  function isCollapsed(card) {
    return card.classList.contains("is-collapsed");
  }

  function collapseCard(card) {
    card.classList.add("is-collapsed");
    card.setAttribute("data-state", "collapsed");
  }

  function expandCard(card) {
    card.classList.remove("is-collapsed");
    card.setAttribute("data-state", "expanded");
  }

  function toggleCard(card) {
    if (isCollapsed(card)) {
      expandCard(card);
      return;
    }
    collapseCard(card);
  }

  function enhanceCard(card) {
    if (!card || card.dataset.collapsibleReady === "1") return;

    card.classList.add("exercise-card");
    card.setAttribute("data-state", "collapsed");

    const head = card.querySelector(".exercise-head");
    if (!head) return;

    const actionsWrap = head.lastElementChild;
    if (actionsWrap) {
      actionsWrap.classList.add("exercise-head-actions");
      if (!actionsWrap.querySelector("[data-toggle-exercise]")) {
        const toggleBtn = document.createElement("button");
        toggleBtn.type = "button";
        toggleBtn.className = "action-mini mini-action-btn";
        toggleBtn.setAttribute("data-toggle-exercise", "");
        toggleBtn.setAttribute("aria-label", "Minimizza o espandi esercizio");
        toggleBtn.setAttribute("title", "Minimizza o espandi esercizio");
        toggleBtn.textContent = "👁";
        actionsWrap.insertBefore(toggleBtn, actionsWrap.firstElementChild);
      }
    }

    if (!card.querySelector(".exercise-card-body")) {
      const body = document.createElement("div");
      body.className = "exercise-card-body";
      const children = Array.from(card.children).filter((child) => child !== head);
      children.forEach((child) => body.appendChild(child));
      card.appendChild(body);
    }

    card.dataset.collapsibleReady = "1";
    collapseCard(card);
  }

  function enhanceAllCards() {
    cards().forEach(enhanceCard);
  }

  toggleAllBtn.addEventListener("click", (event) => {
    event.preventDefault();
    const allCards = cards();
    if (!allCards.length) return;

    const allCollapsed = allCards.every((card) => isCollapsed(card));
    allCards.forEach((card) => {
      if (allCollapsed) {
        expandCard(card);
      } else {
        collapseCard(card);
      }
    });
  });

  document.addEventListener("click", (event) => {
    const singleToggleBtn = event.target.closest("[data-toggle-exercise]");
    if (singleToggleBtn) {
      event.preventDefault();
      event.stopPropagation();
      const card = singleToggleBtn.closest(".exercise-card");
      if (card) {
        toggleCard(card);
      }
      return;
    }

    const card = event.target.closest(".exercise-card");
    if (!card || !isCollapsed(card)) return;
    if (event.target.closest("button, input, textarea, select, a, label")) return;
    expandCard(card);
  });

  const observer = new MutationObserver(() => {
    enhanceAllCards();
  });

  observer.observe(exerciseList, { childList: true });
  enhanceAllCards();
})();
</script>');
