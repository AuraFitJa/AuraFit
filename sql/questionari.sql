CREATE TABLE IF NOT EXISTS Questionari (
  idQuestionario INT AUTO_INCREMENT PRIMARY KEY,
  professionista INT NOT NULL,
  titolo VARCHAR(255) NOT NULL,
  descrizione TEXT NULL,
  categoria VARCHAR(80) NOT NULL DEFAULT 'generale',
  stato ENUM('bozza','attivo','archiviato') NOT NULL DEFAULT 'bozza',
  creatoIl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornatoIl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_questionari_professionista (professionista),
  CONSTRAINT fk_questionari_professionista FOREIGN KEY (professionista) REFERENCES Professionisti(idProfessionista) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS QuestionarioDomande (
  idDomanda INT AUTO_INCREMENT PRIMARY KEY,
  questionario INT NOT NULL,
  tipoDomanda ENUM('short_text','long_text','single_choice','multiple_choice','number','date','consent_checkbox') NOT NULL,
  testoDomanda TEXT NOT NULL,
  descrizione TEXT NULL,
  placeholderText VARCHAR(255) NULL,
  ordine INT NOT NULL DEFAULT 1,
  impostazioniJson JSON NULL,
  creatoIl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornatoIl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_domande_questionario (questionario, ordine),
  CONSTRAINT fk_domande_questionario FOREIGN KEY (questionario) REFERENCES Questionari(idQuestionario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS QuestionarioOpzioni (
  idOpzione INT AUTO_INCREMENT PRIMARY KEY,
  domanda INT NOT NULL,
  labelOpzione VARCHAR(255) NOT NULL,
  valoreOpzione VARCHAR(255) NOT NULL,
  ordine INT NOT NULL DEFAULT 1,
  INDEX idx_opzioni_domanda (domanda, ordine),
  CONSTRAINT fk_opzioni_domanda FOREIGN KEY (domanda) REFERENCES QuestionarioDomande(idDomanda) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS QuestionarioAssegnazioni (
  idAssegnazioneQuestionario INT AUTO_INCREMENT PRIMARY KEY,
  questionario INT NOT NULL,
  cliente INT NOT NULL,
  professionista INT NOT NULL,
  stato ENUM('attivo','disattivato') NOT NULL DEFAULT 'attivo',
  assegnatoIl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  disattivatoIl DATETIME NULL,
  INDEX idx_ass_questionario (questionario),
  INDEX idx_ass_cliente (cliente),
  INDEX idx_ass_prof (professionista),
  CONSTRAINT fk_ass_questionario FOREIGN KEY (questionario) REFERENCES Questionari(idQuestionario) ON DELETE CASCADE,
  CONSTRAINT fk_ass_cliente FOREIGN KEY (cliente) REFERENCES Clienti(idCliente) ON DELETE CASCADE,
  CONSTRAINT fk_ass_prof FOREIGN KEY (professionista) REFERENCES Professionisti(idProfessionista) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS QuestionarioCompilazioni (
  idCompilazione INT AUTO_INCREMENT PRIMARY KEY,
  assegnazione INT NOT NULL,
  questionario INT NOT NULL,
  cliente INT NOT NULL,
  numeroCompilazione INT NOT NULL,
  stato ENUM('bozza','inviato') NOT NULL DEFAULT 'bozza',
  iniziatoIl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornatoIl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  inviatoIl DATETIME NULL,
  ricompilazioneDi INT NULL,
  UNIQUE KEY uk_compilazione_numero (assegnazione, numeroCompilazione),
  INDEX idx_comp_cliente (cliente, stato),
  INDEX idx_comp_questionario (questionario),
  CONSTRAINT fk_comp_assegnazione FOREIGN KEY (assegnazione) REFERENCES QuestionarioAssegnazioni(idAssegnazioneQuestionario) ON DELETE CASCADE,
  CONSTRAINT fk_comp_questionario FOREIGN KEY (questionario) REFERENCES Questionari(idQuestionario) ON DELETE CASCADE,
  CONSTRAINT fk_comp_cliente FOREIGN KEY (cliente) REFERENCES Clienti(idCliente) ON DELETE CASCADE,
  CONSTRAINT fk_comp_ricomp FOREIGN KEY (ricompilazioneDi) REFERENCES QuestionarioCompilazioni(idCompilazione) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS QuestionarioRisposte (
  idRisposta INT AUTO_INCREMENT PRIMARY KEY,
  compilazione INT NOT NULL,
  domanda INT NOT NULL,
  valoreTesto TEXT NULL,
  valoreNumero DECIMAL(10,2) NULL,
  valoreData DATE NULL,
  valoreBoolean TINYINT(1) NULL,
  valoreJson JSON NULL,
  creatoIl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornatoIl DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_risposta_domanda (compilazione, domanda),
  INDEX idx_risposte_domanda (domanda),
  CONSTRAINT fk_risposte_compilazione FOREIGN KEY (compilazione) REFERENCES QuestionarioCompilazioni(idCompilazione) ON DELETE CASCADE,
  CONSTRAINT fk_risposte_domanda FOREIGN KEY (domanda) REFERENCES QuestionarioDomande(idDomanda) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
