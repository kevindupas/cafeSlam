-- Fichier : categories_triggers_mysql.sql
-- Rôle : triggers de la table categories, version MySQL / MariaDB.
--        Exécuté par la migration create_categories_triggers.
-- Auteur : kit de démarrage fourni par l'enseignant.
-- Réalisation : R1.
--
-- Pas de DELIMITER ici : il ne sert que dans la console mysql.
-- DB::unprepared envoie le trigger d'un seul bloc, BEGIN ... END compris.

-- Trigger : met à jour la date de modification d'une catégorie.
-- Événement : avant chaque UPDATE sur categories (une fois par ligne modifiée).
-- Règle : la date de modification est toujours juste, même si la requête oublie de la remplir.
-- Pourquoi BEFORE et pas AFTER : en BEFORE, on peut modifier la ligne avant son
--          enregistrement avec SET NEW.colonne. MySQL interdit à un trigger de faire un
--          UPDATE sur la table qui l'a déclenché (erreur 1442).
-- Exemple : UPDATE categories SET name = 'Cafés filtres' WHERE id = 1;
--           -> updated_at de la catégorie 1 passe à la date et l'heure actuelles.
CREATE TRIGGER trg_categories_updated_at
BEFORE UPDATE ON categories
FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END;
