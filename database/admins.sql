-- =====================================================================
-- Projet  : Architecture & Engineering Services
-- Auteur  : OUNIADON HAIROULAH (ID: 411030513)
-- Fichier : database/admins.sql
-- Rôle    : Table des administrateurs (espace privé /admin).
--           À importer sur une base existante (déjà inclus dans schema.sql).
--           Le compte se crée ensuite avec database/create_admin.php.
-- =====================================================================

USE archi_services;

CREATE TABLE IF NOT EXISTS admins (
    id               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username         VARCHAR(50)       NOT NULL,
    -- Jamais le mot de passe en clair : uniquement son empreinte
    -- password_hash() (bcrypt, sel aléatoire inclus, ~60 caractères ;
    -- 255 laisse de la marge pour les algorithmes futurs).
    password_hash    VARCHAR(255)      NOT NULL,
    failed_attempts  TINYINT UNSIGNED  NOT NULL DEFAULT 0,   -- anti force brute
    locked_until     DATETIME          NULL,                 -- verrouillage temporaire
    last_login_at    DATETIME          NULL,
    created_at       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_admins          PRIMARY KEY (id),
    CONSTRAINT uq_admins_username UNIQUE (username),
    CONSTRAINT ck_admins_username CHECK (CHAR_LENGTH(username) >= 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
