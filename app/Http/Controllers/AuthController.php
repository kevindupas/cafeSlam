<?php

/**
 * Fichier : AuthController.php
 * Rôle : inscription, connexion, déconnexion et profil de l'utilisateur connecté.
 *        L'authentification se fait par jeton (Sanctum) : le front envoie
 *        l'en-tête "Authorization: Bearer <jeton>" à chaque requête protégée.
 * Auteur : kit de démarrage fourni par l'enseignant.
 * Réalisation : R1.
 */

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/register — Crée un compte client et renvoie un jeton.
     * Accès : public.
     * Corps JSON : name, pseudo, email, password.
     * Réponse : 201 avec { token, user }.
     * Codes : 201 créé, 409 email ou pseudo déjà utilisé, 422 données invalides.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'pseudo' => ['required', 'string', 'min:3', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // Vérifie que l'email et le pseudo sont libres, pour renvoyer un message clair.
        // C'est une règle métier (doublon) et non une erreur de saisie : d'où le 409 et pas le 422.
        // La contrainte UNIQUE de la base reste le vrai garde-fou si deux inscriptions arrivent en même temps.
        $existing = DB::selectOne(
            'SELECT email, pseudo FROM users WHERE email = ? OR pseudo = ?',
            [$validated['email'], $validated['pseudo']]
        );

        if ($existing !== null) {
            $field = $existing->email === $validated['email'] ? 'Cet email' : 'Ce pseudo';

            return ApiResponse::error(409, 'compte-existant', 'Compte déjà existant.', $field.' est déjà utilisé.');
        }

        // Insère le compte. Le mot de passe est haché (jamais stocké en clair).
        // Le rôle est forcé à "client" : on ne fait jamais confiance à un rôle envoyé par le front,
        // sinon n'importe qui pourrait s'inscrire en admin.
        DB::insert(
            "INSERT INTO users (name, pseudo, email, password, role, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'client', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
            [$validated['name'], $validated['pseudo'], $validated['email'], Hash::make($validated['password'])]
        );

        $userId = (int) DB::getPdo()->lastInsertId();

        return ApiResponse::success([
            'token' => $this->createToken($userId),
            'user' => $this->findPublicProfile($userId),
        ], status: 201);
    }

    /**
     * POST /api/login — Vérifie l'email et le mot de passe puis renvoie un jeton.
     * Accès : public (limité à 10 tentatives par minute, voir routes/api.php).
     * Corps JSON : email, password.
     * Réponse : 200 avec { token, user }.
     * Codes : 200, 401 identifiants incorrects, 422 données invalides, 429 trop de tentatives.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Récupère le compte et son mot de passe haché à partir de l'email.
        $user = DB::selectOne('SELECT id, password FROM users WHERE email = ?', [$validated['email']]);

        // Hash::check compare le mot de passe saisi au hash enregistré.
        // Même message si l'email ou le mot de passe est faux : on ne révèle pas quels emails existent.
        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error(401, 'identifiants-invalides', 'Identifiants invalides.', 'Email ou mot de passe incorrect.');
        }

        return ApiResponse::success([
            'token' => $this->createToken($user->id),
            'user' => $this->findPublicProfile($user->id),
        ]);
    }

    /**
     * POST /api/logout — Révoque le jeton utilisé pour cette requête.
     * Accès : utilisateur connecté (tous rôles).
     * Réponse : 200, data = { message }.
     * Codes : 200, 401 jeton absent ou invalide.
     */
    public function logout(Request $request): JsonResponse
    {
        // Supprime uniquement le jeton courant : les autres appareils restent connectés.
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(['message' => 'Déconnexion réussie.']);
    }

    /**
     * GET /api/me — Renvoie le profil de l'utilisateur connecté.
     * Sert au front à savoir qui est connecté et avec quel rôle (au rechargement de la page par exemple).
     * Accès : utilisateur connecté (tous rôles).
     * Réponse : 200 avec { id, name, pseudo, email, role }.
     * Codes : 200, 401 jeton absent ou invalide.
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($this->findPublicProfile($request->user()->id));
    }

    /**
     * Crée un jeton Sanctum pour l'utilisateur.
     * Seul endroit où l'on passe par Eloquent : createToken() est une méthode du modèle User.
     */
    private function createToken(int $userId): string
    {
        return User::findOrFail($userId)->createToken('api')->plainTextToken;
    }

    /**
     * Lit les informations d'un compte qu'on peut renvoyer au front.
     * On liste les colonnes une par une : le mot de passe haché ne doit jamais sortir de l'API.
     */
    private function findPublicProfile(int $userId): object
    {
        return DB::selectOne('SELECT id, name, pseudo, email, role FROM users WHERE id = ?', [$userId]);
    }
}
