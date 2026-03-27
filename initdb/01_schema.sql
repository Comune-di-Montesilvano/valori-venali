-- ============================================================
-- Valori Venali Aree Fabbricabili — Schema Database
-- MariaDB 11 | Charset: utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+01:00';

-- ------------------------------------------------------------
-- Tavola principale valori OMI (importata da file CSV Sister)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `valori_omi` (
  `id_valore`        INT(11)      NOT NULL AUTO_INCREMENT,
  `Periodo`          VARCHAR(100) NULL DEFAULT NULL,
  `Zona`             VARCHAR(100) NULL DEFAULT NULL,
  `Cod_Tip`          VARCHAR(100) NULL DEFAULT NULL,
  `Descr_Tipologia`  VARCHAR(100) NULL DEFAULT NULL,
  `Stato`            VARCHAR(100) NULL DEFAULT NULL,
  `Compr_min`        INT(11)      NULL DEFAULT NULL,
  `Compr_max`        INT(11)      NULL DEFAULT NULL,
  PRIMARY KEY (`id_valore`),
  INDEX `idx_periodo` (`Periodo`),
  INDEX `idx_zona`    (`Zona`),
  INDEX `idx_cod_tip` (`Cod_Tip`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ------------------------------------------------------------
-- Coefficienti di abbattimento (es. stato conservativo)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `omi_abbattimenti` (
  `id_coefficiente` INT(11)       NOT NULL AUTO_INCREMENT,
  `descrizione`     VARCHAR(200)  NOT NULL,
  `valore`          DECIMAL(5,4)  NOT NULL COMMENT 'Valore tra 0 e 1',
  PRIMARY KEY (`id_coefficiente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Destinazioni urbanistiche e coefficienti PRG
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `omi_destinazione_urbanistica` (
  `id_destinazione`            INT(11)       NOT NULL AUTO_INCREMENT,
  `destinazione`               VARCHAR(200)  NOT NULL COMMENT 'Zona PRG (es. B1, C2)',
  `coefficiente_destinazione`  DECIMAL(5,4)  NOT NULL,
  `Cod_Tip`                    VARCHAR(100)  NULL DEFAULT NULL,
  `Stato`                      VARCHAR(100)  NULL DEFAULT NULL,
  `Valore`                     TINYINT(1)    NOT NULL DEFAULT 2 COMMENT '1=Min,2=Med,3=Max',
  PRIMARY KEY (`id_destinazione`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Abbinamento foglio catastale → zona OMI
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fogli_zone_omi` (
  `foglio_catastale` VARCHAR(50) NOT NULL,
  `zona_omi`         VARCHAR(100) NOT NULL,
  PRIMARY KEY (`foglio_catastale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Utenti amministratori
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Impostazioni applicazione (logo, favicon, ecc.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(50)  NOT NULL,
  `setting_value` TEXT         NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Seed dati: coefficienti abbattimento standard
-- ------------------------------------------------------------
INSERT IGNORE INTO `omi_abbattimenti` (`descrizione`, `valore`) VALUES
  ('Ottimo stato conservativo',              1.0000),
  ('Buono stato conservativo',               0.9000),
  ('Normale stato conservativo',             0.8000),
  ('Mediocre stato conservativo',            0.7000),
  ('Scadente stato conservativo',            0.5000)
;

-- ============================================================
-- Nota: l'utente admin viene creato all'avvio dell'app PHP
-- tramite lo script src/includes/seed_admin.php
-- se la tabella users è vuota, in modo da leggere le
-- credenziali da variabile d'ambiente e generare il bcrypt.
-- ============================================================
