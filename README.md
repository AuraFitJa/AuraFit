# AuraFit

![AuraFit Logo](https://i.imgur.com/q8qW3dv.png)

> **La piattaforma web per coaching fitness e nutrizione**: un unico spazio per clienti, personal trainer e nutrizionisti.

---

## 🚀 Cos’è AuraFit

**AuraFit** è una web app pensata per digitalizzare il rapporto tra professionista e cliente nel mondo wellness.

Con AuraFit puoi:
- organizzare allenamenti e piani nutrizionali;
- monitorare i progressi nel tempo;
- semplificare la comunicazione tra cliente e professionista;
- gestire ruoli e accessi in modo ordinato.

L’obiettivo è avere un flusso completo: **assegnazione programma → esecuzione → feedback → miglioramento continuo**.

---

## 🎯 Per chi è pensata

### 👤 Clienti
- Accesso a dashboard personale.
- Consultazione programmi assegnati.
- Diario allenamenti e avanzamento.
- Tracciamento abitudini alimentari.
- Comunicazione con il professionista.

### 🧑‍🏫 Personal Trainer / Nutrizionisti
- Gestione clienti tramite sistema **ID-Key**.
- Creazione, modifica e assegnazione programmi.
- Controllo aderenza e progressi.
- Organizzazione report e panoramica attività.

---

## ✨ Funzionalità principali

- **Autenticazione utenti** con separazione ruoli.
- **Dashboard dedicate** per clienti e professionisti.
- **Libreria programmi** con assegnazione rapida ai clienti.
- **Monitoraggio progressi** e stato esecuzione.
- **Sezioni nutrizione e questionari** per raccolta dati utili.
- **Strumenti di supporto operativo** per una gestione più efficace.

---

## 🧱 Stack tecnologico

- **Backend:** PHP
- **Database:** MySQL / MariaDB
- **Frontend:** HTML, CSS, JavaScript
- **Architettura:** organizzazione per aree (`public`, `sql`, `config`, `app`)
- **Versionamento:** Git + GitHub

---

## 📂 Struttura del repository

```text
AuraFit/
├── app/                         # Modelli e logica lato applicazione
├── config/                      # Configurazioni (DB sample incluso)
├── public/                      # Entry point web, dashboard, controller, asset
│   ├── assets/
│   ├── controllers/
│   ├── dashboard_professionisti/
│   └── models/
├── sql/                         # Schema e seed del database
├── ToDoList.txt
└── README.md
```

---

## ⚙️ Setup rapido (locale)

1. **Clona il repository**
   ```bash
   git clone <repo-url>
   cd AuraFit
   ```

2. **Configura il database**
   - crea un database MySQL/MariaDB;
   - importa gli script presenti in `sql/` (schema + eventuali seed);
   - crea `config/database.php` partendo da `config/database.sample.php`.

3. **Avvia in locale**
   - usa un ambiente PHP (es. Apache/Nginx + PHP);
   - imposta `public/` come document root, oppure raggiungi l’app dalla route corretta.

---

## 🔐 Sicurezza e buone pratiche

- Gestione credenziali tramite file di configurazione locale non versionato.
- Separazione dei ruoli applicativi.
- Flusso orientato alla protezione dati utente.

> Suggerimento: in produzione, abilita sempre HTTPS, policy robuste per password e backup periodici del database.

---

## 🗺️ Roadmap (high-level)

- Miglioramento analytics progressi.
- Potenziamento area report professionisti.
- Ottimizzazione UX dashboard mobile.
- Integrazioni future con servizi esterni.

---

## 🤝 Contributi

Contributi, issue e suggerimenti sono benvenuti.

Se vuoi collaborare:
1. apri una issue;
2. proponi una soluzione chiara;
3. invia una pull request con modifiche focalizzate.

---

## 📄 Licenza

Se non diversamente specificato, tutti i diritti sono riservati agli autori del progetto.

Per introdurre una licenza open-source, aggiungere un file `LICENSE` in root.
