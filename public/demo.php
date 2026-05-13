<?php
?><!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>AuraFit - Demo Test</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="AuraFit">
  <link rel="apple-touch-icon" href="/media/apple-touch-icon.png">
  <link rel="manifest" href="/manifest.json">
  <style>
    :root{
      --bg:#070A12;
      --text:#EAF0FF;
      --muted: rgba(234,240,255,.68);
      --line: rgba(234,240,255,.12);
      --brand1:#6D5EF3;
      --brand2:#2EE1A5;
      --brand3:#4CC9F0;
      --sans: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
    }

    * { box-sizing: border-box; }
    html, body { width:100%; max-width:100%; overflow-x:hidden; }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: var(--sans);
      color: var(--text);
      background: var(--bg);
      display: grid;
      place-items: center;
      padding: 24px 16px;
      padding-top: calc(24px + env(safe-area-inset-top));
      padding-right: calc(16px + env(safe-area-inset-right));
      padding-bottom: calc(24px + env(safe-area-inset-bottom));
      padding-left: calc(16px + env(safe-area-inset-left));
      position: relative;
    }

    body::before {
      content: "";
      position: fixed;
      inset: 0;
      z-index: -1;
      background:
        radial-gradient(1200px 800px at 20% -10%, rgba(109,94,243,.35), transparent 55%),
        radial-gradient(1100px 700px at 90% 10%, rgba(46,225,165,.22), transparent 55%),
        radial-gradient(900px 700px at 55% 95%, rgba(76,201,240,.18), transparent 55%);
      pointer-events: none;
    }

    .card {
      width: min(92vw, 460px);
      border: 1px solid var(--line);
      border-radius: 20px;
      background: linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.02));
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      box-shadow: 0 18px 45px rgba(0,0,0,.35);
      padding: 24px;
    }

    h1 {
      margin: 0 0 8px;
      font-size: clamp(24px, 4.8vw, 32px);
      line-height: 1.1;
      text-align: center;
    }

    p {
      margin: 0 0 20px;
      color: var(--muted);
      text-align: center;
    }

    .actions {
      display: grid;
      gap: 12px;
    }

    .btn {
      appearance: none;
      border: 0;
      border-radius: 14px;
      padding: 13px 16px;
      font-weight: 700;
      font-size: 1rem;
      cursor: pointer;
      transition: transform .12s ease, filter .12s ease, box-shadow .12s ease;
      box-shadow: 0 10px 20px rgba(0,0,0,.22);
      color: #fff;
    }

    .btn:active { transform: translateY(1px) scale(.995); }
    .btn:hover { filter: brightness(1.06); }

    .btn-cliente {
      background: linear-gradient(135deg, var(--brand1), #8D7DFF);
    }

    .btn-professionista {
      background: linear-gradient(135deg, #1DBA8A, var(--brand3));
      color: #021018;
    }
  </style>
</head>
<body>
  <main class="card" role="main">
    <h1>Demo AuraFit</h1>
    <p>Seleziona il flusso da testare.</p>

    <div class="actions">
      <button type="button" class="btn btn-cliente">Test AuraFit lato cliente</button>
      <button type="button" class="btn btn-professionista">Test AuraFit lato professionista</button>
    </div>
  </main>
</body>
</html>
