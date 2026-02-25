<?php
require __DIR__ . '/common.php';
require_once __DIR__ . '../app/Models/SupportoModel.php';

$errors = [];
$messages = [];
$associazioniAttive = [
  'pt' => null,
  'nutrizionista' => null,
];

if (!$dbAvailable) {
  $errors[] = $dbError ?? 'Database non disponibile.';
} else {
  try {
    $clienteId = SupportoModel::getClienteIdByUtenteId((int)$user['idUtente']);
    if (!$clienteId) {
      $errors[] = 'Profilo cliente non trovato per questo account.';
    } else {
      $associazioniAttive = SupportoModel::listAssociazioniAttiveCliente($clienteId);
    }
  } catch (Throwable $e) {
    $errors[] = 'Errore nel caricamento associazioni attive.';
  }
}

renderStart('Supporto cliente', 'supporto', $email);
?>
<section class="card hero">
  <span class="pill">Supporto</span>
  <h1>Comunicazione con il team</h1>
  <p class="lead">Inserisci una ID-Key per associare un professionista PT o Nutrizionista. In caso di sostituzione, la precedente associazione viene archiviata e la chat viene bloccata (RF-014, RF-016).</p>
</section>

<section class="grid">
  <article class="card span-6">
    <h3 class="section-title">Associa professionista tramite ID-Key</h3>

    <?php foreach ($messages as $message): ?>
      <div class="okbox" style="margin-bottom:10px"><?= h($message) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
      <div class="alert" style="margin-bottom:10px"><?= h($error) ?></div>
    <?php endforeach; ?>

    <form id="idkeyForm" class="toolbar" style="display:flex;flex-direction:column;gap:10px;align-items:flex-start">
      <div class="field" style="width:100%">
        <label for="idkeyInput">Inserisci ID-Key</label>
        <input id="idkeyInput" name="codice" type="text" required placeholder="Es. AF-PT-ABC123" style="width:100%" />
      </div>
      <button class="btn primary" type="submit">Associa Professionista</button>
    </form>

    <div id="idkeyFeedback" class="note" style="margin-top:10px"></div>
  </article>

  <article class="card span-6">
    <h3 class="section-title">Associazioni attive</h3>
    <table>
      <thead>
      <tr>
        <th>Tipo</th>
        <th>Professionista</th>
        <th>Email</th>
        <th>Iniziata il</th>
      </tr>
      </thead>
      <tbody id="associazioniTableBody">
      <?php foreach (['pt' => 'PT associato', 'nutrizionista' => 'Nutrizionista associato'] as $tipo => $label): ?>
        <?php $assoc = $associazioniAttive[$tipo] ?? null; ?>
        <tr data-tipo="<?= h($tipo) ?>">
          <td><?= h($label) ?></td>
          <td><?= h($assoc['nomeCompleto'] ?? '—') ?></td>
          <td><?= h($assoc['email'] ?? '—') ?></td>
          <td><?= h($assoc['iniziataIl'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="note" style="margin-top:8px">Le associazioni terminate mantengono lo storico; i programmi restano consultabili dal cliente (RF-015).</p>
  </article>
</section>
<?php
$scripts = <<<'HTML'
<script>
(function () {
  const form = document.getElementById('idkeyForm');
  const input = document.getElementById('idkeyInput');
  const feedback = document.getElementById('idkeyFeedback');
  const tableBody = document.getElementById('associazioniTableBody');

  if (!form || !input || !feedback || !tableBody) {
    return;
  }

  function setFeedback(message, isError) {
    feedback.textContent = message;
    feedback.className = isError ? 'alert' : 'okbox';
    feedback.style.marginTop = '10px';
  }

  function updateRow(tipo, data) {
    const row = tableBody.querySelector('tr[data-tipo="' + tipo + '"]');
    if (!row) {
      return;
    }

    const cols = row.querySelectorAll('td');
    cols[1].textContent = data && data.nomeCompleto ? data.nomeCompleto : '—';
    cols[2].textContent = data && data.email ? data.email : '—';
    cols[3].textContent = data && data.iniziataIl ? data.iniziataIl : '—';
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();

    const codice = String(input.value || '').trim();
    if (!codice) {
      setFeedback('Inserisci una ID-Key valida.', true);
      return;
    }

    try {
      const formData = new FormData();
      formData.append('codice', codice);

      const response = await fetch('../controllers/supporto_add_idkey.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error((payload && payload.message) || 'Errore durante associazione ID-Key.');
      }

      updateRow('pt', payload.associazioni ? payload.associazioni.pt : null);
      updateRow('nutrizionista', payload.associazioni ? payload.associazioni.nutrizionista : null);

      let successMessage = payload.message || 'Associazione completata con successo.';
      if (payload.archiviataPrecedente) {
        successMessage += ' La precedente associazione dello stesso tipo è stata archiviata.';
      }
      setFeedback(successMessage, false);

      form.reset();
    } catch (error) {
      setFeedback(error.message || 'Errore durante associazione ID-Key.', true);
    }
  });
})();
</script>
HTML;

renderEnd($scripts);
