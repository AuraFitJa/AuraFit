# AuraFit

![AuraFit Logo](https://i.imgur.com/q8qW3dv.png)

AuraFit è una piattaforma web per **allenamento, nutrizione e coaching** pensata per  
**Clienti, Personal Trainer e Nutrizionisti**.

Un unico hub per gestire programmi di allenamento, diario alimentare, comunicazione e monitoraggio dei progressi in modo semplice e strutturato.

Test Commit

---

## 🔖 Badge

![PHP](https://cdn-icons-png.flaticon.com/128/919/919830.png)
![MySQL](https://cdn-icons-png.flaticon.com/128/919/919836.png)
![Status](https://cdn-icons-png.flaticon.com/128/3022/3022148.png)
![Deploy](https://cdn-icons-png.flaticon.com/128/18565/18565727.png)


---

## ✨ Funzionalità principali

### 👤 Clienti
- Registrazione e gestione profilo
- Diario allenamenti
- Diario alimentare (con supporto AI opzionale)
- Monitoraggio progressi con grafici e report
- Chat con PT e/o Nutrizionista

### 🧑‍🏫 Personal Trainer / Nutrizionisti
- Registrazione con ruolo
- Gestione clienti tramite **ID-Key**
- Creazione programmi di allenamento e piani nutrizionali
- Monitoraggio aderenza
- Chat, notifiche e report automatici

### 🔐 Sicurezza & Privacy
- Autenticazione sicura
- Gestione ruoli e permessi
- Protezione dei dati sensibili
- Progettato per essere **GDPR-ready**

---

## 🛠️ Stack tecnologico

- **Backend:** PHP (PDO)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3
- **Autenticazione:** password_hash / password_verify
- **Deploy:** AlterVista
- **Versionamento:** Git + GitHub

---

## 📁 Struttura del progetto

```text
/
├── config/
│   ├── database.sample.php
│   └── database.php
│
├── public/
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── dashboard_cliente.php
│   └── dashboard_professionista.php
│
├── sql/
│   └── my_aurafit.sql
│
├── .gitignore
└── README.md
