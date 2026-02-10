<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>AuraFit — Allenamento, nutrizione e coaching in un’unica piattaforma</title>
  <meta name="description" content="AuraFit è la piattaforma per Personal Trainer, Nutrizionisti e Clienti: allenamenti, nutrizione con AI, chat e report. Sicura e GDPR-ready." />
  <meta property="og:title" content="AuraFit — Allenamento, nutrizione e coaching in un’unica piattaforma" />
  <meta property="og:description" content="Programmi di allenamento, diario alimentare con AI, chat e monitoraggio progressi. Per PT, Nutrizionisti e Clienti." />
  <meta property="og:type" content="website" />
  <meta name="theme-color" content="#0B0F19" />

  <style>
    :root{
      --bg:#070A12;
      --card: rgba(255,255,255,.06);
      --card2: rgba(255,255,255,.09);
      --text:#EAF0FF;
      --muted: rgba(234,240,255,.68);
      --line: rgba(234,240,255,.12);
      --brand1:#6D5EF3;
      --brand2:#2EE1A5;
      --brand3:#4CC9F0;
      --shadow: 0 18px 60px rgba(0,0,0,.45);
      --radius: 18px;
      --radius2: 24px;
      --max: 1160px;
      --mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace;
      --sans: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family: var(--sans);
      color: var(--text);
      background:
        radial-gradient(1200px 800px at 20% -10%, rgba(109,94,243,.35), transparent 55%),
        radial-gradient(1100px 700px at 90% 10%, rgba(46,225,165,.22), transparent 55%),
        radial-gradient(900px 700px at 55% 95%, rgba(76,201,240,.18), transparent 55%),
        var(--bg);
      overflow-x:hidden;
    }
    a{color:inherit; text-decoration:none}
    .container{width:100%; max-width: var(--max); margin:0 auto; padding:0 18px}
    .pill{
      display:inline-flex; align-items:center; gap:10px;
      padding:10px 12px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.04);
      border-radius: 999px;
      color: var(--muted);
      font-size: 13px;
      letter-spacing:.2px;
      backdrop-filter: blur(10px);
    }
    .pill b{color:var(--text)}
    .topbar{
      position: sticky; top:0; z-index:50;
      background: rgba(7,10,18,.55);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--line);
    }
    .nav{
      display:flex; align-items:center; justify-content:space-between;
      height:72px;
    }
    .brand{
      display:flex; align-items:center; gap:10px;
      font-weight: 700;
      letter-spacing:.2px;
    }
    .logo{
      width:34px;height:34px;border-radius: 10px;
      background: linear-gradient(135deg, var(--brand1), var(--brand2));
      box-shadow: 0 10px 30px rgba(109,94,243,.18);
      position:relative;
      overflow:hidden;
    }
    .logo:after{
      content:""; position:absolute; inset:-30%;
      background: radial-gradient(circle at 30% 30%, rgba(255,255,255,.35), transparent 55%);
      transform: rotate(20deg);
    }
    .navlinks{display:flex; align-items:center; gap:16px}
    .navlinks a{
      color: var(--muted);
      padding:10px 10px;
      border-radius: 12px;
      transition: background .2s ease, color .2s ease;
      font-size: 14px;
    }
    .navlinks a:hover{background: rgba(255,255,255,.06); color: var(--text)}
    .actions{display:flex; align-items:center; gap:10px}
    .btn{
      display:inline-flex; align-items:center; justify-content:center; gap:10px;
      padding:12px 14px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.04);
      color: var(--text);
      font-weight: 650;
      font-size: 14px;
      cursor:pointer;
      transition: transform .15s ease, background .2s ease, border-color .2s ease;
      white-space:nowrap;
    }
    .btn:hover{background: rgba(255,255,255,.07)}
    .btn:active{transform: translateY(1px)}
    .btn.primary{
      border-color: rgba(109,94,243,.55);
      background: linear-gradient(135deg, rgba(109,94,243,.9), rgba(46,225,165,.75));
      color: #061018;
      box-shadow: 0 18px 50px rgba(109,94,243,.18);
    }
    .btn.primary:hover{filter:saturate(1.06)}
    .btn.ghost{
      background: transparent;
    }
    .menuBtn{
      display:none;
      width:42px;height:42px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.04);
      color: var(--text);
      cursor:pointer;
    }

    /* Hero */
    .hero{
      padding: 64px 0 26px;
    }
    .heroGrid{
      display:grid;
      grid-template-columns: 1.1fr .9fr;
      gap: 22px;
      align-items: start;
    }
    h1{
      margin: 18px 0 10px;
      font-size: clamp(34px, 4.6vw, 58px);
      line-height: 1.02;
      letter-spacing: -0.02em;
    }
    .lead{
      margin: 0 0 18px;
      color: var(--muted);
      font-size: clamp(15px, 1.25vw, 18px);
      line-height: 1.55;
      max-width: 56ch;
    }
    .heroCtas{display:flex; flex-wrap:wrap; gap:10px; margin-top: 18px}
    .micro{
      margin-top: 14px;
      display:flex; flex-wrap:wrap; gap:10px;
      color: rgba(234,240,255,.62);
      font-size: 13px;
    }
    .micro span{
      border: 1px solid var(--line);
      padding: 8px 10px;
      border-radius: 999px;
      background: rgba(255,255,255,.03);
    }

    .panel{
      border: 1px solid var(--line);
      background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
      border-radius: var(--radius2);
      box-shadow: var(--shadow);
      overflow:hidden;
    }
    .panelTop{
      padding: 18px 18px 0;
      display:flex; align-items:center; justify-content:space-between;
      gap:14px;
    }
    .panelTitle{
      font-weight: 700;
      letter-spacing:.2px;
      display:flex; align-items:center; gap:10px;
    }
    .dot{
      width:10px;height:10px;border-radius:99px;
      background: var(--brand2);
      box-shadow: 0 0 0 6px rgba(46,225,165,.12);
    }
    .panelBody{padding: 16px 18px 18px}
    .mock{
      border: 1px solid rgba(234,240,255,.10);
      border-radius: 16px;
      background: rgba(7,10,18,.45);
      overflow:hidden;
    }
    .mockHeader{
      display:flex; align-items:center; gap:8px;
      padding: 12px 14px;
      border-bottom: 1px solid rgba(234,240,255,.10);
      color: rgba(234,240,255,.65);
      font-size: 12px;
    }
    .bubble{width:10px;height:10px;border-radius:99px; background: rgba(234,240,255,.22)}
    .mockGrid{
      padding: 14px;
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .tile{
      border: 1px solid rgba(234,240,255,.10);
      background: rgba(255,255,255,.04);
      border-radius: 16px;
      padding: 14px;
      min-height: 110px;
      position:relative;
      overflow:hidden;
    }
    .tile:after{
      content:"";
      position:absolute; inset:-40%;
      background: radial-gradient(circle at 30% 30%, rgba(109,94,243,.25), transparent 55%);
      transform: rotate(18deg);
      pointer-events:none;
    }
    .kpi{
      font-family: var(--mono);
      font-size: 12px;
      color: rgba(234,240,255,.70);
      margin-bottom: 8px;
      position:relative;
      z-index:1;
    }
    .big{
      font-size: 24px;
      font-weight: 800;
      letter-spacing: -0.02em;
      position:relative;
      z-index:1;
    }
    .small{
      margin-top: 6px;
      color: rgba(234,240,255,.70);
      font-size: 13px;
      position:relative;
      z-index:1;
    }

    /* Sections */
    section{padding: 56px 0}
    .sectionHead{
      display:flex; align-items:flex-end; justify-content:space-between; gap:16px;
      margin-bottom: 18px;
    }
    .sectionHead h2{
      margin:0;
      font-size: clamp(22px, 2.3vw, 34px);
      letter-spacing: -0.01em;
    }
    .sectionHead p{
      margin:0;
      color: var(--muted);
      max-width: 62ch;
      line-height: 1.5;
      font-size: 14.5px;
    }

    .grid3{
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
    }
    .card{
      border: 1px solid var(--line);
      background: rgba(255,255,255,.04);
      border-radius: var(--radius);
      padding: 18px;
      box-shadow: 0 12px 40px rgba(0,0,0,.25);
    }
    .card .icon{
      width:42px;height:42px;border-radius: 14px;
      display:grid; place-items:center;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(234,240,255,.10);
      margin-bottom: 12px;
    }
    .card h3{margin:0 0 8px; font-size: 16px}
    .card p{margin:0; color: var(--muted); line-height: 1.6; font-size: 14px}

    .split{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
      align-items: stretch;
    }
    .list{
      margin: 0;
      padding-left: 18px;
      color: var(--muted);
      line-height: 1.75;
      font-size: 14px;
    }
    .callout{
      border: 1px solid rgba(109,94,243,.35);
      background: linear-gradient(135deg, rgba(109,94,243,.18), rgba(46,225,165,.10));
      border-radius: var(--radius2);
      padding: 18px;
      box-shadow: var(--shadow);
    }
    .callout h3{margin:0 0 6px}
    .callout p{margin:0; color: var(--muted); line-height:1.6}

    /* Pricing */
    .pricing{
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
    }
    .priceCard{
      border: 1px solid var(--line);
      background: rgba(255,255,255,.04);
      border-radius: var(--radius2);
      padding: 18px;
      position:relative;
      overflow:hidden;
    }
    .badge{
      display:inline-flex; align-items:center;
      font-size: 12px;
      color: rgba(6,16,24,.92);
      background: linear-gradient(135deg, rgba(109,94,243,.9), rgba(46,225,165,.75));
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 700;
    }
    .price{
      margin-top: 12px;
      font-size: 34px;
      font-weight: 900;
      letter-spacing: -0.02em;
    }
    .price small{font-size: 14px; color: rgba(234,240,255,.65); font-weight: 600}
    .hr{height:1px; background: var(--line); margin: 14px 0}
    .ul{
      list-style:none; padding:0; margin:0;
      display:grid; gap:10px;
      color: rgba(234,240,255,.72);
      font-size: 14px;
      line-height:1.35;
    }
    .ul li{display:flex; gap:10px}
    .check{
      width:18px;height:18px;border-radius: 6px;
      background: rgba(46,225,165,.18);
      border: 1px solid rgba(46,225,165,.45);
      display:grid; place-items:center;
      flex: 0 0 auto;
      margin-top: 1px;
    }

    /* FAQ */
    .faq{
      border: 1px solid var(--line);
      border-radius: var(--radius2);
      overflow:hidden;
      background: rgba(255,255,255,.03);
    }
    .faqItem{border-top: 1px solid var(--line)}
    .faqItem:first-child{border-top:0}
    .faqQ{
      width:100%;
      text-align:left;
      padding: 16px 16px;
      background: transparent;
      border:0;
      color: var(--text);
      display:flex; align-items:center; justify-content:space-between;
      font-weight: 700;
      font-size: 14.5px;
      cursor:pointer;
    }
    .faqA{
      padding: 0 16px 16px;
      color: var(--muted);
      line-height: 1.7;
      font-size: 14px;
      display:none;
    }
    .faqItem.open .faqA{display:block}
    .chev{opacity:.7}

    /* Footer */
    footer{
      padding: 34px 0 46px;
      border-top: 1px solid var(--line);
      color: rgba(234,240,255,.65);
    }
    .footGrid{
      display:grid;
      grid-template-columns: 1.2fr .8fr .8fr;
      gap: 14px;
      align-items:start;
    }
    .footGrid a{color: rgba(234,240,255,.72)}
    .footGrid a:hover{color: var(--text)}
    .fineprint{
      margin-top: 18px;
      font-size: 12.5px;
      color: rgba(234,240,255,.52);
      line-height: 1.6;
    }

    /* Mobile */
    @media (max-width: 980px){
      .heroGrid{grid-template-columns: 1fr}
      .panel{order:2}
      .grid3{grid-template-columns: 1fr}
      .split{grid-template-columns: 1fr}
      .pricing{grid-template-columns: 1fr}
      .footGrid{grid-template-columns: 1fr}
      .navlinks{display:none}
      .menuBtn{display:inline-grid; place-items:center}
      .mobileMenu{
        display:none;
        border-top: 1px solid var(--line);
        padding: 10px 0 14px;
      }
      .mobileMenu.open{display:block}
      .mobileMenu a{
        display:block;
        padding: 12px 10px;
        border-radius: 12px;
        color: var(--muted);
      }
      .mobileMenu a:hover{background: rgba(255,255,255,.05); color: var(--text)}
      .actions .btn.ghost{display:none}
    }
  </style>
</head>

<body>
  <header class="topbar">
    <div class="container">
      <nav class="nav" aria-label="Top navigation">
        <a class="brand" href="index.php">
          <span class="logo" aria-hidden="true"></span>
          <span>AuraFit</span>
        </a>

        <div class="navlinks" aria-label="Primary">
          <a href="#funzionalita">Funzionalità</a>
          <a href="#come-funziona">Come funziona</a>
          <a href="#sicurezza">Sicurezza</a>
          <a href="#piani">Piani</a>
          <a href="#faq">FAQ</a>
        </div>

        <div class="actions">
          <a class="btn ghost" href="login.php">Accedi</a>
          <a class="btn primary" href="register.php?tipo=cliente">Inizia gratis</a>
          <button class="menuBtn" id="menuBtn" aria-label="Apri menu">
            ☰
          </button>
        </div>
      </nav>

      <div class="mobileMenu" id="mobileMenu" aria-label="Mobile menu">
        <a href="#funzionalita">Funzionalità</a>
        <a href="#come-funziona">Come funziona</a>
        <a href="#sicurezza">Sicurezza</a>
        <a href="#piani">Piani</a>
        <a href="#faq">FAQ</a>
        <a href="login.php">Accedi</a>
      </div>
    </div>
  </header>

  <main>
    <!-- HERO -->
    <section class="hero">
      <div class="container">
        <span class="pill">
          <span aria-hidden="true">⚡</span>
          <b>Allenamento</b>, <b>Nutrizione</b> e <b>coaching</b> in un’unica piattaforma
        </span>

        <div class="heroGrid">
          <div>
            <h1>Il tuo hub per progressi misurabili.<br/>Per PT, Nutrizionisti e Clienti.</h1>
            <p class="lead">
              AuraFit unisce programmazione degli allenamenti, diario alimentare con supporto AI, chat e report.
              Semplifica la collaborazione professionista–cliente e rende il monitoraggio chiaro, continuo e condiviso.
            </p>

            <div class="heroCtas">
              <a class="btn primary" href="register.php?tipo=cliente">Inizia come Cliente</a>
              <a class="btn" href="register.php?tipo=professionista">Sei un PT / Nutrizionista?</a>
              <a class="btn ghost" href="#come-funziona">Guarda come funziona →</a>
            </div>

            <div class="micro" role="list">
              <span role="listitem">📈 Grafici e report progressi</span>
              <span role="listitem">💬 Chat e notifiche</span>
              <span role="listitem">🔐 Sicurezza & GDPR</span>
            </div>
          </div>

          <aside class="panel" aria-label="Preview dashboard">
            <div class="panelTop">
              <div class="panelTitle"><span class="dot"></span> Preview dashboard</div>
              <span class="pill"><span aria-hidden="true">●</span> Live</span>
            </div>
            <div class="panelBody">
              <div class="mock">
                <div class="mockHeader">
                  <span class="bubble"></span><span class="bubble"></span><span class="bubble"></span>
                  <span style="margin-left:10px;">aurafit / dashboard</span>
                </div>
                <div class="mockGrid">
                  <div class="tile">
                    <div class="kpi">Allenamenti · settimana</div>
                    <div class="big">4 / 4</div>
                    <div class="small">Diario compilato + feedback PT</div>
                  </div>
                  <div class="tile">
                    <div class="kpi">Macro · oggi</div>
                    <div class="big">P 132 · C 210 · F 62</div>
                    <div class="small">Stima AI modificabile</div>
                  </div>
                  <div class="tile">
                    <div class="kpi">Progressi · 30 giorni</div>
                    <div class="big">+7% volume</div>
                    <div class="small">Grafici + report automatici</div>
                  </div>
                  <div class="tile">
                    <div class="kpi">Chat</div>
                    <div class="big">2 nuovi messaggi</div>
                    <div class="small">PT ↔ Cliente · Nutri ↔ Cliente</div>
                  </div>
                </div>
              </div>

              <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                <a class="btn" href="login.php">Accedi</a>
                <a class="btn primary" href="register.php?tipo=cliente">Crea account</a>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </section>

    <!-- FEATURES -->
    <section id="funzionalita">
      <div class="container">
        <div class="sectionHead">
          <div>
            <h2>Tutto ciò che serve per seguire un percorso completo</h2>
            <p>
              Gestione utenti e ruoli, programmi di allenamento, nutrizione, comunicazione e monitoraggio:
              AuraFit è pensato per supportare il lavoro dei professionisti e la semplicità per i clienti.
            </p>
          </div>
        </div>

        <div class="grid3">
          <article class="card">
            <div class="icon">🏋️</div>
            <h3>Allenamenti</h3>
            <p>
              Crea e assegna programmi, gestisci esercizi, serie, ripetizioni, carichi e note. Il cliente compila il diario,
              il professionista monitora e ottimizza.
            </p>
          </article>

          <article class="card">
            <div class="icon">🥗</div>
            <h3>Nutrizione + AI</h3>
            <p>
              Diario alimentare con inserimento manuale o da foto: stima dei macronutrienti con supporto AI,
              sempre modificabile e sotto supervisione professionale.
            </p>
          </article>

          <article class="card">
            <div class="icon">💬</div>
            <h3>Chat & notifiche</h3>
            <p>
              Comunicazione continua tra professionisti e clienti: chat, messaggi e notifiche per tenere alta l’aderenza
              e ridurre i “buchi” nel percorso.
            </p>
          </article>

          <article class="card">
            <div class="icon">📊</div>
            <h3>Grafici e report</h3>
            <p>
              Traccia parametri fisici e prestazionali nel tempo, con grafici e report riepilogativi automatici.
            </p>
          </article>

          <article class="card">
            <div class="icon">🔑</div>
            <h3>ID-Key professionista</h3>
            <p>
              Associa i clienti a PT e/o Nutrizionisti tramite ID-Key. Gestione stati, cambio professionista e storico consultabile.
            </p>
          </article>

          <article class="card">
            <div class="icon">🧩</div>
            <h3>Ruoli e permessi</h3>
            <p>
              Visibilità dei dati controllata per ruolo: PT e Nutrizionisti con permessi differenziati e accesso incrociato in sola lettura.
            </p>
          </article>
        </div>
      </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="come-funziona">
      <div class="container">
        <div class="sectionHead">
          <div>
            <h2>Come funziona</h2>
            <p>Due percorsi chiari: Cliente e Professionista. Inizia in pochi minuti.</p>
          </div>
        </div>

        <div class="split">
          <div class="callout">
            <h3>Per Clienti</h3>
            <p style="margin-bottom:12px;">
              Registrati, completa i tuoi dati e (se vuoi) inserisci l’ID-Key del tuo PT/Nutrizionista anche in un secondo momento.
            </p>
            <ol class="list">
              <li>Crea l’account e accedi alla dashboard.</li>
              <li>Compila il diario allenamenti e alimentare.</li>
              <li>Carica foto dei pasti per la stima macro (opzionale).</li>
              <li>Ricevi feedback e monitora i progressi con grafici e report.</li>
            </ol>
            <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
              <a class="btn primary" href="register.php?tipo=cliente">Registrati (Cliente)</a>
              <a class="btn" href="login.php">Hai già un account?</a>
            </div>
          </div>

          <div class="callout">
            <h3>Per PT / Nutrizionisti</h3>
            <p style="margin-bottom:12px;">
              Registrazione con ruolo, gestione clienti e contenuti. Mantieni tutto sotto controllo: allenamenti, nutrizione e comunicazione.
            </p>
            <ol class="list">
              <li>Crea l’account professionista e imposta il profilo.</li>
              <li>Genera ID-Key e associa i clienti.</li>
              <li>Crea programmi / piani e monitora l’aderenza.</li>
              <li>Chat, notifiche e report per massimizzare continuità.</li>
            </ol>
            <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
              <a class="btn primary" href="register.php?tipo=professionista">Registrati (Professionista)</a>
              <a class="btn" href="#piani">Vedi i piani</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SECURITY -->
    <section id="sicurezza">
      <div class="container">
        <div class="sectionHead">
          <div>
            <h2>Sicurezza e conformità</h2>
            <p>
              Autenticazione sicura, protezione dei dati e gestione consensi: progettato per un contesto fitness/wellness
              con attenzione a privacy e GDPR.
            </p>
          </div>
        </div>

        <div class="grid3">
          <article class="card">
            <div class="icon">🔒</div>
            <h3>Protezione dei dati</h3>
            <p>
              Controllo accessi basato sui ruoli e protezione dei dati sensibili (personali, fisici e nutrizionali).
            </p>
          </article>
          <article class="card">
            <div class="icon">🛡️</div>
            <h3>GDPR-ready</h3>
            <p>
              Gestione consensi e diritti dell’utente (accesso e cancellazione). Trasparenza sul trattamento dati.
            </p>
          </article>
          <article class="card">
            <div class="icon">⚙️</div>
            <h3>Affidabilità</h3>
            <p>
              Obiettivo prestazioni e disponibilità: esperienza fluida su web e mobile con interfaccia responsive.
            </p>
          </article>
        </div>

        <p class="fineprint" style="margin-top:14px;">
          Nota: le funzionalità AI hanno scopo di supporto informativo e non sostituiscono il giudizio professionale.
        </p>
      </div>
    </section>

    <!-- PRICING -->
    <section id="piani">
      <div class="container">
        <div class="sectionHead">
          <div>
            <h2>Piani (bozza)</h2>
            <p>
              Se vuoi, li allineo ai limiti reali del tuo progetto (es. numero clienti, report periodici, automazioni).
              Qui sotto trovi una struttura “SaaS” pronta.
            </p>
          </div>
        </div>

        <div class="pricing">
          <div class="priceCard">
            <div class="badge">Starter</div>
            <div class="price">€0 <small>/ mese</small></div>
            <div class="hr"></div>
            <ul class="ul">
              <li><span class="check">✓</span><span>Accesso dashboard</span></li>
              <li><span class="check">✓</span><span>Diario allenamenti & alimentare</span></li>
              <li><span class="check">✓</span><span>Chat base</span></li>
              <li><span class="check">✓</span><span>Report essenziali</span></li>
            </ul>
            <div style="margin-top:14px;">
              <a class="btn primary" href="register.php?tipo=cliente">Inizia</a>
            </div>
          </div>

          <div class="priceCard" style="border-color: rgba(109,94,243,.35);">
            <div class="badge">Pro</div>
            <div class="price">€— <small>/ mese</small></div>
            <div class="hr"></div>
            <ul class="ul">
              <li><span class="check">✓</span><span>Gestione clienti + ID-Key</span></li>
              <li><span class="check">✓</span><span>Programmi / piani personalizzati</span></li>
              <li><span class="check">✓</span><span>Notifiche e promemoria</span></li>
              <li><span class="check">✓</span><span>Report periodici</span></li>
            </ul>
            <div style="margin-top:14px;">
              <a class="btn primary" href="register.php?tipo=professionista">Richiedi accesso</a>
            </div>
          </div>

          <div class="priceCard">
            <div class="badge">Studio</div>
            <div class="price">€— <small>/ mese</small></div>
            <div class="hr"></div>
            <ul class="ul">
              <li><span class="check">✓</span><span>Team e collaborazione</span></li>
              <li><span class="check">✓</span><span>Automazioni avanzate</span></li>
              <li><span class="check">✓</span><span>Limiti estesi</span></li>
              <li><span class="check">✓</span><span>Supporto prioritario</span></li>
            </ul>
            <div style="margin-top:14px;">
              <a class="btn" href="#faq">Parliamone</a>
            </div>
          </div>
        </div>

        <p class="fineprint">
          * I prezzi sono placeholder: dimmi cosa vuoi (gratis per clienti + abbonamenti per professionisti, ecc.) e li finalizzo.
        </p>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq">
      <div class="container">
        <div class="sectionHead">
          <div>
            <h2>FAQ</h2>
            <p>Le risposte rapide alle domande più comuni.</p>
          </div>
        </div>

        <div class="faq" role="list">
          <div class="faqItem open" role="listitem">
            <button class="faqQ" type="button">
              AuraFit è per clienti indipendenti o solo seguiti da un professionista?
              <span class="chev">⌄</span>
            </button>
            <div class="faqA">
              Entrambi: puoi registrarti come cliente e poi associare un PT/Nutrizionista tramite ID-Key anche in un secondo momento.
            </div>
          </div>

          <div class="faqItem" role="listitem">
            <button class="faqQ" type="button">
              La stima dei macro tramite AI è obbligatoria?
              <span class="chev">⌄</span>
            </button>
            <div class="faqA">
              No. È un supporto opzionale: puoi inserire manualmente i pasti oppure usare la foto per velocizzare. I valori restano modificabili.
            </div>
          </div>

          <div class="faqItem" role="listitem">
            <button class="faqQ" type="button">
              Come funziona la chat quando finisce l’associazione?
              <span class="chev">⌄</span>
            </button>
            <div class="faqA">
              Alla cessazione dell’associazione la chat viene disabilitata, mantenendo lo storico consultabile dove previsto.
            </div>
          </div>

          <div class="faqItem" role="listitem">
            <button class="faqQ" type="button">
              Come trattate privacy e cancellazione account?
              <span class="chev">⌄</span>
            </button>
            <div class="faqA">
              AuraFit è progettato per supportare gestione consensi e diritti dell’utente (accesso e cancellazione), con attenzione alla conformità GDPR.
            </div>
          </div>
        </div>

        <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
          <a class="btn primary" href="register.php?tipo=cliente">Crea account</a>
          <a class="btn" href="login.php">Accedi</a>
        </div>
      </div>
    </section>
  </main>

  <footer>
    <div class="container">
      <div class="footGrid">
        <div>
          <div class="brand" style="margin-bottom:10px;">
            <span class="logo" aria-hidden="true"></span>
            <span>AuraFit</span>
          </div>
          <div style="color: rgba(234,240,255,.72); line-height:1.6;">
            Piattaforma web/mobile per allenamento, nutrizione, monitoraggio e comunicazione tra professionisti e clienti.
          </div>
          <div class="fineprint">
            © <span id="year"></span> AuraFit. Tutti i diritti riservati.
          </div>
        </div>

        <div>
          <div style="font-weight:800; margin-bottom:10px;">Prodotto</div>
          <div style="display:grid; gap:8px;">
            <a href="#funzionalita">Funzionalità</a>
            <a href="#piani">Piani</a>
            <a href="#sicurezza">Sicurezza</a>
            <a href="login.php">Login</a>
          </div>
        </div>

        <div>
          <div style="font-weight:800; margin-bottom:10px;">Legale</div>
          <div style="display:grid; gap:8px;">
            <a href="#">Privacy Policy</a>
            <a href="#">Cookie Policy</a>
            <a href="#">Termini</a>
          </div>
          <div class="fineprint">
            Le funzionalità AI hanno scopo informativo e non sostituiscono il giudizio professionale.
          </div>
        </div>
      </div>
    </div>
  </footer>

  <script>
    // Navbar mobile
    const menuBtn = document.getElementById('menuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    if (menuBtn && mobileMenu) {
      menuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('open');
      });
      mobileMenu.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => mobileMenu.classList.remove('open'));
      });
    }

    // FAQ accordion
    document.querySelectorAll('.faqItem .faqQ').forEach(btn => {
      btn.addEventListener('click', () => {
        const item = btn.closest('.faqItem');
        const isOpen = item.classList.contains('open');
        document.querySelectorAll('.faqItem').forEach(x => x.classList.remove('open'));
        if (!isOpen) item.classList.add('open');
      });
    });

    // year
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
</body>
</html>

