/* =========================
   SEED AURAFIT
   ========================= */

SET FOREIGN_KEY_CHECKS = 0;

/* =========================
   RUOLI
   ========================= */
INSERT IGNORE INTO Ruoli (idRuolo, nomeRuolo) VALUES
(1, 'cliente'),
(2, 'pt'),
(3, 'nutrizionista');

/* =========================
   PIANI ABBONAMENTO
   ========================= */
INSERT IGNORE INTO PianiAbbonamento
(idPiano, tipoPiano, nome, prezzo, periodo, limiteClienti, includeAIPasti)
VALUES
(1, 'STARTER', 'Starter', 29.00, 'mensile', 5, 0),
(2, 'PRO', 'Pro', 7.00, 'mensile', 15, 1),
(3, 'ELITE', 'Elite', 5.00, 'mensile', 30, 1),
(4, 'BUSINESS', 'Business', 0.00, 'custom', NULL, 1),
(5, 'SOLO', 'AuraFit Solo', 9.99, 'mensile', NULL, 1);

/* =========================
   ESERCIZI BASE
   ========================= */
INSERT IGNORE INTO Esercizi (idEsercizio, nome, gruppoMuscolare) VALUES
(1, 'Panca piana', 'Petto'),
(2, 'Squat', 'Gambe'),
(3, 'Stacco da terra', 'Schiena'),
(4, 'Lat machine', 'Schiena'),
(5, 'Curl bilanciere', 'Bicipiti'),
(6, 'Push down', 'Tricipiti'),
(7, 'Military press', 'Spalle'),
(8, 'Leg press', 'Gambe');

/* =========================
   UTENTI DI TEST
   Password = "password123" (hash fittizio)
   ========================= */
INSERT IGNORE INTO Utenti
(idUtente, email, emailNormalizzata, passwordHash, nome, cognome, statoAccount)
VALUES
(1, 'cliente@test.it', 'cliente@test.it',
 '$2y$10$abcdefghijklmnopqrstuv', 'Mario', 'Rossi', 'attivo'),

(2, 'pt@test.it', 'pt@test.it',
 '$2y$10$abcdefghijklmnopqrstuv', 'Luca', 'Bianchi', 'attivo'),

(3, 'nutri@test.it', 'nutri@test.it',
 '$2y$10$abcdefghijklmnopqrstuv', 'Anna', 'Verdi', 'attivo');

/* =========================
   ASSEGNAZIONE RUOLI
   ========================= */
INSERT IGNORE INTO UtenteRuolo (idUtente, idRuolo) VALUES
(1, 1), -- cliente
(2, 2), -- pt
(3, 3); -- nutrizionista

/* =========================
   CLIENTI / PROFESSIONISTI
   ========================= */
INSERT IGNORE INTO Clienti (idCliente, idUtente) VALUES
(1, 1);

INSERT IGNORE INTO Professionisti (idProfessionista, idUtente) VALUES
(1, 2),
(2, 3);

/* =========================
   PROFILI
   ========================= */
INSERT IGNORE INTO ProfiloCliente
(idCliente, altezzaCm, pesoKg, eta, livelloAttivita)
VALUES
(1, 175, 75.0, 25, 'medio');

INSERT IGNORE INTO ProfiloProfessionista
(idProfessionista, statoVerifica)
VALUES
(1, 'verificato'),
(2, 'verificato');

/* =========================
   ABBONAMENTI DI TEST
   ========================= */
INSERT IGNORE INTO Abbonamenti
(idAbbonamento, utente, piano, stato, inizioPeriodo, finePeriodoCorrente)
VALUES
(1, 1, 5, 'attivo', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH)), -- Cliente SOLO
(2, 2, 2, 'attivo', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH)), -- PT PRO
(3, 3, 2, 'attivo', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH)); -- Nutri PRO

/* =========================
   ID-KEY DI TEST
   ========================= */
INSERT IGNORE INTO IDKey
(idKey, codice, professionista, tipo, stato)
VALUES
(1, 'PT-TEST-001', 1, 'pt', 'attiva'),
(2, 'NUTRI-TEST-001', 2, 'nutrizionista', 'attiva');

/* =========================
   ASSOCIAZIONE DI TEST
   ========================= */
INSERT IGNORE INTO Associazioni
(idAssociazione, cliente, professionista, tipo, idKey, attiva, dataInizio)
VALUES
(1, 1, 1, 'pt', 1, 1, NOW());

SET FOREIGN_KEY_CHECKS = 1;
