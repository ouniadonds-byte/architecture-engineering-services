-- =====================================================================
-- Projet  : Architecture Services & Training
-- Auteur  : OUNIADON HAIROULAH (ID: 411030513)
-- Fichier : database/app_user.sql
-- Rôle    : Compte MySQL dédié à l'application (principe du moindre
--           privilège : le site ne se connecte jamais en « root »).
-- AVANT DE LANCER : remplacez le mot de passe ci-dessous.
-- =====================================================================

CREATE USER IF NOT EXISTS 'archi_app'@'localhost'
    IDENTIFIED BY 'REMPLACEZ_MOI_par_un_mot_de_passe_fort';

-- Lecture des catalogues + insertion / mise à jour des demandes.
-- Pas de DELETE, DROP ni ALTER : une faille éventuelle ne pourrait pas
-- détruire les données.
GRANT SELECT, INSERT, UPDATE ON archi_services.* TO 'archi_app'@'localhost';

FLUSH PRIVILEGES;
