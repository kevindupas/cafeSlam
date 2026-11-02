<?php

/**
 * Fichier : ProductController.php
 * Rôle : consultation de la carte (liste, recherche, détail) et gestion des produits par le gérant.
 * Auteur : kit de démarrage fourni par l'enseignant.
 * Réalisation : R1.
 */

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * GET /api/products — Liste paginée des produits actifs de la carte.
     * Accès : public.
     * Paramètres optionnels : category (id de catégorie), page (numéro de page).
     * Réponse : 200, enveloppe {data, error, meta} avec la pagination dans meta.
     * Codes : 200.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $perPage = 20;

        // Filtre facultatif par catégorie. $where ne contient que du texte fixe :
        // la valeur saisie passe par un paramètre lié (?), jamais par concaténation.
        $where = 'WHERE p.active = 1';
        $params = [];

        if ($request->filled('category')) {
            $where .= ' AND p.category_id = ?';
            $params[] = $request->integer('category');
        }

        // Jointure avec categories pour renvoyer le nom de la catégorie sans second appel côté front.
        // INNER JOIN suffit : tout produit a forcément une catégorie (clé étrangère NOT NULL).
        // LIMIT / OFFSET découpe la carte en pages.
        $products = DB::select(
            "SELECT p.id, p.name, p.description, p.price, p.stock, p.category_id, c.name AS category
             FROM products p
             JOIN categories c ON c.id = p.category_id
             $where
             ORDER BY c.name, p.name
             LIMIT ? OFFSET ?",
            [...$params, $perPage, ($page - 1) * $perPage]
        );

        // Nombre total de produits correspondant au filtre, pour la pagination.
        $total = DB::selectOne("SELECT COUNT(*) AS n FROM products p $where", $params)->n;

        return ApiResponse::success($products, [
            'page' => $page,
            'page_size' => $perPage,
            'total_results' => $total,
        ]);
    }

    /**
     * GET /api/products/search — Recherche multi-critères dans la carte.
     * Accès : public.
     * Paramètres optionnels (combinables) :
     *   q (texte dans le nom), min_price, max_price, category (id), available (1 = en stock uniquement), page.
     * Réponse : 200, enveloppe {data, error, meta} avec la pagination dans meta.
     * Codes : 200, 422 paramètre invalide.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'category' => ['nullable', 'integer'],
            'available' => ['nullable', 'boolean'],
        ]);

        $page = max(1, $request->integer('page', 1));
        $perPage = 20;

        // On construit le WHERE critère par critère : chaque filtre n'est ajouté que s'il est fourni.
        // Seul du texte fixe est ajouté à $where, les valeurs vont dans $params.
        $where = 'WHERE p.active = 1';
        $params = [];

        // LIKE '%mot%' : le nom contient le mot cherché, n'importe où.
        // Les % sont ajoutés à la valeur liée, pas collés dans la requête.
        if ($request->filled('q')) {
            $where .= ' AND p.name LIKE ?';
            $params[] = '%'.$request->input('q').'%';
        }

        // Fourchette de prix.
        if ($request->filled('min_price')) {
            $where .= ' AND p.price >= ?';
            $params[] = $request->input('min_price');
        }

        if ($request->filled('max_price')) {
            $where .= ' AND p.price <= ?';
            $params[] = $request->input('max_price');
        }

        if ($request->filled('category')) {
            $where .= ' AND p.category_id = ?';
            $params[] = $request->integer('category');
        }

        // Disponibilité : uniquement les produits encore en stock.
        if ($request->boolean('available')) {
            $where .= ' AND p.stock > 0';
        }

        // Même requête que la liste de la carte, avec les filtres de recherche en plus.
        $products = DB::select(
            "SELECT p.id, p.name, p.description, p.price, p.stock, p.category_id, c.name AS category
             FROM products p
             JOIN categories c ON c.id = p.category_id
             $where
             ORDER BY p.name
             LIMIT ? OFFSET ?",
            [...$params, $perPage, ($page - 1) * $perPage]
        );

        $total = DB::selectOne("SELECT COUNT(*) AS n FROM products p $where", $params)->n;

        return ApiResponse::success($products, [
            'page' => $page,
            'page_size' => $perPage,
            'total_results' => $total,
        ]);
    }

    /**
     * GET /api/products/{id} — Détail d'un produit.
     * Accès : public.
     * Paramètre d'URL : id (entier).
     * Réponse : 200 avec le produit et le nom de sa catégorie.
     * Codes : 200, 404 produit inexistant.
     */
    public function show(int $id): JsonResponse
    {
        // Pas de filtre sur active : un produit retiré reste consultable,
        // par exemple depuis l'historique des commandes d'un client.
        $product = DB::selectOne(
            'SELECT p.id, p.name, p.description, p.price, p.stock, p.active, p.category_id, c.name AS category
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?',
            [$id]
        );

        if ($product === null) {
            return ApiResponse::error(404, 'produit-introuvable', 'Produit introuvable.', "Aucun produit n'a l'identifiant ".$id.'.');
        }

        return ApiResponse::success($product);
    }

    /**
     * POST /api/products — Ajoute un produit à la carte.
     * Accès : admin.
     * Corps JSON : category_id, name, description, price, stock.
     * Réponse : 201 avec le produit créé.
     * Codes : 201, 401, 403, 422 données invalides (catégorie inexistante, prix <= 0…).
     */
    public function store(Request $request): JsonResponse
    {
        // exists:categories,id : la catégorie doit exister, sinon 422.
        // gt:0 et min:0 reprennent les contraintes CHECK de la base pour renvoyer un message clair.
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'gt:0'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        // Insère le produit, actif par défaut (valeur par défaut de la colonne active).
        DB::insert(
            'INSERT INTO products (category_id, name, description, price, stock, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$validated['category_id'], $validated['name'], $validated['description'], $validated['price'], $validated['stock'], now(), now()]
        );

        $id = (int) DB::getPdo()->lastInsertId();

        return $this->show($id)->setStatusCode(201);
    }

    /**
     * PUT /api/products/{id} — Modifie un produit (prix, stock, catégorie, remise en carte…).
     * Accès : admin.
     * Paramètre d'URL : id (entier). Corps JSON : category_id, name, description, price, stock, active.
     * Réponse : 200 avec le produit modifié.
     * Codes : 200, 401, 403, 404 produit inexistant, 422 données invalides.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'gt:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'active' => ['required', 'boolean'],
        ]);

        // Vérifie que le produit à modifier existe.
        $product = DB::selectOne('SELECT id FROM products WHERE id = ?', [$id]);

        if ($product === null) {
            return ApiResponse::error(404, 'produit-introuvable', 'Produit introuvable.', "Aucun produit n'a l'identifiant ".$id.'.');
        }

        // Modifie toutes les colonnes du produit. Changer le prix ici ne touche pas
        // aux commandes déjà passées : elles gardent le prix du moment de la commande.
        DB::update(
            'UPDATE products
             SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, active = ?, updated_at = ?
             WHERE id = ?',
            [$validated['category_id'], $validated['name'], $validated['description'], $validated['price'], $validated['stock'], $validated['active'], now(), $id]
        );

        return $this->show($id);
    }

    /**
     * DELETE /api/products/{id} — Retire un produit de la carte (suppression logique).
     * Accès : admin.
     * Paramètre d'URL : id (entier).
     * Réponse : 200, data = { message }.
     * Codes : 200, 401, 403, 404 produit inexistant.
     */
    public function destroy(int $id): JsonResponse
    {
        // On vérifie l'existence avec un SELECT et pas avec le nombre de lignes modifiées :
        // en MySQL, un UPDATE qui ne change rien (produit déjà retiré) renvoie 0 ligne modifiée.
        $product = DB::selectOne('SELECT id FROM products WHERE id = ?', [$id]);

        if ($product === null) {
            return ApiResponse::error(404, 'produit-introuvable', 'Produit introuvable.', "Aucun produit n'a l'identifiant ".$id.'.');
        }

        // Suppression logique : la ligne reste en base (elle a pu être commandée),
        // mais active = 0 la fait disparaître de la carte.
        DB::update('UPDATE products SET active = 0, updated_at = ? WHERE id = ?', [now(), $id]);

        return ApiResponse::success(['message' => 'Produit retiré de la carte.']);
    }
}
