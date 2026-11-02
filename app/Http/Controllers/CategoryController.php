<?php

/**
 * Fichier : CategoryController.php
 * Rôle : gestion des catégories de la carte du café.
 *        Contrôleur d'exemple : il montre sur une table simple tous les cas à reproduire
 *        dans vos contrôleurs (pagination, 404, 409, 422, 201, route réservée à l'admin).
 * Auteur : kit de démarrage fourni par l'enseignant.
 * Réalisation : R1.
 */

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * GET /api/categories — Liste paginée des catégories, triée par nom.
     * Accès : public.
     * Paramètre optionnel : page (numéro de page, 1 par défaut).
     * Réponse : 200, enveloppe {data, error, meta} avec la pagination dans meta.
     * Codes : 200.
     *
     * À faire de votre côté : quand votre table des produits existera, ajoutez à cette requête
     * le nombre de produits actifs de chaque catégorie (COUNT + GROUP BY).
     * Question à se poser : faut-il un INNER JOIN ou un LEFT JOIN pour ne pas perdre
     * les catégories qui n'ont encore aucun produit ?
     */
    public function index(Request $request): JsonResponse
    {
        // max(1, ...) : une page 0 ou négative est ramenée à la page 1.
        $page = max(1, $request->integer('page', 1));
        $perPage = 20;

        // Récupère une page de catégories triées par nom.
        // LIMIT = nombre de lignes par page, OFFSET = nombre de lignes à sauter avant la page voulue
        // (page 1 -> on saute 0 ligne, page 2 -> on saute 20 lignes…).
        $categories = DB::select(
            'SELECT id, name, description
             FROM categories
             ORDER BY name
             LIMIT ? OFFSET ?',
            [$perPage, ($page - 1) * $perPage]
        );

        // Compte toutes les catégories pour que le front puisse calculer le nombre de pages.
        $total = DB::selectOne('SELECT COUNT(*) AS n FROM categories')->n;

        return ApiResponse::success($categories, [
            'page' => $page,
            'page_size' => $perPage,
            'total_results' => $total,
        ]);
    }

    /**
     * GET /api/categories/{id} — Détail d'une catégorie.
     * Accès : public.
     * Paramètre d'URL : id (entier).
     * Réponse : 200 avec { id, name, description }.
     * Codes : 200, 404 catégorie inexistante.
     */
    public function show(int $id): JsonResponse
    {
        // Cherche la catégorie par sa clé primaire ; l'id passe par un paramètre lié (?).
        $category = DB::selectOne('SELECT id, name, description FROM categories WHERE id = ?', [$id]);

        // selectOne renvoie null quand aucune ligne ne correspond : c'est un 404.
        if ($category === null) {
            return ApiResponse::error(404, 'categorie-introuvable', 'Catégorie introuvable.', "Aucune catégorie n'a l'identifiant ".$id.'.');
        }

        return ApiResponse::success($category);
    }

    /**
     * POST /api/categories — Ajoute une catégorie à la carte.
     * Accès : admin (middleware role:admin dans routes/api.php).
     * Corps JSON : name, description.
     * Réponse : 201 avec la catégorie créée { id, name, description }.
     * Codes : 201, 401 non connecté, 403 rôle insuffisant, 409 nom déjà utilisé, 422 données invalides.
     */
    public function store(Request $request): JsonResponse
    {
        // Erreur de saisie (champ vide, trop long) -> 422 renvoyé automatiquement par validate().
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string'],
        ]);

        // Vérifie qu'aucune catégorie ne porte déjà ce nom : un doublon est un conflit métier (409).
        $existing = DB::selectOne('SELECT id FROM categories WHERE name = ?', [$validated['name']]);

        if ($existing !== null) {
            return ApiResponse::error(409, 'categorie-existante', 'Catégorie déjà existante.', 'La catégorie "'.$validated['name'].'" existe déjà.');
        }

        // Insère la catégorie. On écrit le SQL à la main, donc on remplit nous-mêmes
        // created_at et updated_at avec la date du jour (now()).
        DB::insert(
            'INSERT INTO categories (name, description, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [$validated['name'], $validated['description'], now(), now()]
        );

        // Récupère l'id généré par la base pour renvoyer la ressource créée.
        $id = (int) DB::getPdo()->lastInsertId();

        return ApiResponse::success(
            DB::selectOne('SELECT id, name, description FROM categories WHERE id = ?', [$id]),
            status: 201
        );
    }

    /**
     * PUT /api/categories/{id} — Modifie le nom et la description d'une catégorie.
     * Accès : admin.
     * Paramètre d'URL : id (entier). Corps JSON : name, description.
     * Réponse : 200 avec la catégorie modifiée { id, name, description }.
     * Codes : 200, 401, 403, 404 catégorie inexistante, 409 nom déjà utilisé, 422 données invalides.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string'],
        ]);

        // Vérifie que la catégorie à modifier existe.
        $category = DB::selectOne('SELECT id FROM categories WHERE id = ?', [$id]);

        if ($category === null) {
            return ApiResponse::error(404, 'categorie-introuvable', 'Catégorie introuvable.', "Aucune catégorie n'a l'identifiant ".$id.'.');
        }

        // Cherche une AUTRE catégorie qui porte déjà ce nom (id <> ? : on s'exclut soi-même,
        // sinon garder le même nom en changeant seulement la description serait refusé).
        $duplicate = DB::selectOne('SELECT id FROM categories WHERE name = ? AND id <> ?', [$validated['name'], $id]);

        if ($duplicate !== null) {
            return ApiResponse::error(409, 'categorie-existante', 'Catégorie déjà existante.', 'La catégorie "'.$validated['name'].'" existe déjà.');
        }

        // Modifie la catégorie. updated_at est aussi mis à jour par le trigger trg_categories_updated_at.
        DB::update(
            'UPDATE categories SET name = ?, description = ?, updated_at = ? WHERE id = ?',
            [$validated['name'], $validated['description'], now(), $id]
        );

        return ApiResponse::success(
            DB::selectOne('SELECT id, name, description FROM categories WHERE id = ?', [$id])
        );
    }

    /**
     * DELETE /api/categories/{id} — Supprime une catégorie.
     * Accès : admin.
     * Paramètre d'URL : id (entier).
     * Réponse : 200, data = { message }.
     * Codes : 200, 401, 403, 404 catégorie inexistante, 409 la catégorie contient des produits.
     */
    public function destroy(int $id): JsonResponse
    {
        // Compte les produits rangés dans cette catégorie (actifs ou retirés).
        // La clé étrangère products.category_id interdit de supprimer une catégorie utilisée :
        // on le vérifie avant pour renvoyer un 409 clair plutôt qu'une erreur SQL (500).
        $productCount = DB::selectOne('SELECT COUNT(*) AS n FROM products WHERE category_id = ?', [$id])->n;

        if ($productCount > 0) {
            return ApiResponse::error(409, 'categorie-utilisee', 'Catégorie utilisée.', 'Cette catégorie contient '.$productCount.' produit(s), elle ne peut pas être supprimée.');
        }

        // DB::delete renvoie le nombre de lignes supprimées : 0 = la catégorie n'existait pas.
        $deletedRows = DB::delete('DELETE FROM categories WHERE id = ?', [$id]);

        if ($deletedRows === 0) {
            return ApiResponse::error(404, 'categorie-introuvable', 'Catégorie introuvable.', "Aucune catégorie n'a l'identifiant ".$id.'.');
        }

        return ApiResponse::success(['message' => 'Catégorie supprimée.']);
    }
}
