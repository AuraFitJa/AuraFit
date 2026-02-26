(function () {
  async function postForm(action, payload) {
    const form = new FormData();
    form.set('action', action);
    Object.entries(payload || {}).forEach(([k, v]) => form.set(k, v));

    const res = await fetch('../controllers/programmi_controller.php', { method: 'POST', body: form });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error(data.message || 'Errore richiesta');
    }
    return data;
  }

  function toggleModal(modal, open) {
    if (!modal) return;
    modal.classList.toggle('open', open);
  }

  const folderModal = document.querySelector('[data-folder-modal]');
  const programModal = document.querySelector('[data-program-modal]');

  document.querySelector('[data-open-folder-modal]')?.addEventListener('click', () => {
    toggleModal(folderModal, true);
  });

  document.querySelector('[data-close-folder-modal]')?.addEventListener('click', () => {
    toggleModal(folderModal, false);
  });

  document.querySelector('[data-open-program-modal]')?.addEventListener('click', (event) => {
    const folderId = event.currentTarget.getAttribute('data-folder-id') || '0';
    const hidden = programModal?.querySelector('[name="cartellaId"]');
    if (hidden) {
      hidden.value = folderId;
    }
    toggleModal(programModal, true);
  });

  document.querySelector('[data-close-program-modal]')?.addEventListener('click', () => {
    toggleModal(programModal, false);
  });

  folderModal?.addEventListener('click', (event) => {
    if (event.target === folderModal) {
      toggleModal(folderModal, false);
    }
  });

  programModal?.addEventListener('click', (event) => {
    if (event.target === programModal) {
      toggleModal(programModal, false);
    }
  });

  const folderForm = document.querySelector('[data-folder-form]');
  if (folderForm) {
    folderForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const nome = folderForm.querySelector('[name="nome"]').value.trim();
      if (!nome) return;
      await postForm('createFolder', { nome });
      window.location.reload();
    });
  }

  const programForm = document.querySelector('[data-program-form]');
  if (programForm) {
    programForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const titolo = programForm.querySelector('[name="titolo"]').value.trim();
      const descrizione = programForm.querySelector('[name="descrizione"]').value.trim();
      const cartellaId = programForm.querySelector('[name="cartellaId"]').value;
      if (!titolo || !cartellaId) return;

      await postForm('createProgramTemplate', { titolo, descrizione, cartellaId });
      window.location.reload();
    });
  }

  document.querySelectorAll('[data-open-program]').forEach((card) => {
    card.addEventListener('click', () => {
      const id = card.getAttribute('data-open-program');
      const folderId = card.getAttribute('data-folder-id');
      const params = new URLSearchParams({ id });
      if (folderId) {
        params.set('cartella', folderId);
      }
      window.location.href = `programma.php?${params.toString()}`;
    });
  });

  const addDayForm = document.querySelector('[data-add-day-form]');
  if (addDayForm) {
    addDayForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const idProgramma = addDayForm.querySelector('[name="idProgramma"]').value;
      const nome = addDayForm.querySelector('[name="nome"]').value.trim() || 'Nuovo allenamento';
      const data = await postForm('addGiornoToProgram', { idProgramma, nome });
      window.location.href = `routine_edit.php?giorno=${data.idGiorno}`;
    });
  }

  document.querySelectorAll('[data-duplicate-program]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const idProgramma = btn.getAttribute('data-duplicate-program');
      const titolo = prompt('Titolo della copia', 'Copia programma');
      if (!titolo) return;
      await postForm('duplicateProgram', { idProgramma, titolo });
      window.location.reload();
    });
  });

  const assignForm = document.querySelector('[data-assign-form]');
  if (assignForm) {
    assignForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      await postForm('assignProgramToClient', {
        idProgramma: assignForm.querySelector('[name="idProgramma"]').value,
        idCliente: assignForm.querySelector('[name="idCliente"]').value,
        stato: 'attiva'
      });
      window.location.reload();
    });
  }
})();
