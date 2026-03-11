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
  const folderModalTitle = folderModal?.querySelector('[data-folder-modal-title]');
  const folderFeedback = folderModal?.querySelector('[data-folder-feedback]');
  const folderSubmitBtn = folderModal?.querySelector('[data-folder-submit]');
  const folderNomeInput = folderModal?.querySelector('[name="nome"]');
  const folderDescrizioneInput = folderModal?.querySelector('[name="descrizione"]');
  let modalMode = 'create';
  let editingCartellaId = null;

  function resetFolderFeedback() {
    if (folderFeedback) {
      folderFeedback.textContent = '';
    }
  }

  function setFolderModalState(mode, payload = {}) {
    modalMode = mode;
    editingCartellaId = mode === 'edit' ? Number(payload.idCartella || 0) : null;

    if (folderModalTitle) {
      folderModalTitle.textContent = mode === 'edit' ? 'Modifica cartella' : 'Crea nuova cartella';
    }
    if (folderSubmitBtn) {
      folderSubmitBtn.textContent = mode === 'edit' ? 'Salva modifiche' : 'Crea cartella';
      folderSubmitBtn.disabled = false;
    }
    if (folderNomeInput) {
      folderNomeInput.value = mode === 'edit' ? (payload.nome || '') : '';
    }
    if (folderDescrizioneInput) {
      folderDescrizioneInput.value = mode === 'edit' ? (payload.descrizione || '') : '';
    }

    resetFolderFeedback();
  }

  document.querySelector('[data-open-folder-modal]')?.addEventListener('click', () => {
    setFolderModalState('create');
    toggleModal(folderModal, true);
  });

  document.querySelector('[data-close-folder-modal]')?.addEventListener('click', () => {
    setFolderModalState('create');
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
      setFolderModalState('create');
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
      const nome = folderNomeInput?.value.trim() || '';
      const descrizione = folderDescrizioneInput?.value.trim() || '';
      if (!nome) {
        if (folderFeedback) {
          folderFeedback.textContent = 'Inserisci un nome cartella.';
        }
        return;
      }

      if (folderSubmitBtn) {
        folderSubmitBtn.disabled = true;
      }
      resetFolderFeedback();

      try {
        if (modalMode === 'edit') {
          if (!editingCartellaId) {
            throw new Error('Cartella non valida.');
          }

          const res = await fetch('api/update_cartella.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
              idCartella: editingCartellaId,
              nome,
              descrizione: descrizione || null
            })
          });

          const data = await res.json();
          if (!res.ok || !data.ok) {
            throw new Error(data.error || 'Errore aggiornamento cartella.');
          }

          const folderCard = document.querySelector(`[data-folder-card="${editingCartellaId}"]`);
          const folderTitle = folderCard?.querySelector('[data-folder-title]');
          const folderDescription = folderCard?.querySelector('[data-folder-description]');
          const editBtn = folderCard?.querySelector('[data-edit-cartella]');
          const deleteBtn = folderCard?.querySelector('[data-delete-cartella]');

          if (folderTitle) {
            folderTitle.textContent = `📁 ${nome}`;
          }
          if (folderDescription) {
            folderDescription.textContent = descrizione;
            folderDescription.hidden = !descrizione;
          }
          if (editBtn) {
            editBtn.setAttribute('data-cartella-nome', nome);
            editBtn.setAttribute('data-cartella-descrizione', descrizione);
          }
          if (deleteBtn) {
            deleteBtn.setAttribute('data-folder-name', nome);
          }

          setFolderModalState('create');
          toggleModal(folderModal, false);
          return;
        }

        await postForm('createFolder', { nome });
        window.location.reload();
      } catch (error) {
        if (folderFeedback) {
          folderFeedback.textContent = error.message || 'Errore salvataggio cartella.';
        }
      } finally {
        if (folderSubmitBtn) {
          folderSubmitBtn.disabled = false;
        }
      }
    });
  }

  document.querySelectorAll('[data-edit-cartella]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      const idCartella = Number(btn.getAttribute('data-edit-cartella'));
      if (!idCartella) {
        return;
      }

      setFolderModalState('edit', {
        idCartella,
        nome: btn.getAttribute('data-cartella-nome') || '',
        descrizione: btn.getAttribute('data-cartella-descrizione') || ''
      });
      toggleModal(folderModal, true);
    });
  });

  const programForm = document.querySelector('[data-program-form]');
  if (programForm) {
    programForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const titolo = programForm.querySelector('[name="titolo"]').value.trim();
      const descrizione = programForm.querySelector('[name="descrizione"]').value.trim();
      const cartellaId = programForm.querySelector('[name="cartellaId"]').value;
      if (!titolo || !cartellaId) return;

      const data = await postForm('createProgramTemplate', { titolo, descrizione, cartellaId });
      const params = new URLSearchParams({ id: data.idProgramma });
      if (cartellaId) {
        params.set('cartella', cartellaId);
      }
      window.location.href = `programma.php?${params.toString()}`;
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

  const duplicateProgramModal = document.querySelector('[data-duplicate-program-modal]');
  const duplicateProgramTitleInput = document.querySelector('[data-duplicate-program-title]');
  const duplicateProgramFeedback = document.querySelector('[data-duplicate-program-feedback]');
  const cancelDuplicateProgramBtn = document.querySelector('[data-cancel-duplicate-program]');
  const confirmDuplicateProgramBtn = document.querySelector('[data-confirm-duplicate-program]');
  let duplicateProgramId = null;

  function setDuplicateProgramFeedback(message) {
    if (duplicateProgramFeedback) {
      duplicateProgramFeedback.textContent = message || '';
      duplicateProgramFeedback.classList.remove('ok');
    }
  }

  function closeDuplicateProgramModal() {
    toggleModal(duplicateProgramModal, false);
    duplicateProgramId = null;
    setDuplicateProgramFeedback('');
    if (duplicateProgramTitleInput) {
      duplicateProgramTitleInput.value = 'Copia programma';
    }
    if (confirmDuplicateProgramBtn) {
      confirmDuplicateProgramBtn.disabled = false;
      confirmDuplicateProgramBtn.textContent = 'Duplica';
    }
  }

  document.querySelectorAll('[data-duplicate-program]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const idProgramma = btn.getAttribute('data-duplicate-program');
      if (!idProgramma) {
        return;
      }

      duplicateProgramId = idProgramma;
      setDuplicateProgramFeedback('');
      if (duplicateProgramTitleInput) {
        duplicateProgramTitleInput.value = 'Copia programma';
      }
      toggleModal(duplicateProgramModal, true);
      duplicateProgramTitleInput?.focus();
      duplicateProgramTitleInput?.select();
    });
  });

  duplicateProgramModal?.addEventListener('click', (event) => {
    if (event.target === duplicateProgramModal) {
      closeDuplicateProgramModal();
    }
  });

  cancelDuplicateProgramBtn?.addEventListener('click', () => {
    closeDuplicateProgramModal();
  });

  confirmDuplicateProgramBtn?.addEventListener('click', async () => {
    if (!duplicateProgramId) {
      return;
    }

    const titolo = duplicateProgramTitleInput?.value.trim() || '';
    if (!titolo) {
      setDuplicateProgramFeedback('Inserisci un titolo valido.');
      duplicateProgramTitleInput?.focus();
      return;
    }

    confirmDuplicateProgramBtn.disabled = true;
    confirmDuplicateProgramBtn.textContent = 'Duplicazione...';
    setDuplicateProgramFeedback('');

    try {
      await postForm('duplicateProgram', { idProgramma: duplicateProgramId, titolo });
      window.location.reload();
    } catch (error) {
      setDuplicateProgramFeedback(error.message || 'Errore durante duplicazione programma.');
      confirmDuplicateProgramBtn.disabled = false;
      confirmDuplicateProgramBtn.textContent = 'Duplica';
    }
  });

  duplicateProgramTitleInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      confirmDuplicateProgramBtn?.click();
    }
  });


  const deleteFolderModal = document.querySelector('[data-delete-folder-modal]');
  const cancelDeleteFolderBtn = document.querySelector('[data-cancel-delete-folder]');
  const confirmDeleteFolderBtn = document.querySelector('[data-confirm-delete-folder]');
  const deleteFolderName = document.querySelector('[data-delete-folder-name]');
  const deleteFolderFeedback = document.querySelector('[data-delete-folder-feedback]');
  let deleteFolderId = null;
  const confirmDeleteFolderDefaultText = confirmDeleteFolderBtn?.textContent || 'Elimina';

  function resetDeleteFolderModalState() {
    if (deleteFolderFeedback) {
      deleteFolderFeedback.textContent = '';
    }
    if (confirmDeleteFolderBtn) {
      confirmDeleteFolderBtn.disabled = false;
      confirmDeleteFolderBtn.textContent = confirmDeleteFolderDefaultText;
    }
  }

  function closeDeleteFolderModal() {
    toggleModal(deleteFolderModal, false);
    deleteFolderId = null;
    if (deleteFolderName) {
      deleteFolderName.textContent = '';
    }
    resetDeleteFolderModalState();
  }

  document.querySelectorAll('[data-delete-cartella]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      const folderId = Number(btn.getAttribute('data-delete-cartella'));
      if (!folderId) {
        return;
      }

      deleteFolderId = folderId;
      if (deleteFolderName) {
        const folderName = btn.getAttribute('data-folder-name') || '';
        deleteFolderName.textContent = folderName ? `Cartella: ${folderName}` : '';
      }
      resetDeleteFolderModalState();
      toggleModal(deleteFolderModal, true);
    });
  });

  deleteFolderModal?.addEventListener('click', (event) => {
    if (event.target === deleteFolderModal) {
      closeDeleteFolderModal();
    }
  });

  cancelDeleteFolderBtn?.addEventListener('click', () => {
    closeDeleteFolderModal();
  });

  confirmDeleteFolderBtn?.addEventListener('click', async () => {
    if (!deleteFolderId) {
      return;
    }

    confirmDeleteFolderBtn.disabled = true;
    confirmDeleteFolderBtn.textContent = 'Eliminazione...';
    if (deleteFolderFeedback) {
      deleteFolderFeedback.textContent = '';
    }

    try {
      const res = await fetch('api/delete_cartella.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ idCartella: deleteFolderId })
      });

      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Errore eliminazione cartella.');
      }

      const folderCard = document.querySelector(`[data-folder-card="${deleteFolderId}"]`);
      folderCard?.remove();
      closeDeleteFolderModal();
    } catch (error) {
      if (deleteFolderFeedback) {
        deleteFolderFeedback.textContent = error.message || 'Errore eliminazione cartella.';
      }
      confirmDeleteFolderBtn.disabled = false;
      confirmDeleteFolderBtn.textContent = confirmDeleteFolderDefaultText;
    }
  });

  const deleteProgramModal = document.querySelector('[data-delete-program-modal]');
  const cancelDeleteProgramBtn = document.querySelector('[data-cancel-delete-program]');
  const confirmDeleteProgramBtn = document.querySelector('[data-confirm-delete-program]');
  let deleteProgramPayload = null;

  document.querySelectorAll('[data-delete-program]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const idProgramma = btn.getAttribute('data-delete-program');
      const folderId = btn.getAttribute('data-folder-id') || '0';
      if (!idProgramma) return;

      deleteProgramPayload = { idProgramma, folderId };
      toggleModal(deleteProgramModal, true);
    });
  });

  deleteProgramModal?.addEventListener('click', (event) => {
    if (event.target === deleteProgramModal) {
      toggleModal(deleteProgramModal, false);
      deleteProgramPayload = null;
    }
  });

  cancelDeleteProgramBtn?.addEventListener('click', () => {
    toggleModal(deleteProgramModal, false);
    deleteProgramPayload = null;
  });

  confirmDeleteProgramBtn?.addEventListener('click', async () => {
    if (!deleteProgramPayload) return;

    try {
      await postForm('deleteProgram', { idProgramma: deleteProgramPayload.idProgramma });
      const target = deleteProgramPayload.folderId && Number(deleteProgramPayload.folderId) > 0
        ? `allenamenti.php?cartella=${encodeURIComponent(deleteProgramPayload.folderId)}`
        : 'allenamenti.php';
      window.location.href = target;
    } catch (error) {
      window.alert(error.message || 'Impossibile eliminare il programma.');
    }
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
