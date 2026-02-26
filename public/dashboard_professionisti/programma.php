<?php
require __DIR__ . '/common.php';
require_once __DIR__ . '/../models/programmi_model.php';

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
$clients = $professionistaId ? ProgrammiModel::listPtClients($professionistaId) : [];

renderStart('Programma', 'allenamenti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell">
  <div class="program-toolbar">
    <a href="allenamenti.php" class="link-btn">← Libreria</a>
    <h2 class="section-title" style="margin:0"><?= h((string)$program['titolo']) ?></h2>
    <button class="btn" data-duplicate-program="<?= (int)$program['idProgramma'] ?>">Duplica</button>
  </div>

  <p class="muted"><?= h((string)($program['descrizione'] ?? '')) ?></p>

  <div class="day-list">
    <?php foreach ($program['giorni'] as $giorno): ?>
      <article class="day-card">
        <h3><?= h((string)$giorno['nome']) ?></h3>
        <p class="muted-sm"><?= h((string)($giorno['previewEsercizi'] ?? 'Nessun esercizio')) ?></p>
        <a class="btn" href="routine_edit.php?giorno=<?= (int)$giorno['idGiorno'] ?>">Modifica routine</a>
      </article>
    <?php endforeach; ?>

    <article class="add-day-card">
      <form data-add-day-form>
        <input type="hidden" name="idProgramma" value="<?= (int)$program['idProgramma'] ?>" />
        <input class="dark-input" name="nome" placeholder="Push / Pull / Legs" />
        <button type="submit">Aggiungi allenamento</button>
      </form>
    </article>
  </div>

  <div class="divider"></div>

  <h3>Assegna a cliente</h3>
  <form class="inline-form" data-assign-form>
    <input type="hidden" name="idProgramma" value="<?= (int)$program['idProgramma'] ?>" />
    <select class="dark-select" name="idCliente" required>
      <option value="">Seleziona cliente</option>
      <?php foreach ($clients as $client): ?>
        <option value="<?= (int)$client['idCliente'] ?>"><?= h(trim((string)$client['nome'] . ' ' . (string)$client['cognome'])) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn primary" type="submit">Assegna programma</button>
  </form>

  <ul>
    <?php foreach ($program['assegnazioni'] as $assegnazione): ?>
      <li><?= h(trim((string)$assegnazione['nome'] . ' ' . (string)$assegnazione['cognome'])) ?> — <?= h((string)$assegnazione['stato']) ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php
renderEnd('<script src="../assets/js/program_library.js"></script>');
