<?php

/**
 * Fichier : EnsureUserHasRole.php
 * Rôle : middleware qui vérifie le rôle de l'utilisateur connecté (client, barista, admin).
 *        Il s'utilise sur un groupe de routes dans routes/api.php :
 *        Route::middleware(['auth:sanctum', 'role:barista,admin'])->group(...)
 * Auteur : kit de démarrage fourni par l'enseignant.
 * Réalisation : R1.
 */

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Laisse passer la requête seulement si le rôle de l'utilisateur fait partie des rôles autorisés.
     *
     * @param  string  ...$roles  rôles autorisés, écrits après "role:" dans la route (ex. "barista", "admin")
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Sécurité : ce middleware doit être placé après auth:sanctum.
        // S'il est utilisé seul, on refuse plutôt que de laisser passer un inconnu.
        if ($user === null) {
            return ApiResponse::error(401, 'non-authentifie', 'Authentification requise.', 'Jeton manquant, invalide ou expiré.');
        }

        // Connecté, mais pas avec le bon rôle : 403 (et non 401, l'identité est connue).
        if (! in_array($user->role, $roles, true)) {
            return ApiResponse::error(403, 'acces-refuse', 'Accès refusé.', 'Cette action est réservée au rôle : '.implode(', ', $roles).'.');
        }

        return $next($request);
    }
}
