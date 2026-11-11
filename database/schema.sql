-- =====================================================================
-- Projet  : Architecture Services & Training
-- Auteur  : OUNIADON HAIROULAH (ID: 411030513)
-- Fichier : database/schema.sql
-- Rôle    : Création de la base de données et des tables (Phase 2)
-- SGBD    : MariaDB 10.4+ (XAMPP) / MySQL 8+ — moteur InnoDB, utf8mb4
-- ---------------------------------------------------------------------
-- Modèle : un client (clients) dépose des demandes (requests).
-- Chaque demande a un type, et ses détails propres sont stockés dans une
-- table de spécialisation 1–1 (héritage « Class Table Inheritance ») :
--   custom_project      -> custom_project_details
--   plan_purchase       -> plan_purchase_details        -> plans
--   training_enrollment -> training_enrollment_details  -> trainings
-- =====================================================================

-- ATTENTION : supprime la base existante. À utiliser en développement
-- uniquement, pour pouvoir relancer le script autant de fois que voulu.
DROP DATABASE IF EXISTS archi_services;

CREATE DATABASE archi_services
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE archi_services;

-- ---------------------------------------------------------------------
-- 1. Catalogue des formations (Archicad, Lumion, Revit, Robot...)
-- ---------------------------------------------------------------------
CREATE TABLE trainings (
    id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(30)       NOT NULL,          -- ex. ARCHICAD-01
    title           VARCHAR(150)      NOT NULL,
    software        VARCHAR(60)       NOT NULL,
    description     TEXT              NULL,
    level           ENUM('beginner','intermediate','advanced')
                                      NOT NULL DEFAULT 'beginner',
    duration_hours  SMALLINT UNSIGNED NOT NULL,
    price           DECIMAL(10,2)     NOT NULL,          -- jamais de FLOAT pour l'argent
    currency        CHAR(3)           NOT NULL DEFAULT 'USD',  -- code ISO 4217
    delivery_mode   ENUM('online','onsite','hybrid')
                                      NOT NULL DEFAULT 'online',
    is_active       BOOLEAN           NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_trainings          PRIMARY KEY (id),
    CONSTRAINT uq_trainings_code     UNIQUE (code),
    CONSTRAINT ck_trainings_duration CHECK (duration_hours > 0),
    CONSTRAINT ck_trainings_price    CHECK (price >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. Catalogue des plans prêts à bâtir
-- ---------------------------------------------------------------------
CREATE TABLE plans (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    reference       VARCHAR(20)       NOT NULL,          -- ex. PLN-VIL-001
    title           VARCHAR(150)      NOT NULL,
    category        ENUM('villa','duplex','apartment_building','commercial','other')
                                      NOT NULL,
    bedrooms        TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    floors          TINYINT UNSIGNED  NOT NULL DEFAULT 1,
    built_area_m2   DECIMAL(8,2)      NOT NULL,
    price           DECIMAL(10,2)     NOT NULL,
    currency        CHAR(3)           NOT NULL DEFAULT 'USD',
    cover_image     VARCHAR(255)      NULL,              -- chemin relatif de l'image
    is_active       BOOLEAN           NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_plans           PRIMARY KEY (id),
    CONSTRAINT uq_plans_reference UNIQUE (reference),
    CONSTRAINT ck_plans_floors    CHECK (floors >= 1),
    CONSTRAINT ck_plans_area      CHECK (built_area_m2 > 0),
    CONSTRAINT ck_plans_price     CHECK (price >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. Clients (un e-mail = un client, réutilisé à chaque nouvelle demande)
-- ---------------------------------------------------------------------
CREATE TABLE clients (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    full_name       VARCHAR(120)      NOT NULL,
    email           VARCHAR(254)      NOT NULL,          -- longueur max RFC 5321
    phone           VARCHAR(20)       NULL,              -- format E.164 : +22997000000
    country         VARCHAR(80)       NULL,
    city            VARCHAR(80)       NULL,
    created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_clients       PRIMARY KEY (id),
    CONSTRAINT uq_clients_email UNIQUE (email),
    CONSTRAINT ck_clients_email CHECK (email LIKE '%_@_%._%')
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. Demandes (table « parent » commune aux trois types)
-- ---------------------------------------------------------------------
CREATE TABLE requests (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    client_id       INT UNSIGNED      NOT NULL,
    request_type    ENUM('custom_project','plan_purchase','training_enrollment')
                                      NOT NULL,
    message         TEXT              NULL,
    status          ENUM('new','in_progress','completed','cancelled')
                                      NOT NULL DEFAULT 'new',
    consent_given   BOOLEAN           NOT NULL,          -- case RGPD cochée
    ip_address      VARCHAR(45)       NULL,              -- 45 = longueur max IPv6
    created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_requests         PRIMARY KEY (id),
    CONSTRAINT fk_requests_client  FOREIGN KEY (client_id)
        REFERENCES clients (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_requests_consent CHECK (consent_given = 1),

    INDEX idx_requests_type_status (request_type, status),
    INDEX idx_requests_created     (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5a. Détails : projet sur mesure
-- ---------------------------------------------------------------------
CREATE TABLE custom_project_details (
    request_id        INT UNSIGNED    NOT NULL,          -- PK + FK => relation 1–1
    project_type      ENUM('architectural_design','3d_visualization','structural_study',
                           'bim_modeling','renovation','interior_design')
                                      NOT NULL,
    building_use      ENUM('residential','commercial','institutional','mixed')
                                      NOT NULL DEFAULT 'residential',
    location          VARCHAR(150)    NULL,
    land_area_m2      DECIMAL(10,2)   NULL,
    budget_min        DECIMAL(12,2)   NULL,
    budget_max        DECIMAL(12,2)   NULL,
    currency          CHAR(3)         NOT NULL DEFAULT 'USD',
    desired_deadline  DATE            NULL,

    CONSTRAINT pk_custom_project_details PRIMARY KEY (request_id),
    CONSTRAINT fk_cpd_request FOREIGN KEY (request_id)
        REFERENCES requests (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_cpd_land   CHECK (land_area_m2 IS NULL OR land_area_m2 > 0),
    CONSTRAINT ck_cpd_budget CHECK (budget_min IS NULL OR budget_min >= 0),
    CONSTRAINT ck_cpd_range  CHECK (budget_min IS NULL OR budget_max IS NULL
                                    OR budget_min <= budget_max)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5b. Détails : achat de plan prêt à bâtir
-- ---------------------------------------------------------------------
CREATE TABLE plan_purchase_details (
    request_id          INT UNSIGNED  NOT NULL,
    plan_id             INT UNSIGNED  NOT NULL,
    license_type        ENUM('single_build','multi_build')
                                      NOT NULL DEFAULT 'single_build',
    needs_customization BOOLEAN       NOT NULL DEFAULT FALSE,
    unit_price          DECIMAL(10,2) NOT NULL,          -- prix figé au moment de la demande
    currency            CHAR(3)       NOT NULL DEFAULT 'USD',

    CONSTRAINT pk_plan_purchase_details PRIMARY KEY (request_id),
    CONSTRAINT fk_ppd_request FOREIGN KEY (request_id)
        REFERENCES requests (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ppd_plan FOREIGN KEY (plan_id)
        REFERENCES plans (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,         -- interdit de supprimer un plan déjà commandé
    CONSTRAINT ck_ppd_price CHECK (unit_price >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5c. Détails : inscription à une formation
-- ---------------------------------------------------------------------
CREATE TABLE training_enrollment_details (
    request_id       INT UNSIGNED      NOT NULL,
    training_id      SMALLINT UNSIGNED NOT NULL,
    delivery_mode    ENUM('online','onsite') NOT NULL,
    current_level    ENUM('beginner','intermediate','advanced')
                                       NOT NULL DEFAULT 'beginner',
    preferred_start  DATE              NULL,

    CONSTRAINT pk_training_enrollment_details PRIMARY KEY (request_id),
    CONSTRAINT fk_ted_request FOREIGN KEY (request_id)
        REFERENCES requests (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ted_training FOREIGN KEY (training_id)
        REFERENCES trainings (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. Vue de synthèse (jointures) : toutes les demandes sur une ligne
-- ---------------------------------------------------------------------
CREATE VIEW v_requests_overview AS
SELECT
    r.id            AS request_id,
    r.created_at,
    r.request_type,
    r.status,
    c.full_name,
    c.email,
    c.phone,
    COALESCE(cpd.project_type, p.title, t.title) AS subject,
    r.message
FROM requests r
INNER JOIN clients c                          ON c.id = r.client_id
LEFT  JOIN custom_project_details cpd         ON cpd.request_id = r.id
LEFT  JOIN plan_purchase_details ppd          ON ppd.request_id = r.id
LEFT  JOIN plans p                            ON p.id = ppd.plan_id
LEFT  JOIN training_enrollment_details ted    ON ted.request_id = r.id
LEFT  JOIN trainings t                        ON t.id = ted.training_id;
