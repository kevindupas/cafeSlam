<?php

/**
 * Fichier : CategorySeeder.php
 * Rôle : remplit la carte avec les catégories du café.
 * Auteur : kit de démarrage fourni par l'enseignant.
 * Réalisation : R1.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Insère les catégories de la carte.
     */
    public function run(): void
    {
        $categories = [
            'Cafés' => 'Espresso, cappuccino, latte et cafés filtres.',
            'Thés & matcha' => 'Thés en vrac, chaï et matcha latte.',
            'Boissons froides' => 'Cold brew, frappés et limonades maison.',
            'Pâtisseries' => 'Cookies, muffins et gâteaux du jour.',
            'Salé' => 'Sandwichs, bagels et quiches.',
        ];

        foreach ($categories as $name => $description) {
            DB::insert(
                'INSERT INTO categories (name, description, created_at, updated_at) VALUES (?, ?, ?, ?)',
                [$name, $description, now(), now()]
            );
        }
    }
}
