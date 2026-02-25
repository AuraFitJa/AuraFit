<?php
require __DIR__ . '/common.php';

renderStart('Nutrizione', 'nutrizione', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<section class="card">
  <h2 class="section-title">Nutrizione (solo Nutrizionista) — RF-006</h2>
  <?php if (!$isNutrizionista): ?>
    <p class="muted">Questa sezione è disponibile solo per il ruolo Nutrizionista.</p>
  <?php else: ?>
    <div class="two">
      <div class="field"><label>Nome piano alimentare</label><input type="text" value="Recomposition Primavera" /></div>
      <div class="field"><label>Cliente associato</label><select><option>Silvia Martini</option><option>Giulia Rinaldi</option></select></div>
    </div>

    <div class="divider"></div>

    <h3>Inserimento pasti e alimenti</h3>
    <div class="three">
      <div class="field"><label>Pasto</label><input type="text" value="Colazione" /></div>
      <div class="field"><label>Alimento</label><input type="text" value="Yogurt greco 0%" /></div>
      <div class="field"><label>Quantità (g)</label><input type="number" value="200" /></div>
      <div class="field"><label>Proteine</label><input type="number" value="20" /></div>
      <div class="field"><label>Carboidrati</label><input type="number" value="8" /></div>
      <div class="field"><label>Grassi</label><input type="number" value="2" /></div>
    </div>

    <div class="toolbar" style="margin-top:12px">
      <button class="btn primary">Salva piano</button>
      <button class="btn">Aggiungi pasto</button>
      <button class="btn">Visualizza diario cliente</button>
      <button class="btn">Modifica piano</button>
    </div>
  <?php endif; ?>
</section>
<?php
renderEnd();
