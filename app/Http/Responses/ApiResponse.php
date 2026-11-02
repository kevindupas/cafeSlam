<?php

/**
 * Fichier : ApiResponse.php
 * Rôle : unique classe qui fabrique les réponses JSON de l'API BeanQuest.
 *        Toutes les réponses (succès comme erreurs) ont la même enveloppe :
 *        { "data": ..., "error": ..., "meta": ... }
 *        C'est le contrat entre l'API et ses clients (web en R1, Java en R2).
 * Auteur : kit de démarrage fourni par l'enseignant.
 * Réalisation : R1.
 */

namespace App\Http\Responses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiResponse
{
    /**
     * Préfixe des URI qui identifient chaque type d'erreur (champ "type" de la RFC 9457).
     */
    private const ERROR_TYPE_BASE_URL = 'http://cafeslam.test/';

    /**
     * Réponse de succès.
     *
     * Exemple : return ApiResponse::success($products, ['page' => 2, 'page_size' => 20, 'total_results' => 57]);
     *
     * @param  mixed  $data  les données demandées (tableau, objet, ou null)
     * @param  array<string, mixed>  $meta  informations complémentaires (pagination…)
     * @param  int  $status  200 par défaut, 201 après une création
     */
    public static function success(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'error' => null,
            // Le copyright est toujours présent, la pagination est ajoutée si on la fournit.
            'meta' => ['copyright' => 'BeanQuest', ...$meta],
        ], $status);
    }

    /**
     * Réponse d'erreur au format RFC 9457 (type, title, status, detail).
     *
     * Exemple : return ApiResponse::error(409, 'stock-insuffisant', 'Stock insuffisant.', 'Il reste 1 cookie, 3 demandés.');
     *
     * @param  int  $status  code HTTP (401, 403, 404, 409, 422, 500…)
     * @param  string  $type  identifiant court de l'erreur, transformé en URI (ex. "stock-insuffisant")
     * @param  string  $title  résumé lisible, identique pour toutes les erreurs de ce type
     * @param  string  $detail  explication propre à ce cas précis, affichée par le front
     * @param  array<string, mixed>  $extensions  champs supplémentaires autorisés par la RFC 9457 (ex. "errors" pour le 422)
     */
    public static function error(int $status, string $type, string $title, string $detail, array $extensions = []): JsonResponse
    {
        return response()->json([
            'data' => null,
            'error' => [
                'type' => self::ERROR_TYPE_BASE_URL.$type,
                'title' => $title,
                'status' => $status,
                'detail' => $detail,
                ...$extensions,
            ],
            'meta' => null,
        ], $status);
    }

    /**
     * Transforme une exception levée n'importe où dans l'API en réponse d'erreur avec l'enveloppe.
     * Appelée depuis bootstrap/app.php : les contrôleurs n'ont donc pas besoin de try/catch
     * pour les cas courants (jeton absent, validation, route inconnue…).
     */
    public static function fromException(Throwable $exception): JsonResponse
    {
        // 422 : $request->validate() a échoué. On renvoie aussi la liste des erreurs par champ
        // pour que le front puisse les afficher sous chaque champ du formulaire.
        if ($exception instanceof ValidationException) {
            return self::error(422, 'donnees-invalides', 'Données invalides.', $exception->getMessage(), [
                'errors' => $exception->errors(),
            ]);
        }

        // 401 : jeton absent, invalide ou révoqué (middleware auth:sanctum).
        if ($exception instanceof AuthenticationException) {
            return self::error(401, 'non-authentifie', 'Authentification requise.', 'Jeton manquant, invalide ou expiré.');
        }

        // 403 : l'utilisateur est connecté mais n'a pas le droit de faire cette action.
        if ($exception instanceof AuthorizationException || $exception instanceof AccessDeniedHttpException) {
            return self::error(403, 'acces-refuse', 'Accès refusé.', 'Votre rôle ne permet pas cette action.');
        }

        // 404 : route inexistante.
        if ($exception instanceof NotFoundHttpException) {
            return self::error(404, 'introuvable', 'Ressource introuvable.', "Cette adresse n'existe pas dans l'API.");
        }

        // 409 : une contrainte UNIQUE de la base a bloqué l'écriture (doublon).
        // C'est le filet de sécurité si deux requêtes arrivent en même temps.
        if ($exception instanceof UniqueConstraintViolationException) {
            return self::error(409, 'doublon', 'Doublon.', 'Cette valeur existe déjà.');
        }

        // Autres erreurs HTTP prévues par Laravel (405 méthode non autorisée, 429 trop de requêtes…).
        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return self::error($status, 'erreur-http', 'Erreur HTTP '.$status.'.', $exception->getMessage() ?: 'Requête refusée.');
        }

        // 500 : erreur imprévue. Le message technique n'est montré qu'en mode debug (développement),
        // jamais en production : il pourrait révéler des informations sur le serveur.
        $detail = config('app.debug') ? $exception->getMessage() : 'Une erreur interne est survenue.';

        return self::error(500, 'erreur-serveur', 'Erreur serveur.', $detail);
    }
}
