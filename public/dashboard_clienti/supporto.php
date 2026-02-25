<?php
require __DIR__ . '/common.php';

renderStart('Supporto cliente', 'supporto', $email);
?>
<section class="card hero">
  <span class="pill">Supporto</span>
  <h1>Comunicazione con il team</h1>
  <p class="lead">Canale centralizzato per richieste a coach e nutrizionista, in linea con la UX della dashboard professionista.</p>
</section>

<section class="grid">
  <article class="card span-6">
    <h3 class="section-title">Contatti assegnati</h3>
    <ul class="list">
      <li><strong>Coach:</strong> <?= h($coachAssegnato) ?></li>
      <li><strong>Nutrizionista:</strong> <?= h($nutrizionistaAssegnato) ?></li>
      <li><strong>Tempo medio risposta:</strong> entro 24 ore lavorative</li>
    </ul>
  </article>

  <article class="card span-6">
    <h3 class="section-title">Invia richiesta</h3>
    <form>
      <div class="field"><label for="topic">Argomento</label><select id="topic"><option>Allenamento</option><option>Nutrizione</option><option>Check-in</option></select></div>
      <div class="field" style="margin-top:8px"><label for="msg">Messaggio</label><textarea id="msg" rows="4" placeholder="Scrivi qui la tua richiesta..."></textarea></div>
      <button class="btn primary" type="button" style="margin-top:10px">Invia (UI demo)</button>
    </form>
  </article>
</section>
<?php
renderEnd();
