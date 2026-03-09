<?php
require __DIR__ . '/common.php';

renderStart('Nutrizione', 'nutrizione', $email, $roleBadge, $isPt, $isNutrizionista);

if (!$isNutrizionista):
?>
  <section class="card">
    <h2 class="section-title">Nutrizione</h2>
    <p class="muted">Questa sezione è disponibile solo per professionisti con ruolo nutrizionista.</p>
  </section>
<?php
  renderEnd();
  exit;
endif;

$cartellePiani = [
  ['idCartella' => 11, 'nome' => 'Dimagrimento', 'descrizione' => 'Piani ipocalorici progressivi e sostenibili.'],
  ['idCartella' => 12, 'nome' => 'Massa muscolare', 'descrizione' => 'Surplus controllato con focus performance.'],
  ['idCartella' => 13, 'nome' => 'Mantenimento', 'descrizione' => 'Gestione equilibrio energetico a lungo termine.'],
  ['idCartella' => 14, 'nome' => 'Sport specifici', 'descrizione' => 'Protocolli dedicati a sport di endurance e forza.'],
];

$pianiAlimentari = [
  ['idPiano' => 101, 'cartellaId' => 11, 'nome' => 'Cut Base 1800', 'descrizione' => 'Deficit moderato con alta sazietà.', 'preview' => "3 pasti + 1 snack\nDistribuzione proteica uniforme\nVerdure libere"],
  ['idPiano' => 102, 'cartellaId' => 11, 'nome' => 'Cut Rotazione CHO', 'descrizione' => 'Carboidrati ciclizzati ON/OFF.', 'preview' => "Giorni ON: carbo più alti\nGiorni OFF: carbo ridotti\nIdratazione minima 2L"],
  ['idPiano' => 201, 'cartellaId' => 12, 'nome' => 'Bulk Pulito 2900', 'descrizione' => 'Surplus graduale, digestione leggera.', 'preview' => "5 pasti giornalieri\nIncremento +100 kcal/2 settimane\nSnack pre nanna"],
  ['idPiano' => 301, 'cartellaId' => 13, 'nome' => 'Maintenance Smart', 'descrizione' => 'Stabilità del peso e flessibilità.', 'preview' => "Struttura flessibile 80/20\nControllo porzioni\nWeekend guidelines"],
];

$cartellaAttivaId = (int)($_GET['cartella'] ?? 0);
$pianoAttivoId = (int)($_GET['piano'] ?? 0);
$cartellaAttiva = null;
$pianoAttivo = null;

foreach ($cartellePiani as $cartella) {
  if ((int)$cartella['idCartella'] === $cartellaAttivaId) {
    $cartellaAttiva = $cartella;
    break;
  }
}

if ($cartellaAttivaId > 0 && !$cartellaAttiva) {
  header('Location: nutrizione.php');
  exit;
}

$pianiCartella = [];
if ($cartellaAttiva) {
  foreach ($pianiAlimentari as $piano) {
    if ((int)$piano['cartellaId'] === (int)$cartellaAttiva['idCartella']) {
      $pianiCartella[] = $piano;
    }
  }
}

if ($pianoAttivoId > 0 && !$cartellaAttiva) {
  header('Location: nutrizione.php');
  exit;
}

if ($pianoAttivoId > 0 && $cartellaAttiva) {
  foreach ($pianiCartella as $piano) {
    if ((int)$piano['idPiano'] === $pianoAttivoId) {
      $pianoAttivo = $piano;
      break;
    }
  }

  if (!$pianoAttivo) {
    header('Location: nutrizione.php?cartella=' . (int)$cartellaAttiva['idCartella']);
    exit;
  }
}
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell nutrition-shell">
  <?php if (!$cartellaAttiva): ?>
    <div class="library-toolbar">
      <h2 class="section-title" style="margin:0">Libreria piani alimentari</h2>
    </div>

    <div class="folder-grid">
      <?php foreach ($cartellePiani as $cartella): ?>
        <?php $descrizioneCartella = trim((string)($cartella['descrizione'] ?? '')); ?>
        <article class="folder-card folder-item nutrition-folder-card">
          <a class="folder-link" href="nutrizione.php?cartella=<?= (int)$cartella['idCartella'] ?>">
            <div>
              <strong>📁 <?= h((string)$cartella['nome']) ?></strong>
              <p class="muted-sm" <?= $descrizioneCartella === '' ? 'hidden' : '' ?>><?= h($descrizioneCartella) ?></p>
            </div>
          </a>
          <div class="folder-actions">
            <button
              type="button"
              class="icon-btn"
              data-open-rename-folder
              data-folder-id="<?= (int)$cartella['idCartella'] ?>"
              data-folder-name="<?= h((string)$cartella['nome']) ?>"
              aria-label="Modifica cartella"
              title="Modifica cartella"
            >✎</button>
            <button
              type="button"
              class="icon-btn danger"
              data-open-delete-folder
              data-folder-id="<?= (int)$cartella['idCartella'] ?>"
              data-folder-name="<?= h((string)$cartella['nome']) ?>"
              aria-label="Elimina cartella"
            >🗑</button>
          </div>
        </article>
      <?php endforeach; ?>

      <button type="button" class="folder-card folder-create" data-open-create-folder>
        <span class="create-plus">＋</span>
        <span class="muted-sm">Crea nuova cartella</span>
      </button>
    </div>
  <?php elseif (!$pianoAttivo): ?>
    <div class="library-toolbar">
      <a href="nutrizione.php" class="link-btn">← Torna alle cartelle</a>
      <h2 class="section-title" style="margin:0">📁 <?= h((string)$cartellaAttiva['nome']) ?></h2>
    </div>

    <div class="program-grid nutrition-plan-grid">
      <?php foreach ($pianiCartella as $piano): ?>
        <article class="program-card nutrition-plan-card" onclick="window.location.href='nutrizione.php?cartella=<?= (int)$cartellaAttiva['idCartella'] ?>&piano=<?= (int)$piano['idPiano'] ?>'">
          <h4><?= h((string)$piano['nome']) ?></h4>
          <p class="muted-sm"><?= h((string)$piano['descrizione']) ?></p>
          <div class="program-meta nutrition-preview">
            <span><?= nl2br(h((string)$piano['preview'])) ?></span>
          </div>
        </article>
      <?php endforeach; ?>

      <button type="button" class="program-card add-program-card" data-open-create-plan data-folder-id="<?= (int)$cartellaAttiva['idCartella'] ?>">
        <div>
          <div class="create-plus">＋</div>
          <div class="muted-sm">Crea nuovo piano alimentare</div>
        </div>
      </button>
    </div>
  <?php else: ?>
    <div class="program-toolbar">
      <a href="nutrizione.php?cartella=<?= (int)$cartellaAttiva['idCartella'] ?>" class="link-btn">← Torna alle cartelle</a>
      <h2 class="section-title" style="margin:0"><?= h((string)$pianoAttivo['nome']) ?></h2>
    </div>

    <article class="folder-card nutrition-builder-shell">
      <div class="program-toolbar nutrition-builder-toolbar">
        <button type="button" class="btn primary">Assegna piano</button>
        <button type="button" class="btn" data-duplicate-plan>Duplica</button>
        <button type="button" class="btn danger" data-open-delete-plan data-plan-id="<?= (int)$pianoAttivo['idPiano'] ?>" data-plan-name="<?= h((string)$pianoAttivo['nome']) ?>">Elimina</button>
      </div>

      <div class="nutrition-builder-fields">
        <label class="muted-sm" for="diet-plan-name">Nome piano</label>
        <input id="diet-plan-name" class="dark-input" type="text" value="<?= h((string)$pianoAttivo['nome']) ?>" />

        <label class="muted-sm" for="diet-plan-description">Descrizione</label>
        <textarea id="diet-plan-description" class="dark-textarea" rows="8"><?= h((string)$pianoAttivo['descrizione']) ?></textarea>
      </div>
    </article>
  <?php endif; ?>
</section>

<div class="modal-layer" data-modal="create-folder">
  <div class="modal-card">
    <h3>Crea nuova cartella</h3>
    <form>
      <input class="dark-input" type="text" name="nomeCartella" placeholder="Nome cartella" required />
      <p class="muted-sm">Questa è una modale placeholder in attesa del collegamento backend.</p>
      <div class="library-toolbar" style="justify-content:flex-end">
        <button class="btn" type="button" data-close-modal>Annulla</button>
        <button class="btn primary" type="submit">Crea nuova cartella</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-layer" data-modal="rename-folder">
  <div class="modal-card">
    <h3>Rinomina cartella</h3>
    <form>
      <input class="dark-input" type="text" name="nuovoNomeCartella" data-rename-folder-input placeholder="Nome cartella" required />
      <div class="library-toolbar" style="justify-content:flex-end">
        <button class="btn" type="button" data-close-modal>Annulla</button>
        <button class="btn primary" type="submit">Salva</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-layer" data-modal="delete-folder">
  <div class="modal-card">
    <h3>Elimina cartella</h3>
    <p class="muted-sm">Confermi l'eliminazione della cartella <strong data-delete-folder-name></strong>?</p>
    <div class="library-toolbar" style="justify-content:flex-end">
      <button class="btn" type="button" data-close-modal>Annulla</button>
      <button class="btn danger" type="button">Elimina</button>
    </div>
  </div>
</div>

<div class="modal-layer" data-modal="create-plan">
  <div class="modal-card">
    <h3>Crea nuovo piano alimentare</h3>
    <form>
      <input class="dark-input" type="text" name="nomePiano" placeholder="Nome piano" required />
      <textarea class="dark-textarea" name="descrizionePiano" placeholder="Descrizione"></textarea>
      <div class="library-toolbar" style="justify-content:flex-end">
        <button class="btn" type="button" data-close-modal>Annulla</button>
        <button class="btn primary" type="submit">Crea nuovo piano alimentare</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-layer" data-modal="delete-plan">
  <div class="modal-card">
    <h3>Elimina piano alimentare</h3>
    <p class="muted-sm">Confermi l'eliminazione del piano <strong data-delete-plan-name></strong>?</p>
    <div class="library-toolbar" style="justify-content:flex-end">
      <button class="btn" type="button" data-close-modal>Annulla</button>
      <button class="btn danger" type="button">Elimina</button>
    </div>
  </div>
</div>

<style>
  .nutrition-shell {
    width: min(100%, 1120px);
  }

  .nutrition-folder-card,
  .nutrition-plan-card {
    transition: transform .15s ease, border-color .15s ease, background .15s ease;
  }

  .nutrition-folder-card:hover,
  .nutrition-plan-card:hover {
    transform: translateY(-2px);
    border-color: rgba(134, 195, 255, .45);
    background: rgba(255, 255, 255, .08);
  }

  .nutrition-plan-grid {
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  }

  .nutrition-preview {
    margin-top: 8px;
    line-height: 1.4;
  }

  .nutrition-builder-shell {
    display: grid;
    gap: 16px;
    max-width: 900px;
  }

  .nutrition-builder-toolbar {
    justify-content: flex-start;
  }

  .nutrition-builder-fields {
    display: grid;
    gap: 8px;
  }
</style>
<?php
renderEnd(<<<'SCRIPT'
<script>
  (function () {
    const modals = document.querySelectorAll('[data-modal]');

    function openModal(name) {
      const modal = document.querySelector('[data-modal="' + name + '"]');
      if (modal) {
        modal.classList.add('open');
      }
    }

    function closeAllModals() {
      modals.forEach(function (modal) {
        modal.classList.remove('open');
      });
    }

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
      button.addEventListener('click', closeAllModals);
    });

    document.querySelectorAll('[data-modal]').forEach(function (modal) {
      modal.addEventListener('click', function (event) {
        if (event.target === modal) {
          closeAllModals();
        }
      });
    });

    const createFolderBtn = document.querySelector('[data-open-create-folder]');
    if (createFolderBtn) {
      createFolderBtn.addEventListener('click', function () {
        openModal('create-folder');
      });
    }

    document.querySelectorAll('[data-open-rename-folder]').forEach(function (button) {
      button.addEventListener('click', function () {
        const input = document.querySelector('[data-rename-folder-input]');
        if (input) {
          input.value = button.getAttribute('data-folder-name') || '';
        }
        openModal('rename-folder');
      });
    });

    document.querySelectorAll('[data-open-delete-folder]').forEach(function (button) {
      button.addEventListener('click', function () {
        const target = document.querySelector('[data-delete-folder-name]');
        if (target) {
          target.textContent = button.getAttribute('data-folder-name') || '';
        }
        openModal('delete-folder');
      });
    });

    const createPlanBtn = document.querySelector('[data-open-create-plan]');
    if (createPlanBtn) {
      createPlanBtn.addEventListener('click', function () {
        openModal('create-plan');
      });
    }

    const deletePlanBtn = document.querySelector('[data-open-delete-plan]');
    if (deletePlanBtn) {
      deletePlanBtn.addEventListener('click', function () {
        const target = document.querySelector('[data-delete-plan-name]');
        if (target) {
          target.textContent = deletePlanBtn.getAttribute('data-plan-name') || '';
        }
        openModal('delete-plan');
      });
    }

    document.querySelectorAll('.modal-layer form').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        closeAllModals();
      });
    });
  })();
</script>
SCRIPT
);
