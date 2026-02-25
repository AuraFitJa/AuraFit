<?php
require __DIR__ . '/common.php';

renderStart('Allenamenti', 'allenamenti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <h2 class="section-title">Allenamenti (solo PT) — RF-005</h2>
  <?php if (!$isPt): ?>
    <p class="muted">Questa sezione è disponibile solo per il ruolo Personal Trainer.</p>
  <?php else: ?>
    <div class="two">
      <div class="field"><label>Nome programma</label><input type="text" value="Strength Base - 8 settimane" /></div>
      <div class="field"><label>Assegna a cliente associato</label><select><option>Giulia Rinaldi</option><option>Marco Testa</option></select></div>
    </div>

    <div class="divider"></div>

    <h3>Editor esercizi dinamico</h3>
    <div class="three">
      <div class="field"><label>Nome esercizio</label><input type="text" value="Squat bilanciere" /></div>
      <div class="field"><label>Serie</label><input type="number" value="4" /></div>
      <div class="field"><label>Ripetizioni</label><input type="text" value="6-8" /></div>
      <div class="field"><label>Carico</label><input type="text" value="80kg" /></div>
      <div class="field"><label>Recupero</label><input type="text" value="120s" /></div>
      <div class="field"><label>Media upload</label><input type="text" placeholder="Link video o media" /></div>
    </div>
    <div class="field" style="margin-top:10px"><label>Note</label><textarea rows="3">Controllare profondità e mantenere core attivo.</textarea></div>

    <div class="toolbar" style="margin-top:12px">
      <button class="btn primary">Salva su DB</button>
      <button class="btn">Aggiungi esercizio</button>
      <button class="btn">Duplica programma</button>
    </div>
  <?php endif; ?>
</section>
<?php
renderEnd();
