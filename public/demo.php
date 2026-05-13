<?php
require_once __DIR__ . '/lib/security.php';
aurafit_start_secure_session();

$csrfToken = aurafit_get_csrf_token();

$flows = [
  [
    'title' => 'Lato cliente',
    'description' => 'Simula l’esperienza utente: onboarding, scelta obiettivi e percorso fitness.',
    'label' => 'Accedi come cliente demo',
    'email' => 'cliente@test.it',
    'password' => 'password123',
    'icon' => '👤',
    'class' => 'client'
  ],
  [
    'title' => 'Lato professionista',
    'description' => 'Prova gli strumenti dedicati a trainer, coach e operatori fitness.',
    'label' => 'Accedi come professionista demo',
    'email' => 'pt@test.it',
    'password' => 'password123',
    'icon' => '🏋️',
    'class' => 'pro'
  ],
];
?><!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>AuraFit - Demo Test</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#070A12">

  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="AuraFit">

  <link rel="apple-touch-icon" href="/media/apple-touch-icon.png">
  <link rel="manifest" href="/manifest.json">

  <style>
    :root {
      --bg: #070A12;
      --bg-soft: #0D1220;
      --text: #EAF0FF;
      --muted: rgba(234, 240, 255, .68);
      --muted-strong: rgba(234, 240, 255, .82);
      --line: rgba(234, 240, 255, .13);

      --brand-purple: #6D5EF3;
      --brand-green: #2EE1A5;
      --brand-cyan: #4CC9F0;
      --brand-pink: #FF5EA8;

      --radius-xl: 28px;
      --radius-lg: 22px;
      --radius-md: 16px;

      --shadow: 0 24px 70px rgba(0, 0, 0, .42);
      --sans: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      width: 100%;
      min-height: 100%;
      overflow-x: hidden;
    }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: var(--sans);
      color: var(--text);
      background:
        radial-gradient(circle at top left, rgba(109, 94, 243, .35), transparent 34rem),
        radial-gradient(circle at top right, rgba(46, 225, 165, .22), transparent 32rem),
        radial-gradient(circle at bottom, rgba(76, 201, 240, .18), transparent 34rem),
        var(--bg);
      display: grid;
      place-items: center;
      padding: 28px 16px;
      padding-top: calc(28px + env(safe-area-inset-top));
      padding-right: calc(16px + env(safe-area-inset-right));
      padding-bottom: calc(28px + env(safe-area-inset-bottom));
      padding-left: calc(16px + env(safe-area-inset-left));
      position: relative;
    }

    body::before {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      background-image:
        linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px);
      background-size: 44px 44px;
      mask-image: linear-gradient(to bottom, rgba(0,0,0,.85), transparent 75%);
      z-index: -2;
    }

    body::after {
      content: "";
      position: fixed;
      width: 420px;
      height: 420px;
      border-radius: 999px;
      background: linear-gradient(135deg, rgba(109,94,243,.24), rgba(46,225,165,.16));
      filter: blur(28px);
      opacity: .72;
      transform: translate(28vw, 22vh);
      z-index: -1;
      pointer-events: none;
    }

    .shell {
      width: min(100%, 920px);
    }

    .panel {
      position: relative;
      overflow: hidden;
      border: 1px solid var(--line);
      border-radius: var(--radius-xl);
      background:
        linear-gradient(180deg, rgba(255,255,255,.09), rgba(255,255,255,.035)),
        rgba(13,18,32,.72);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      box-shadow: var(--shadow);
    }

    .panel::before {
      content: "";
      position: absolute;
      inset: 0;
      background:
        linear-gradient(135deg, rgba(109,94,243,.16), transparent 34%),
        linear-gradient(315deg, rgba(46,225,165,.13), transparent 38%);
      pointer-events: none;
    }

    .content {
      position: relative;
      padding: clamp(24px, 5vw, 48px);
    }

    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: clamp(28px, 5vw, 48px);
    }

    .brand {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      font-weight: 800;
      letter-spacing: -.03em;
    }

    .brand-mark {
      width: 42px;
      height: 42px;
      display: grid;
      place-items: center;
      border-radius: 14px;
      background:
        linear-gradient(135deg, var(--brand-purple), var(--brand-green));
      color: #fff;
      box-shadow: 0 14px 28px rgba(109, 94, 243, .32);
    }

    .brand-mark img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      border-radius: 14px;
      display: block;
    }

    .hero {
      max-width: 710px;
      margin-bottom: 30px;
    }

    h1 {
      margin: 0;
      font-size: clamp(2.35rem, 7vw, 5.15rem);
      line-height: .94;
      letter-spacing: -.075em;
    }

    .gradient-text {
      background: linear-gradient(135deg, #fff 12%, #BFC8FF 44%, #82FFE0 86%);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .intro {
      max-width: 580px;
      margin: 20px 0 0;
      color: var(--muted);
      font-size: clamp(1rem, 2.5vw, 1.15rem);
      line-height: 1.65;
    }

    .actions {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
      margin-top: 34px;
    }

    .flow-form {
      margin: 0;
    }

    .flow-card {
      position: relative;
      width: 100%;
      min-height: 220px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 22px;
      padding: 22px;
      border: 1px solid rgba(255,255,255,.13);
      border-radius: var(--radius-lg);
      background: rgba(255,255,255,.055);
      color: inherit;
      text-decoration: none;
      overflow: hidden;
      transition:
        transform .18s ease,
        border-color .18s ease,
        background .18s ease,
        box-shadow .18s ease;
      font: inherit;
      text-align: left;
      cursor: pointer;
    }

    button.flow-card {
      appearance: none;
      -webkit-appearance: none;
    }

    .flow-card::before {
      content: "";
      position: absolute;
      inset: auto -40px -70px auto;
      width: 180px;
      height: 180px;
      border-radius: 999px;
      opacity: .32;
      filter: blur(4px);
      transition: transform .18s ease, opacity .18s ease;
    }

    .flow-card.client::before {
      background: var(--brand-purple);
    }

    .flow-card.pro::before {
      background: var(--brand-green);
    }

    .flow-card:hover {
      transform: translateY(-4px);
      border-color: rgba(255,255,255,.26);
      background: rgba(255,255,255,.085);
      box-shadow: 0 22px 46px rgba(0,0,0,.28);
    }

    .flow-card:hover::before {
      transform: scale(1.08);
      opacity: .45;
    }

    .flow-card:focus-visible {
      outline: 3px solid rgba(76,201,240,.72);
      outline-offset: 4px;
    }

    .flow-icon {
      width: 54px;
      height: 54px;
      display: grid;
      place-items: center;
      border-radius: 18px;
      background: rgba(255,255,255,.1);
      font-size: 1.45rem;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.14);
    }

    .flow-title {
      margin: 0 0 8px;
      font-size: 1.25rem;
      letter-spacing: -.03em;
    }

    .flow-description {
      margin: 0;
      color: var(--muted);
      line-height: 1.55;
      font-size: .96rem;
    }

    .flow-cta {
      position: relative;
      z-index: 1;
      display: inline-flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      width: 100%;
      border-radius: var(--radius-md);
      padding: 13px 14px;
      font-weight: 800;
      letter-spacing: -.02em;
      background: rgba(255,255,255,.1);
      box-shadow: inset 0 1px 0 rgba(255,255,255,.12);
    }

    .client .flow-cta {
      background: linear-gradient(135deg, var(--brand-purple), #8D7DFF);
      color: #fff;
    }

    .pro .flow-cta {
      background: linear-gradient(135deg, #1DBA8A, var(--brand-cyan));
      color: #021018;
    }

    .arrow {
      transition: transform .18s ease;
    }

    .flow-card:hover .arrow {
      transform: translateX(3px);
    }

    @media (max-width: 720px) {
      .topbar {
        align-items: flex-start;
      }

      .actions {
        grid-template-columns: 1fr;
      }

      .flow-card {
        min-height: 190px;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        scroll-behavior: auto !important;
        transition: none !important;
      }
    }
  </style>
</head>

<body>
  <main class="shell" role="main">
    <section class="panel" aria-labelledby="page-title">
      <div class="content">
        <header class="topbar">
          <div class="brand" aria-label="AuraFit">
            <div class="brand-mark" aria-hidden="true">
              <img src="/media/logo.png" alt="">
            </div>
            <span>AuraFit</span>
          </div>
        </header>

        <div class="hero">
          <h1 id="page-title">
            Testa i flussi <span class="gradient-text">AuraFit</span>
          </h1>
          <p class="intro">
            Scegli il profilo demo da usare: entrerai automaticamente con un account
            già configurato, senza dover inserire manualmente email e password.
          </p>
        </div>

        <div class="actions" aria-label="Account demo disponibili">
          <?php foreach ($flows as $flow): ?>
            <form class="flow-form" method="post" action="/public/login.php">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="email" value="<?= htmlspecialchars($flow['email'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="password" value="<?= htmlspecialchars($flow['password'], ENT_QUOTES, 'UTF-8') ?>">

              <button
                class="flow-card <?= htmlspecialchars($flow['class'], ENT_QUOTES, 'UTF-8') ?>"
                type="submit"
              >
                <span>
                  <span class="flow-icon" aria-hidden="true">
                    <?= htmlspecialchars($flow['icon'], ENT_QUOTES, 'UTF-8') ?>
                  </span>

                  <h2 class="flow-title">
                    <?= htmlspecialchars($flow['title'], ENT_QUOTES, 'UTF-8') ?>
                  </h2>

                  <span class="flow-description">
                    <?= htmlspecialchars($flow['description'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </span>

                <span class="flow-cta">
                  <?= htmlspecialchars($flow['label'], ENT_QUOTES, 'UTF-8') ?>
                  <span class="arrow" aria-hidden="true">→</span>
                </span>
              </button>
            </form>
          <?php endforeach; ?>
        </div>

      </div>
    </section>
  </main>
</body>
</html>
