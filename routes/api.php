<?php

/**
 * Fichier : api.php
 * Rôle : déclare toutes les routes de l'API BeanQuest (préfixe /api ajouté automatiquement).
 *        Les droits sont contrôlés par groupes de routes :
 *          - auth:sanctum   -> il faut un jeton valide (sinon 401) ;
 *          - role:xxx       -> il faut en plus le bon rôle (sinon 403).
 * Auteur : kit de démarrage fourni par l'enseignant.
 * Réalisation : R1.
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Routes publiques (aucun jeton nécessaire)
// ---------------------------------------------------------------------------

Route::post('/register', [AuthController::class, 'register']);
// throttle:10,1 : 10 tentatives par minute maximum, contre les attaques par force brute.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('/categories', [CategoryController::class, 'index']);
// whereNumber : /api/categories/abc renvoie un 404 au lieu d'appeler show() avec un texte.
Route::get('/categories/{id}', [CategoryController::class, 'show'])->whereNumber('id');

Route::get('/products', [ProductController::class, 'index']);
// /products/search est déclarée avant /products/{id} pour que "search" ne soit pas pris pour un id.
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/products/{id}', [ProductController::class, 'show'])->whereNumber('id');

// ---------------------------------------------------------------------------
// Routes réservées aux utilisateurs connectés
// ---------------------------------------------------------------------------

Route::middleware('auth:sanctum')->group(function () {
    // Tous les rôles
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Clients uniquement
    Route::middleware('role:client')->group(function () {
        // À ajouter : POST /api/orders, GET /api/orders/me
    });

    // Personnel du café (barista et gérant)
    Route::middleware('role:barista,admin')->group(function () {
        // À ajouter : GET /api/orders?status=, PATCH /api/orders/{id}/status,
        //             GET /api/orders/{id}/history
    });

    // Gérant uniquement
    Route::middleware('role:admin')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update'])->whereNumber('id');
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->whereNumber('id');

        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update'])->whereNumber('id');
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->whereNumber('id');

        // À ajouter : GET /api/stats/today
    });
});
