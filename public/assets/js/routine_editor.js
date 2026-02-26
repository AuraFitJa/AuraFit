(function () {
  const root = document.querySelector('[data-routine-editor]');
  if (!root) return;

  const giorno = root.getAttribute('data-giorno');
  const searchInput = document.querySelector('[data-exercise-search]');
  const searchResults = document.querySelector('[data-search-results]');

  async function api(action, method, payload) {
    let url = `../controllers/routine_controller.php?action=${encodeURIComponent(action)}`;
    const options = { method, headers: { 'X-Requested-With': 'XMLHttpRequest' } };

    if (method === 'GET') {
      const query = new URLSearchParams(payload || {}).toString();
      url += '&' + query;
    } else {
      const form = new FormData();
      Object.entries(payload || {}).forEach(([k, v]) => {
        if (Array.isArray(v)) {
          v.forEach((item) => form.append(k + '[]', item));
          return;
        }
        form.set(k, v);
      });
      options.body = form;
    }

    const res = await fetch(url, options);
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.message || 'Errore API routine');
    return data;
  }

  async function refresh() {
    const data = await api('getRoutineEditorData', 'GET', { giorno });
    renderExercises(data.routine.esercizi || []);
  }

  function renderExercises(exercises) {
    const list = document.querySelector('[data-exercise-list]');
    if (!list) return;

    list.innerHTML = '';
    exercises.forEach((ex, index) => {
      const block = document.createElement('article');
      block.className = 'exercise-block';
      block.innerHTML = `
        <div class="exercise-head">
          <h4>${index + 1}. ${ex.esercizioNome}</h4>
          <div>
            <button class="action-mini" data-move-up="${ex.idEsercizioGiorno}">↑</button>
            <button class="action-mini" data-move-down="${ex.idEsercizioGiorno}">↓</button>
            <button class="action-mini danger" data-remove-ex="${ex.idEsercizioGiorno}">Rimuovi</button>
          </div>
        </div>
        <textarea class="dark-textarea" data-note="${ex.idEsercizioGiorno}" placeholder="Istruzioni">${ex.istruzioni || ''}</textarea>
        <input class="dark-input" data-video="${ex.idEsercizioGiorno}" placeholder="URL video" value="${ex.urlVideo || ''}" />
        <table class="set-table" data-table="${ex.idEsercizioGiorno}">
          <thead><tr><th>Set</th><th>KG</th><th>Reps</th><th>Rep Min</th><th>Rep Max</th><th>RPE</th><th>Rest sec</th><th>Note</th><th></th></tr></thead>
          <tbody></tbody>
        </table>
        <div class="library-toolbar">
          <button class="action-mini" data-add-set="${ex.idEsercizioGiorno}">Add set</button>
          <button class="action-mini" data-save-set="${ex.idEsercizioGiorno}">Save set table</button>
          <button class="action-mini" data-save-meta="${ex.idEsercizioGiorno}">Save note/video</button>
        </div>`;
      list.appendChild(block);

      const tbody = block.querySelector('tbody');
      (ex.serie || []).forEach((set) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${set.numeroSerie}</td>
          <td><input data-field="targetCarico" value="${set.targetCarico ?? ''}"></td>
          <td><input data-field="targetReps" value="${set.targetReps ?? ''}"></td>
          <td><input data-field="repsMin" value="${set.repsMin ?? ''}"></td>
          <td><input data-field="repsMax" value="${set.repsMax ?? ''}"></td>
          <td><input data-field="targetRPE" value="${set.targetRPE ?? ''}"></td>
          <td><input data-field="recuperoSecondi" value="${set.recuperoSecondi ?? ''}"></td>
          <td><input data-field="note" value="${set.note ?? ''}"></td>
          <td><button class="action-mini danger" data-remove-set="${ex.idEsercizioGiorno}" data-set-number="${set.numeroSerie}">x</button></td>`;
        tr.setAttribute('data-set-number', set.numeroSerie);
        tbody.appendChild(tr);
      });
    });
  }

  searchInput?.addEventListener('input', async () => {
    const query = searchInput.value.trim();
    const data = await api('searchExercises', 'GET', { query });
    searchResults.innerHTML = '';
    (data.items || []).forEach((item) => {
      const row = document.createElement('div');
      row.className = 'search-item';
      row.innerHTML = `<div><strong>${item.nome}</strong><div class="muted-sm">${item.muscoloPrincipale || ''}</div></div><button class="action-mini">+</button>`;
      row.querySelector('button').addEventListener('click', async () => {
        await api('addExerciseToDay', 'POST', { giorno, esercizio: item.idEsercizio });
        await refresh();
      });
      searchResults.appendChild(row);
    });
  });

  document.addEventListener('click', async (e) => {
    const removeEx = e.target.closest('[data-remove-ex]');
    if (removeEx) {
      await api('removeExerciseFromDay', 'POST', { idEsercizioGiorno: removeEx.getAttribute('data-remove-ex') });
      await refresh();
      return;
    }

    const addSet = e.target.closest('[data-add-set]');
    if (addSet) {
      await api('addSet', 'POST', { idEsercizioGiorno: addSet.getAttribute('data-add-set') });
      await refresh();
      return;
    }

    const removeSet = e.target.closest('[data-remove-set]');
    if (removeSet) {
      await api('removeSet', 'POST', {
        idEsercizioGiorno: removeSet.getAttribute('data-remove-set'),
        numeroSerie: removeSet.getAttribute('data-set-number')
      });
      await refresh();
      return;
    }

    const saveSet = e.target.closest('[data-save-set]');
    if (saveSet) {
      const exId = saveSet.getAttribute('data-save-set');
      const table = document.querySelector(`[data-table="${exId}"] tbody`);
      const sets = [...table.querySelectorAll('tr')].map((tr) => {
        const obj = { numeroSerie: tr.getAttribute('data-set-number') };
        tr.querySelectorAll('input').forEach((input) => {
          obj[input.getAttribute('data-field')] = input.value;
        });
        return obj;
      });
      await api('saveSetsForExercise', 'POST', { idEsercizioGiorno: exId, sets: JSON.stringify(sets) });
      return;
    }

    const saveMeta = e.target.closest('[data-save-meta]');
    if (saveMeta) {
      const exId = saveMeta.getAttribute('data-save-meta');
      await api('updateExerciseNotesRestVideo', 'POST', {
        idEsercizioGiorno: exId,
        istruzioni: document.querySelector(`[data-note="${exId}"]`).value,
        urlVideo: document.querySelector(`[data-video="${exId}"]`).value
      });
      return;
    }


    const saveRoutineNote = e.target.closest('[data-save-routine-note]');
    if (saveRoutineNote) {
      await api('updateRoutineNotes', 'POST', {
        giorno,
        note: document.querySelector('[data-routine-note]')?.value || ''
      });
      return;
    }

    const moveUp = e.target.closest('[data-move-up]');
    const moveDown = e.target.closest('[data-move-down]');
    if (moveUp || moveDown) {
      const exId = Number((moveUp || moveDown).getAttribute(moveUp ? 'data-move-up' : 'data-move-down'));
      const ids = [...document.querySelectorAll('[data-move-up]')].map((btn) => Number(btn.getAttribute('data-move-up')));
      const idx = ids.indexOf(exId);
      if (idx < 0) return;
      const swap = moveUp ? idx - 1 : idx + 1;
      if (swap < 0 || swap >= ids.length) return;
      [ids[idx], ids[swap]] = [ids[swap], ids[idx]];
      await api('reorderExercises', 'POST', { giorno, orderedIds: ids });
      await refresh();
    }
  });

  refresh();
})();
