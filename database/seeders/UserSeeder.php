<?php

/**
 * Fichier : UserSeeder.php
 * Rôle : crée un compte de test pour chaque rôle (mot de passe : "password").
 *          - admin@beanquest.fr   (gérant)
 *          - barista@beanquest.fr (barista)
 *          - client@beanquest.fr  (client)
 * Auteur : kit de démarrage fourni par l'enseignant.
 * Réalisation : R1.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Insère les comptes de test.
     */
    public function run(): void
    {
        $accounts = [
            ['Camille Martin', 'Patron', 'admin@beanquest.fr', 'admin'],
            ['Hugo Bernard', 'BaristaHugo', 'barista@beanquest.fr', 'barista'],
            ['Léa Dubois', 'Lea', 'client@beanquest.fr', 'client'],
        ];

        foreach ($accounts as [$name, $pseudo, $email, $role]) {
            // Même requête que l'inscription, mais ici le rôle est choisi (comptes de test).
            DB::insert(
                'INSERT INTO users (name, pseudo, email, password, role, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [$name, $pseudo, $email, Hash::make('password'), $role]
            );
        }
    }
}