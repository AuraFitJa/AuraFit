<?php
require __DIR__ . '/common.php';

$errors = [];
$cliente = null;
$programmiAssegnati = [];
$storicoAssociazioni = [];
$questionariAssegnati = [];
$questionariCompilazioni = [];
$compilazioneApertaMeta = null;
$compilazioneApertaRisposte = [];

$idCliente = (int)($_GET['idCliente'] ?? 0);
$idCompilazioneAperta = (int)($_GET['idCompilazione'] ?? 0);

if ($idCliente < 1) {
  $errors[] = 'Cliente non valido.';
} elseif (!$dbAvailable) {
  $errors[] = $dbError ?? 'Database non disponibile.';
} else {
  try {
    $professionistaId = getProfessionistaId($userId);

    if (!$professionistaId) {
      $errors[] = 'Profilo professionista non trovato per questo account.';
    } else {
      $tipiConsentiti = [];
      if ($isPt) {
        $tipiConsentiti[] = 'pt';
      }
      if ($isNutrizionista) {
        $tipiConsentiti[] = 'nutrizionista';
      }

      if (!$tipiConsentiti) {
        $errors[] = 'Ruolo professionista non autorizzato.';
      } else {
        $placeholders = implode(',', array_fill(0, count($tipiConsentiti), '?'));

        $cliente = Database::exec(
          "SELECT c.idCliente, u.nome, u.cognome, u.email, pc.eta, pc.altezzaCm, pc.pesoKg
           FROM Associazioni a
           INNER JOIN Clienti c ON c.idCliente = a.cliente
           INNER JOIN Utenti u ON u.idUtente = c.idUtente
           LEFT JOIN ProfiloCliente pc ON pc.idCliente = c.idCliente
           WHERE a.professionista = ?
             AND a.cliente = ?
             AND a.tipoAssociazione IN ($placeholders)
           ORDER BY a.attivaFlag = 1 DESC, a.iniziataIl DESC
           LIMIT 1",
          array_merge([$professionistaId, $idCliente], $tipiConsentiti)
        )->fetch();

        if (!$cliente) {
          $errors[] = 'Cliente non associato al tuo profilo professionista.';
        } else {
          $programmiAssegnati = Database::exec(
            "SELECT p.idProgramma, p.titolo, ap.stato, ap.assegnatoIl
             FROM AssegnazioniProgramma ap
             INNER JOIN ProgrammiAllenamento p ON p.idProgramma = ap.programma
             WHERE ap.cliente = ?
               AND ap.stato IN ('attivo', 'attiva')
             ORDER BY ap.assegnatoIl DESC
             LIMIT 5",
            [$idCliente]
          )->fetchAll();

          $storicoAssociazioni = Database::exec(
            "SELECT tipoAssociazione, iniziataIl, terminataIl, attivaFlag
             FROM Associazioni
             WHERE professionista = ? AND cliente = ?
             ORDER BY iniziataIl DESC
             LIMIT 5",
            [$professionistaId, $idCliente]
          )->fetchAll();

          $questionariAssegnati = Database::exec(
            "SELECT qa.idAssegnazioneQuestionario, qa.stato, qa.assegnatoIl, qa.disattivatoIl, q.titolo
             FROM QuestionarioAssegnazioni qa
             INNER JOIN Questionari q ON q.idQuestionario = qa.questionario
             WHERE qa.professionista = ? AND qa.cliente = ?
             ORDER BY qa.assegnatoIl DESC",
            [$professionistaId, $idCliente]
          )->fetchAll();

          $questionariCompilazioni = Database::exec(
            "SELECT qc.idCompilazione, qc.numeroCompilazione, qc.stato, qc.inviatoIl, qc.aggiornatoIl, qc.ricompilazioneDi, q.titolo
             FROM QuestionarioCompilazioni qc
             INNER JOIN Questionari q ON q.idQuestionario = qc.questionario
             INNER JOIN QuestionarioAssegnazioni qa ON qa.idAssegnazioneQuestionario = qc.assegnazione
             WHERE qa.professionista = ? AND qc.cliente = ?
             ORDER BY qc.aggiornatoIl DESC",
            [$professionistaId, $idCliente]
          )->fetchAll();

          if ($idCompilazioneAperta > 0) {
            $compilazioneApertaMeta = Database::exec(
              "SELECT qc.idCompilazione, qc.numeroCompilazione, qc.inviatoIl, qc.questionario, q.titolo
               FROM QuestionarioCompilazioni qc
               INNER JOIN QuestionarioAssegnazioni qa ON qa.idAssegnazioneQuestionario = qc.assegnazione
               INNER JOIN Questionari q ON q.idQuestionario = qc.questionario
               WHERE qc.idCompilazione = ?
                 AND qc.cliente = ?
                 AND qa.professionista = ?
               LIMIT 1",
              [$idCompilazioneAperta, $idCliente, $professionistaId]
            )->fetch();

            if ($compilazioneApertaMeta) {
              $compilazioneApertaRisposte = Database::exec(
                "SELECT d.idDomanda, d.testoDomanda, d.tipoDomanda, r.valoreTesto, r.valoreNumero, r.valoreData, r.valoreBoolean, r.valoreJson
                 FROM QuestionarioDomande d
                 LEFT JOIN QuestionarioRisposte r ON r.domanda = d.idDomanda AND r.compilazione = ?
                 WHERE d.questionario = ?
                 ORDER BY d.ordine ASC",
                [$idCompilazioneAperta, $compilazioneApertaMeta['questionario']]
              )->fetchAll();
            }
          }
        }
      }
    }
  } catch (Throwable $e) {
    $errors[] = 'Errore DB scheda cliente: ' . $e->getMessage();
  }
}

$clienteNome = $cliente ? trim((string)$cliente['nome'] . ' ' . (string)$cliente['cognome']) : 'Cliente';
$clienteEmail = $cliente ? (string)$cliente['email'] : '';
$ultimoProgramma = $programmiAssegnati[0] ?? null;
renderStart('Scheda Cliente', 'clienti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card premium-client-card" data-client-card<?= $compilazioneApertaMeta ? ' style="display:none"' : '' ?>>
  <style>
    :root{--max:1380px;}
    .premium-client-card {
      background:
        radial-gradient(1200px 400px at 10% -10%, rgba(34, 211, 238, .12), transparent 52%),
        radial-gradient(900px 360px at 92% -15%, rgba(79, 70, 229, .15), transparent 55%),
        #020617;
      border: 1px solid rgba(255, 255, 255, .1);
      border-radius: 24px;
      padding: clamp(16px, 2.6vw, 32px);
      box-shadow: 0 35px 80px rgba(2, 6, 23, .7);
    }
    .premium-grid{display:grid;gap:16px}
    .premium-header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}
    .premium-header-title h2{font-size:clamp(1.75rem,2.8vw,2.35rem);margin:8px 0 4px;line-height:1.1}
    .premium-header-title p{margin:0;color:#94a3b8;max-width:760px}
    .premium-pill{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:6px 12px;font-size:.72rem;font-weight:700;letter-spacing:.03em;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05)}
    .premium-pill.info{background:rgba(34,211,238,.16);color:#67e8f9;border-color:rgba(34,211,238,.35)}
    .premium-pill.success{background:rgba(16,185,129,.16);color:#6ee7b7;border-color:rgba(16,185,129,.3)}
    .premium-pill.warn{background:rgba(245,158,11,.16);color:#fcd34d;border-color:rgba(245,158,11,.3)}
    .premium-actions{display:flex;gap:10px;flex-wrap:wrap}
    .premium-btn{border-radius:12px;padding:10px 14px;border:1px solid rgba(255,255,255,.16);background:rgba(15,23,42,.8);color:#e2e8f0;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;font-size:.82rem;transition:.2s}
    .premium-btn:hover{border-color:rgba(34,211,238,.45);color:#fff;transform:translateY(-1px)}
    .premium-btn.primary{background:linear-gradient(90deg,#4f46e5,#06b6d4);border:none;color:#fff}
    .premium-btn.ghost-danger{border-color:rgba(244,63,94,.45);color:#fda4af;background:rgba(190,24,93,.08)}
    .premium-kpi-wrap{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px;margin-top:18px}
    .premium-kpi{grid-column:span 3;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:16px;box-shadow:inset 0 1px rgba(255,255,255,.06)}
    .premium-kpi strong{display:block;font-size:1.55rem;line-height:1.15;margin-top:8px;color:#f8fafc}
    .premium-kpi small{color:#64748b;font-size:.73rem}
    .premium-snapshot{grid-column:span 3;background:rgba(15,23,42,.75);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:16px}
    .premium-progress{height:7px;border-radius:999px;background:rgba(148,163,184,.25);overflow:hidden}
    .premium-progress > span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#22d3ee,#34d399)}
    .premium-main{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,380px);gap:14px;margin-top:18px;align-items:start}
    .premium-section{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.11);border-radius:18px;padding:16px;min-width:0;position:relative;z-index:0}
    .premium-section h3{margin:0;font-size:1.03rem}
    .premium-sub{margin:4px 0 0;color:#94a3b8;font-size:.8rem}
    .program-card{margin-top:12px;padding:14px;border-radius:14px;background:rgba(2,6,23,.62);border:1px solid rgba(255,255,255,.12)}
    .program-top{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center}
    .program-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
    .premium-table-wrap{overflow:auto;margin-top:12px}
    .premium-table{width:100%;border-collapse:separate;border-spacing:0;font-size:.84rem}
    .premium-table th,.premium-table td{padding:11px 10px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left}
    .premium-table th{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8}
    .premium-table tr:last-child td{border-bottom:none}
    .compact-list{display:grid;gap:10px;margin-top:12px}
    .compact-item{padding:12px;border-radius:12px;background:rgba(2,6,23,.55);border:1px solid rgba(255,255,255,.1)}
    .quick-actions .premium-btn{width:100%;justify-content:flex-start}
    .quick-actions .premium-btn.primary{justify-content:center}
    .premium-main > div{min-width:0}
    @media (max-width:1280px){
      .premium-kpi{grid-column:span 4}
      .premium-snapshot{grid-column:span 12}
      .premium-main{grid-template-columns:1fr}
    }
    @media (max-width:768px){
      .premium-kpi{grid-column:span 12}
      .premium-header-title h2{font-size:1.45rem}
      .premium-actions{width:100%}
      .premium-actions .premium-btn{flex:1}
    }


    .responses-card { margin-top: 16px; display: none; }
    .responses-card.open { display: block; }
    .responses-list { display: grid; gap: 10px; margin-top: 10px; }
    .responses-item { border: 1px solid rgba(255, 255, 255, .08); border-radius: 10px; padding: 10px 12px; background: rgba(255, 255, 255, .02); }
    .responses-item-question { font-weight: 600; margin: 0 0 6px; }
    .responses-item-answer { margin: 0; color: #d5dde8; white-space: pre-wrap; word-break: break-word; }
    .remove-program-overlay {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
      background: rgba(0, 0, 0, .6);
      z-index: 1200;
      opacity: 0;
      transition: opacity .2s ease;
    }
    .remove-program-overlay.open { display:flex; opacity:1; }
    .remove-program-modal {
      width:min(520px,100%);border-radius:14px;background:#1a2026;border:1px solid rgba(255,255,255,.1);box-shadow:0 22px 55px rgba(0,0,0,.45);padding:18px;color:#eef3f7;transform:scale(.96);transition:transform .2s ease;
    }
    .remove-program-overlay.open .remove-program-modal { transform: scale(1); }
    .remove-program-meta {margin-top:12px;padding:10px;border-radius:10px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);}
    .remove-program-actions {margin-top:14px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;}
  </style>

  <div class="premium-header">
    <div class="premium-header-title">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <a class="premium-btn" href="clienti.php">← Indietro</a>
        <span class="premium-pill info">Scheda cliente</span>
      </div>
      <h2><?= h($clienteNome) ?></h2>
      <p>Vista completa del cliente con dati anagrafici, programmi attivi, questionari, compilazioni e storico associazioni in un'unica interfaccia più pulita e leggibile.</p>
    </div>
    <?php if ($cliente): ?>
      <div class="premium-actions">
        <button class="premium-btn" type="button" data-toggle-contact>Nascondi mail contatto</button>
        <button class="premium-btn" type="button" data-toggle-physical>Nascondi dati fisici</button>
      </div>
    <?php endif; ?>
  </div>

  <?php foreach ($errors as $error): ?>
    <div class="alert" style="margin-top:12px"><?= h($error) ?></div>
  <?php endforeach; ?>

  <?php if ($cliente): ?>
    <div class="premium-kpi-wrap">
      <article class="premium-kpi" data-contact-mail>
        <small>Email contatto</small>
        <strong style="font-size:1.35rem"><?= h($clienteEmail ?: '—') ?></strong>
        <small>Contatto principale del cliente</small>
      </article>
      <article class="premium-kpi" data-physical-line>
        <small>Età / Altezza</small>
        <strong><?= h(isset($cliente['eta']) ? (string)$cliente['eta'] : '—') ?> · <?= h(isset($cliente['altezzaCm']) ? (string)$cliente['altezzaCm'] . ' cm' : '—') ?></strong>
        <small>Profilo fisico attuale</small>
      </article>
      <article class="premium-kpi">
        <small>Peso</small>
        <strong><?= h(isset($cliente['pesoKg']) ? (string)$cliente['pesoKg'] . ' kg' : '—') ?></strong>
        <small>Ultimo valore registrato</small>
      </article>
      <?php
        $totProgrammi = count($programmiAssegnati);
        $programmiAttivi = count(array_filter($programmiAssegnati, static function ($p) { return in_array((string)$p['stato'], ['attivo', 'attiva'], true); }));
        $progRate = $totProgrammi > 0 ? (int)round(($programmiAttivi / $totProgrammi) * 100) : 0;
        $totComp = count($questionariCompilazioni);
        $compInviate = count(array_filter($questionariCompilazioni, static function ($q) { return ((string)$q['stato']) === 'inviato'; }));
        $compRate = $totComp > 0 ? (int)round(($compInviate / $totComp) * 100) : 0;
      ?>
      <article class="premium-snapshot">
        <div style="display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0">Snapshot cliente</h3><strong style="font-size:.9rem"><?= $progRate ?>%</strong></div>
        <p class="premium-sub">Aderenza programmi</p>
        <div class="premium-progress"><span style="width:<?= $progRate ?>%"></span></div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px"><p class="premium-sub" style="margin:0">Compilazioni questionari</p><strong style="font-size:.9rem"><?= $compInviate ?> inviate</strong></div>
        <div class="premium-progress"><span style="width:<?= $compRate ?>%"></span></div>
        <div style="margin-top:12px;padding:10px;border-radius:10px;background:rgba(15,23,42,.55);border:1px solid rgba(255,255,255,.1);font-size:.8rem;color:#cbd5e1">Cliente <?= $programmiAttivi > 0 ? 'attivo con programma in corso.' : 'senza programmi attivi.' ?> <?= $totComp > 0 ? 'Storico questionari disponibile.' : 'Nessuna compilazione al momento.' ?></div>
      </article>
    </div>

    <div class="premium-main">
      <div class="premium-grid">
        <section class="premium-section">
          <div class="premium-header" style="align-items:center">
            <div>
              <h3>Programmi di allenamento assegnati</h3>
              <p class="premium-sub">Programmi attualmente disponibili per il cliente e relativo stato di avanzamento</p>
            </div>
            <?php if (!$ultimoProgramma): ?><a class="premium-btn primary" href="allenamenti.php">+ Assegna programma</a><?php endif; ?>
          </div>
          <?php if (!$programmiAssegnati): ?>
            <p class="muted" style="margin-top:12px">Nessun programma assegnato.</p>
          <?php endif; ?>
          <?php foreach ($programmiAssegnati as $programma): ?>
            <?php $isActive = in_array((string)$programma['stato'], ['attivo','attiva'], true); $mockProgress = $isActive ? 76 : 24; ?>
            <article class="program-card">
              <div class="program-top">
                <div>
                  <h4 style="margin:0 0 4px"><?= h((string)$programma['titolo']) ?></h4>
                  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <span class="premium-pill <?= $isActive ? 'success' : 'warn' ?>"><?= h((string)$programma['stato']) ?></span>
                    <small style="color:#94a3b8">Assegnato il <?= h((string)$programma['assegnatoIl']) ?></small>
                  </div>
                </div>
              </div>
              <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center"><small style="color:#94a3b8">Progressi visualizzati</small><strong style="font-size:.8rem"><?= $mockProgress ?>%</strong></div>
              <div class="premium-progress"><span style="width:<?= $mockProgress ?>%"></span></div>
              <div class="program-actions">
                <a class="premium-btn" href="programma.php?id=<?= (int)$programma['idProgramma'] ?>">Apri programma</a>
                <a class="premium-btn" href="progressi_programma.php?idCliente=<?= (int)$idCliente ?>&idProgramma=<?= (int)$programma['idProgramma'] ?>">Visualizza progressi</a>
                <?php if ($isActive): ?>
                  <button class="premium-btn ghost-danger" type="button" data-remove-program data-id-cliente="<?= (int)$idCliente ?>" data-id-programma="<?= (int)$programma['idProgramma'] ?>" data-titolo="<?= h((string)$programma['titolo']) ?>" data-assegnato-il="<?= h((string)$programma['assegnatoIl']) ?>">Rimuovi</button>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </section>

        <section class="premium-section">
          <div class="premium-header" style="align-items:center">
            <div>
              <h3>Compilazioni questionari</h3>
              <p class="premium-sub">Storico invii del cliente con accesso rapido alle risposte</p>
            </div>
            <button class="premium-btn" type="button">Esporta storico</button>
          </div>
          <div class="premium-table-wrap">
            <table class="premium-table">
              <thead><tr><th>Questionario</th><th>#</th><th>Stato</th><th>Inviato il</th><th>Data ricompilazione</th><th>Apri</th></tr></thead>
              <tbody>
              <?php if (!$questionariCompilazioni): ?><tr><td colspan="6" class="muted">Nessuna compilazione disponibile.</td></tr><?php endif; ?>
              <?php foreach ($questionariCompilazioni as $qc): ?>
                <tr>
                  <td><?= h($qc['titolo']) ?></td>
                  <td><?= (int)$qc['numeroCompilazione'] ?></td>
                  <td><span class="premium-pill info"><?= h($qc['stato']) ?></span></td>
                  <td><?= h($qc['inviatoIl'] ?: '—') ?></td>
                  <td><?= h($qc['ricompilazioneDi'] ? $qc['aggiornatoIl'] : '—') ?></td>
                  <td><a class="premium-btn" href="scheda_cliente.php?idCliente=<?= (int)$idCliente ?>&idCompilazione=<?= (int)$qc['idCompilazione'] ?>#visualizzazione-risposte" data-open-responses data-id-compilazione="<?= (int)$qc['idCompilazione'] ?>" data-questionario="<?= h((string)$qc['titolo']) ?>" data-numero="<?= (int)$qc['numeroCompilazione'] ?>" data-inviato-il="<?= h((string)($qc['inviatoIl'] ?: '—')) ?>">Apri risposte</a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <div class="premium-grid">
        <section class="premium-section">
          <div class="premium-header" style="align-items:center">
            <div><h3>Questionari assegnati</h3><p class="premium-sub">Questionari attivi e stato dell'assegnazione</p></div>
            <button class="premium-btn primary" type="button">Nuovo questionario</button>
          </div>
          <div class="compact-list">
            <?php if (!$questionariAssegnati): ?><p class="muted">Nessun questionario assegnato.</p><?php endif; ?>
            <?php foreach ($questionariAssegnati as $qa): ?>
              <article class="compact-item">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:center"><strong><?= h($qa['titolo']) ?></strong><span class="premium-pill <?= ((string)$qa['stato']==='attivo' ? 'success':'info') ?>"><?= h($qa['stato']) ?></span></div>
                <p class="premium-sub" style="margin-top:8px">Assegnato il <?= h($qa['assegnatoIl']) ?></p>
                <p class="premium-sub" style="margin:4px 0 0">Disattivato il <?= h($qa['disattivatoIl'] ?: '—') ?></p>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="premium-section">
          <div class="premium-header" style="align-items:center"><div><h3>Storico associazioni</h3><p class="premium-sub">Relazione professionale e stato corrente del collegamento</p></div><button class="premium-btn" type="button" data-toggle-associazioni>&gt; Storico associazioni con questo cliente</button></div>
          <div class="compact-list" data-associazioni-container style="display:none">
            <?php foreach ($storicoAssociazioni as $associazione): ?>
              <article class="compact-item">
                <small class="premium-sub" style="display:block"><?= strtoupper(h((string)$associazione['tipoAssociazione'])) ?></small>
                <p style="margin:8px 0 0"><strong>Inizio</strong> <?= h((string)$associazione['iniziataIl']) ?></p>
                <p style="margin:4px 0 0"><strong>Fine</strong> <?= h((string)$associazione['terminataIl'] ?: '—') ?></p>
                <span class="premium-pill <?= ((int)$associazione['attivaFlag']===1 ? 'success' : 'warn') ?>" style="margin-top:8px"><?= ((int)$associazione['attivaFlag']===1 ? 'Attiva' : 'Terminata') ?></span>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="premium-section quick-actions">
          <h3>Azioni rapide</h3>
          <p class="premium-sub">Comandi frequenti per lavorare sul profilo cliente</p>
          <div class="premium-grid" style="margin-top:12px">
            <a class="premium-btn" href="supporto.php?idCliente=<?= (int)$idCliente ?>">Apri chat cliente</a>
            <a class="premium-btn" href="allenamenti.php?idCliente=<?= (int)$idCliente ?>">Assegna nuovo programma</a>
            <a class="premium-btn primary" href="overview.php?idCliente=<?= (int)$idCliente ?>">Apri dashboard completa</a>
          </div>
        </section>
      </div>
    </div>
  <?php endif; ?>
</section>
<?php if ($cliente): ?>
  <section
    id="visualizzazione-risposte"
    class="card responses-card<?= $compilazioneApertaMeta ? ' open' : '' ?>"
    data-responses-card
    aria-hidden="<?= $compilazioneApertaMeta ? 'false' : 'true' ?>"
  >
    <div class="toolbar" style="justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <a class="btn" href="scheda_cliente.php?idCliente=<?= (int)$idCliente ?>" data-back-to-client>← Torna alla scheda cliente</a>
      <h3 style="margin:0" data-responses-title>Questionario: <?= h((string)($compilazioneApertaMeta['titolo'] ?? '—')) ?></h3>
    </div>
    <div class="divider" style="margin:14px 0"></div>
    <p class="muted" style="margin:8px 0 0" data-responses-meta>
      <?php if ($compilazioneApertaMeta): ?>
        Questionario: <?= h((string)$compilazioneApertaMeta['titolo']) ?> ·
        Compilazione #<?= (int)$compilazioneApertaMeta['numeroCompilazione'] ?> ·
        Inviato il: <?= h((string)($compilazioneApertaMeta['inviatoIl'] ?: '—')) ?>
      <?php else: ?>
        Seleziona una compilazione per visualizzare le risposte.
      <?php endif; ?>
    </p>
    <p class="muted" style="margin:8px 0 0;display:none" data-responses-feedback></p>
    <div class="responses-list" data-responses-list>
      <?php foreach ($compilazioneApertaRisposte as $answer): ?>
        <?php
          $renderedValue = '—';
          if ($answer['valoreTesto'] !== null && $answer['valoreTesto'] !== '') {
            $renderedValue = (string)$answer['valoreTesto'];
          } elseif ($answer['valoreNumero'] !== null) {
            $renderedValue = (string)$answer['valoreNumero'];
          } elseif ($answer['valoreData'] !== null && $answer['valoreData'] !== '') {
            $renderedValue = (string)$answer['valoreData'];
          } elseif ($answer['valoreBoolean'] !== null) {
            $renderedValue = ((int)$answer['valoreBoolean'] === 1) ? 'Sì' : 'No';
          } elseif ($answer['valoreJson'] !== null && $answer['valoreJson'] !== '') {
            $decoded = json_decode((string)$answer['valoreJson'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
              $renderedValue = implode(', ', array_map('strval', array_values($decoded)));
            } else {
              $renderedValue = (string)$answer['valoreJson'];
            }
          }
        ?>
        <div class="responses-item">
          <p class="responses-item-question"><?= h((string)($answer['testoDomanda'] ?: 'Domanda')) ?></p>
          <p class="responses-item-answer"><?= h($renderedValue) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<div id="removeProgramOverlay" class="remove-program-overlay" aria-hidden="true">
  <div id="removeProgramModal" class="remove-program-modal" role="dialog" aria-modal="true" aria-labelledby="removeProgramTitle">
    <div class="toolbar" style="justify-content:space-between;align-items:center;gap:12px">
      <h3 id="removeProgramTitle" style="margin:0">Conferma rimozione</h3>
      <button class="btn" type="button" data-remove-close aria-label="Chiudi">✕</button>
    </div>
    <p style="margin:10px 0 0">Vuoi rimuovere questo programma dal cliente? Il cliente non lo vedrà più in dashboard.</p>
    <div class="remove-program-meta">
      <p style="margin:0"><strong>Programma:</strong> <span data-remove-program-title>—</span></p>
      <p style="margin:8px 0 0"><strong>Assegnato il:</strong> <span data-remove-program-date>—</span></p>
    </div>
    <p class="muted" data-remove-feedback style="margin:10px 0 0"></p>
    <div class="remove-program-actions">
      <button class="btn" type="button" data-remove-cancel>Annulla</button>
      <button class="btn primary" type="button" data-remove-confirm>Conferma rimozione</button>
    </div>
  </div>
</div>
<?php
renderEnd(<<<'HTML'
<script>
(function(){
  const contactBtn = document.querySelector('[data-toggle-contact]');
  const contactMail = document.querySelector('[data-contact-mail]');
  if (contactBtn && contactMail) {
    contactBtn.addEventListener('click', function(){
      const isHidden = contactMail.style.display === 'none';
      contactMail.style.display = isHidden ? 'block' : 'none';
      contactBtn.textContent = isHidden ? 'Nascondi mail contatto' : 'Mostra mail contatto';
    });
  }

  const physicalBtn = document.querySelector('[data-toggle-physical]');
  const physicalLine = document.querySelector('[data-physical-line]');
  if (physicalBtn && physicalLine) {
    physicalBtn.addEventListener('click', function(){
      const hidden = physicalLine.style.display === 'none';
      physicalLine.style.display = hidden ? 'block' : 'none';
      physicalBtn.textContent = hidden ? 'Nascondi dati fisici' : 'Mostra dati fisici';
    });
  }

  const assocBtn = document.querySelector('[data-toggle-associazioni]');
  const assocContainer = document.querySelector('[data-associazioni-container]');
  if (assocBtn && assocContainer) {
    assocBtn.addEventListener('click', function(){
      const hidden = assocContainer.style.display === 'none';
      assocContainer.style.display = hidden ? 'block' : 'none';
      assocBtn.textContent = hidden ? '˅ Nascondi storico associazioni' : '> Storico associazioni con questo cliente';
    });
  }

  const clientCard = document.querySelector('[data-client-card]');
  const responsesCard = document.querySelector('[data-responses-card]');
  const responsesTitle = document.querySelector('[data-responses-title]');
  const responsesMeta = document.querySelector('[data-responses-meta]');
  const responsesFeedback = document.querySelector('[data-responses-feedback]');
  const responsesList = document.querySelector('[data-responses-list]');
  const backToClientBtn = document.querySelector('[data-back-to-client]');

  function formatAnswer(answer) {
    if (answer.valoreTesto !== null && answer.valoreTesto !== '') {
      return String(answer.valoreTesto);
    }
    if (answer.valoreNumero !== null) {
      return String(answer.valoreNumero);
    }
    if (answer.valoreData !== null && answer.valoreData !== '') {
      return String(answer.valoreData);
    }
    if (answer.valoreBoolean !== null) {
      return Number(answer.valoreBoolean) === 1 ? 'Sì' : 'No';
    }
    if (answer.valoreJson !== null && answer.valoreJson !== '') {
      try {
        const parsed = JSON.parse(answer.valoreJson);
        if (Array.isArray(parsed)) {
          return parsed.join(', ');
        }
        if (typeof parsed === 'object' && parsed) {
          return Object.values(parsed).join(', ');
        }
      } catch (error) {
        return String(answer.valoreJson);
      }
      return String(answer.valoreJson);
    }
    return '—';
  }

  function clearResponses() {
    if (responsesList) {
      responsesList.innerHTML = '';
    }
  }

  function openResponsesCard() {
    if (!responsesCard) {
      return;
    }
    responsesCard.classList.add('open');
    responsesCard.setAttribute('aria-hidden', 'false');
    if (clientCard) {
      clientCard.style.display = 'none';
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function closeResponsesCard() {
    if (!responsesCard) {
      return;
    }
    responsesCard.classList.remove('open');
    responsesCard.setAttribute('aria-hidden', 'true');
    clearResponses();
    if (clientCard) {
      clientCard.style.display = 'block';
    }
    if (responsesFeedback) {
      responsesFeedback.style.display = 'none';
      responsesFeedback.textContent = '';
    }
    if (responsesMeta) {
      responsesMeta.textContent = 'Seleziona una compilazione per visualizzare le risposte.';
    }
    if (responsesTitle) {
      responsesTitle.textContent = 'Questionario: —';
    }
  }

  backToClientBtn?.addEventListener('click', closeResponsesCard);

  document.querySelectorAll('[data-open-responses]').forEach(function(btn){
    btn.addEventListener('click', async function(){
      const idCompilazione = Number(btn.dataset.idCompilazione || 0);
      if (!idCompilazione || !responsesCard || !responsesList) {
        return;
      }

      openResponsesCard();
      clearResponses();

      if (responsesMeta) {
        responsesMeta.textContent = 'Questionario: ' + (btn.dataset.questionario || '—') + ' · Compilazione #' + (btn.dataset.numero || '—') + ' · Inviato il: ' + (btn.dataset.inviatoIl || '—');
      }
      if (responsesTitle) {
        responsesTitle.textContent = 'Questionario: ' + (btn.dataset.questionario || '—');
      }
      if (responsesFeedback) {
        responsesFeedback.style.display = 'block';
        responsesFeedback.textContent = 'Caricamento risposte...';
      }

      try {
        const response = await fetch('../api/questionari/compilazione_detail.php?idCompilazione=' + idCompilazione, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const responseData = await response.json();
        if (!response.ok || !responseData.ok) {
          throw new Error((responseData && responseData.error) || 'Impossibile caricare le risposte.');
        }

        (responseData.risposte || []).forEach(function(answer){
          const item = document.createElement('div');
          item.className = 'responses-item';

          const question = document.createElement('p');
          question.className = 'responses-item-question';
          question.textContent = answer.testoDomanda || 'Domanda';

          const value = document.createElement('p');
          value.className = 'responses-item-answer';
          value.textContent = formatAnswer(answer);

          item.appendChild(question);
          item.appendChild(value);
          responsesList.appendChild(item);
        });

        if (responsesFeedback) {
          responsesFeedback.style.display = 'none';
          responsesFeedback.textContent = '';
        }
      } catch (error) {
        if (responsesFeedback) {
          responsesFeedback.style.display = 'block';
          responsesFeedback.textContent = error.message || 'Errore durante il caricamento delle risposte.';
        }
      }
    });
  });

  const overlay = document.getElementById('removeProgramOverlay');
  const modal = document.getElementById('removeProgramModal');
  const titleEl = document.querySelector('[data-remove-program-title]');
  const dateEl = document.querySelector('[data-remove-program-date]');
  const feedbackEl = document.querySelector('[data-remove-feedback]');
  const closeEls = document.querySelectorAll('[data-remove-close],[data-remove-cancel]');
  const confirmBtn = document.querySelector('[data-remove-confirm]');

  let currentBtn = null;
  let payload = null;

  function closeModal() {
    if (!overlay) {
      return;
    }
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (feedbackEl) {
      feedbackEl.textContent = '';
    }
    currentBtn = null;
    payload = null;
    if (confirmBtn) {
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'Conferma rimozione';
    }
  }

  function openModal(btn) {
    if (!overlay || !modal) {
      return;
    }
    currentBtn = btn;
    payload = {
      idCliente: Number(btn.dataset.idCliente || 0),
      idProgramma: Number(btn.dataset.idProgramma || 0)
    };
    if (titleEl) {
      titleEl.textContent = btn.dataset.titolo || '—';
    }
    if (dateEl) {
      dateEl.textContent = btn.dataset.assegnatoIl || '—';
    }
    if (feedbackEl) {
      feedbackEl.textContent = '';
    }
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  document.querySelectorAll('[data-remove-program]').forEach(function(btn){
    btn.addEventListener('click', function(){
      openModal(btn);
    });
  });

  closeEls.forEach(function(el){
    el.addEventListener('click', closeModal);
  });

  overlay?.addEventListener('click', function(event){
    if (event.target === overlay) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function(event){
    if (event.key === 'Escape' && overlay && overlay.classList.contains('open')) {
      closeModal();
    }
  });

  confirmBtn?.addEventListener('click', async function(){
    if (!currentBtn || !payload) {
      return;
    }

    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Rimozione in corso...';
    closeEls.forEach(function(el){
      el.disabled = true;
    });

    if (feedbackEl) {
      feedbackEl.textContent = 'Rimozione in corso...';
    }

    try {
      const response = await fetch('api/rimuovi_programma_cliente.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      });
      const text = await response.text();
      let data = null;
      try {
        data = JSON.parse(text);
      } catch (parseError) {
        throw new Error('Risposta non valida dal server.');
      }

      if (!response.ok || !data.ok) {
        throw new Error((data && data.error) || 'Rimozione non riuscita.');
      }

      const row = currentBtn.closest('tr');
      if (row) {
        row.remove();
      }
      if (feedbackEl) {
        feedbackEl.textContent = 'Programma rimosso correttamente.';
      }
      setTimeout(closeModal, 500);
    } catch (error) {
      if (feedbackEl) {
        feedbackEl.textContent = error.message || 'Errore durante la rimozione.';
      }
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'Conferma rimozione';
    } finally {
      closeEls.forEach(function(el){
        el.disabled = false;
      });
    }
  });
})();
</script>
HTML
);
