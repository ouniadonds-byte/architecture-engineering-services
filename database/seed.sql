-- =====================================================================
-- Projet  : Architecture Services & Training
-- Auteur  : OUNIADON HAIROULAH (ID: 411030513)
-- Fichier : database/seed.sql
-- Rôle    : Données initiales des catalogues (à lancer après schema.sql)
-- NB      : prix et durées = valeurs d'exemple, à remplacer par les vôtres
-- =====================================================================

USE archi_services;

INSERT INTO trainings (code, title, software, description, level, duration_hours, price, delivery_mode) VALUES
('ARCHICAD-01', 'Archicad : de l''esquisse au dossier d''exécution', 'Archicad',
 'Modélisation BIM, plans, coupes, façades et mise en page.', 'beginner', 30, 150.00, 'online'),
('LUMION-01',   'Lumion : rendus et animations photoréalistes', 'Lumion',
 'Matériaux, éclairage, végétation, rendus fixes et vidéos.', 'intermediate', 20, 120.00, 'online'),
('REVIT-01',    'Revit Architecture : maîtriser le BIM', 'Revit',
 'Familles, gabarits, nomenclatures et documentation.', 'beginner', 35, 170.00, 'hybrid'),
('RSA-01',      'Robot Structural Analysis : calcul de structures', 'Robot Structural Analysis',
 'Modélisation, charges, combinaisons et dimensionnement béton armé.', 'advanced', 30, 200.00, 'online');

INSERT INTO plans (reference, title, category, bedrooms, floors, built_area_m2, price, cover_image) VALUES
('PLN-VIL-001', 'Villa contemporaine 4 chambres', 'villa',  4, 1, 210.00, 450.00, 'assets/images/plans/pln-vil-001.webp'),
('PLN-DUP-001', 'Duplex moderne avec terrasse',   'duplex', 5, 2, 320.00, 650.00, 'assets/images/plans/pln-dup-001.webp'),
('PLN-IMM-001', 'Immeuble R+3, 8 appartements',   'apartment_building', 16, 4, 780.00, 1200.00, 'assets/images/plans/pln-imm-001.webp');
