<?php
?><!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AuraFit Demo</title>
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            color: #1f2937;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .demo-container {
            width: min(100%, 520px);
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.6rem;
        }

        p {
            margin: 0 0 24px;
            color: #4b5563;
        }

        .actions {
            display: grid;
            gap: 12px;
        }

        .actions button {
            border: 0;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.12s ease;
        }

        .btn-cliente {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-professionista {
            background: #059669;
            color: #ffffff;
        }

        .actions button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
        }

        .actions button:active {
            transform: translateY(0);
            opacity: 0.92;
        }
    </style>
</head>
<body>
    <main class="demo-container">
        <h1>Demo AuraFit</h1>
        <p>Seleziona il tipo di test da avviare.</p>

        <div class="actions">
            <button type="button" class="btn-cliente">Test AuraFit lato cliente</button>
            <button type="button" class="btn-professionista">Test AuraFit lato professionista</button>
        </div>
    </main>
</body>
</html>
