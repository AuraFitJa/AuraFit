<?php
require __DIR__ . '/common.php';

$folders = [];
$selectedFolderId = isset($_GET['cartella']) ? (string)$_GET['cartella'] : '';
$selectedFolder = null;
$programs = [];
$assignedFolderKey = 'assigned';
$assignedFolder = null;
$clienteId = null;
$ptProfessionistaId = null;
$ptUserId = null;
$ptDisplayName = '';

if ($dbAvailable) {
  try {
    $clienteRow = Database::exec(
      'SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1',
      [(int)$user['idUtente']]
    )->fetch();

    if ($clienteRow) {
      $clienteId = (int)$clienteRow['idCliente'];

      $ptRow = Database::exec(
        "SELECT p.idProfessionista, p.idUtente, u.nome, u.cognome
         FROM Associazioni a
         INNER JOIN Professionisti p ON p.idProfessionista = a.professionista
         INNER JOIN Utenti u ON u.idUtente = p.idUtente
         WHERE a.cliente = ?
           AND a.tipoAssociazione = 'pt'
           AND a.attivaFlag = 1
         ORDER BY a.iniziataIl DESC
         LIMIT 1",
        [$clienteId]
      )->fetch();

      if ($ptRow) {
        $ptProfessionistaId = (int)$ptRow['idProfessionista'];
        $ptUserId = (int)$ptRow['idUtente'];
        $ptDisplayName = trim((string)($ptRow['nome'] ?? '') . ' ' . (string)($ptRow['cognome'] ?? ''));

        $folders = Database::exec(
          'SELECT idCartella, nome, ordine
           FROM ProgrammiCartelle
           WHERE professionista = ?
           ORDER BY ordine ASC, nome ASC',
          [$ptProfessionistaId]
        )->fetchAll();

        $assignedPrograms = Database::exec(
          "SELECT p.idProgramma, p.titolo, p.descrizione, p.aggiornatoIl,
                  ap.assegnatoIl, ap.stato,
                  (
                    SELECT GROUP_CONCAT(e.nome ORDER BY g.ordine ASC, eg.ordine ASC SEPARATOR ', ')
                    FROM GiorniAllenamento g
                    INNER JOIN EserciziGiorno eg ON eg.giorno = g.idGiorno
                    INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
                    WHERE g.programma = p.idProgramma
                  ) AS previewEsercizi
           FROM AssegnazioniProgramma ap
           INNER JOIN ProgrammiAllenamento p ON p.idProgramma = ap.programma
           WHERE ap.cliente = ?
             AND p.stato <> 'archiviato'
           ORDER BY ap.assegnatoIl DESC",
          [$clienteId]
        )->fetchAll();

        if ($assignedPrograms) {
          $assignedFolder = [
            'idCartella' => $assignedFolderKey,
            'nome' => 'Programmi Assegnati da ' . $ptDisplayName . ' "Personal Trainer"',
            'isAssigned' => true,
            'programs' => $assignedPrograms,
          ];
        }
      }
    }
  } catch (Throwable $e) {
    $folders = [];
    $assignedFolder = null;
  }
}

if ($selectedFolderId === '' && !empty($folders)) {
  $selectedFolderId = (string)$folders[0]['idCartella'];
}
if ($selectedFolderId === '' && $assignedFolder) {
  $selectedFolderId = $assignedFolderKey;
}

foreach ($folders as $folder) {
  if ((string)$folder['idCartella'] === $selectedFolderId) {
    $selectedFolder = $folder;
    break;
  }
}

if ($selectedFolderId === $assignedFolderKey && $assignedFolder) {
  $selectedFolder = $assignedFolder;
}

if ($selectedFolder && empty($selectedFolder['isAssigned']) && $ptUserId) {
  $programs = Database::exec(
    "SELECT p.idProgramma, p.titolo, p.descrizione,
            (
              SELECT GROUP_CONCAT(e.nome ORDER BY g.ordine ASC, eg.ordine ASC SEPARATOR ', ')
              FROM GiorniAllenamento g
              INNER JOIN EserciziGiorno eg ON eg.giorno = g.idGiorno
              INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
              WHERE g.programma = p.idProgramma
            ) AS previewEsercizi
     FROM ProgrammiAllenamento p
     WHERE p.creatoreUtente = ?
       AND p.isTemplate = 1
       AND p.stato <> 'archiviato'
       AND p.cartellaId = ?
     ORDER BY p.aggiornatoIl DESC, p.idProgramma DESC",
    [$ptUserId, (int)$selectedFolder['idCartella']]
  )->fetchAll();
}

if ($selectedFolder && !empty($selectedFolder['isAssigned'])) {
  $programs = $selectedFolder['programs'] ?? [];
}

renderStart('Allenamenti cliente', 'allenamenti', $email);
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell">
  <div class="library-toolbar">
    <h2 class="section-title" style="margin:0">Le tue cartelle allenamento</h2>
  </div>

  <div class="folder-grid">
    <?php foreach ($folders as $folder): ?>
      <?php $isActive = ((string)$folder['idCartella'] === (string)$selectedFolderId); ?>
      <a class="folder-card folder-link<?= $isActive ? ' active-folder' : '' ?>" href="allenamenti.php?cartella=<?= (int)$folder['idCartella'] ?>">
        <strong>📁 <?= h((string)$folder['nome']) ?></strong>
      </a>
    <?php endforeach; ?>

    <?php if ($assignedFolder): ?>
      <?php $isAssignedActive = ($selectedFolderId === $assignedFolderKey); ?>
      <a class="folder-card folder-link assigned-folder<?= $isAssignedActive ? ' active-folder' : '' ?>" href="allenamenti.php?cartella=<?= h($assignedFolderKey) ?>">
        <strong>📁 <?= h((string)$assignedFolder['nome']) ?></strong>
      </a>
    <?php endif; ?>
  </div>

  <?php if ($selectedFolder): ?>
    <div class="library-toolbar" style="margin-top:8px">
      <h3 class="section-title" style="margin:0">📁 <?= h((string)$selectedFolder['nome']) ?></h3>
    </div>

    <div class="program-grid">
      <?php if (!$programs): ?>
        <article class="program-card">
          <h4>Nessun programma disponibile</h4>
          <p class="muted-sm">In questa cartella non ci sono ancora programmi visibili.</p>
        </article>
      <?php endif; ?>

      <?php foreach ($programs as $program): ?>
        <?php
          $exercisePreview = trim((string)($program['previewEsercizi'] ?? ''));
          if ($exercisePreview === '') {
            $exercisePreview = 'Nessun esercizio';
          } elseif (strlen($exercisePreview) > 90) {
            $exercisePreview = substr($exercisePreview, 0, 89) . '…';
          }
        ?>
        <article class="program-card">
          <h4><?= h((string)$program['titolo']) ?></h4>
          <p class="muted-sm"><?= h((string)($program['descrizione'] ?? '')) ?></p>
          <div class="program-meta">
            <span><?= h($exercisePreview) ?></span>
            <?php if (isset($program['assegnatoIl'])): ?>
              <span>Assegnato: <?= h((string)$program['assegnatoIl']) ?></span>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <article class="program-card">
      <h4>Nessuna cartella disponibile</h4>
      <p class="muted-sm">Quando il tuo Personal Trainer condivide la libreria, troverai qui le cartelle.</p>
    </article>
  <?php endif; ?>
</section>

<style>
  .active-folder{border-color:rgba(134,195,255,.75);box-shadow:0 0 0 1px rgba(134,195,255,.35) inset}
  .assigned-folder{background:linear-gradient(135deg,rgba(63,106,255,.22),rgba(75,203,255,.18));border-color:rgba(128,219,255,.6)}
</style>
<?php
renderEnd();
