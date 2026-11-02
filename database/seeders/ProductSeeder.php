<?php

/**
 * Fichier : ProductSeeder.php
 * Rôle : remplit la carte avec une vingtaine de produits répartis dans les catégories.
 *        À lancer après CategorySeeder (chaque produit a besoin de sa catégorie).
 * Auteur : kit de démarrage fourni par l'enseignant.
 * Réalisation : R1.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Insère les produits de la carte.
     */
    public function run(): void
    {
        // Produits rangés par nom de catégorie : [nom, description, prix, stock]
        $productsByCategory = [
            'Cafés' => [
                ['Espresso', 'Arabica éthiopien, serré et intense.', 2.20, 200],
                ['Double espresso', 'Deux shots pour bien démarrer.', 3.00, 200],
                ['Cappuccino', 'Espresso, lait mousseux, cacao.', 3.80, 150],
                ['Latte', 'Espresso allongé de lait chaud.', 4.00, 150],
                ['Flat white noisette', 'Lait velouté et sirop noisette.', 4.20, 100],
                ['Moka', 'Espresso, chocolat et lait.', 4.30, 0],
            ],
            'Thés & matcha' => [
                ['Matcha latte', 'Matcha cérémonial d\'Uji et lait.', 4.50, 80],
                ['Chaï latte', 'Thé noir épicé et lait.', 4.20, 80],
                ['Thé vert sencha', 'Thé vert japonais en vrac.', 3.00, 100],
            ],
            'Boissons froides' => [
                ['Cold brew', 'Café infusé à froid pendant 18 h.', 4.00, 60],
                ['Iced latte', 'Latte servi sur glace.', 4.20, 60],
                ['Limonade maison', 'Citron pressé et menthe fraîche.', 3.50, 40],
            ],
            'Pâtisseries' => [
                ['Cookie chocolat', 'Cookie moelleux aux pépites de chocolat.', 2.50, 30],
                ['Muffin myrtille', 'Muffin aux myrtilles fraîches.', 2.80, 20],
                ['Cheesecake', 'Part de cheesecake new-yorkais.', 4.50, 12],
                ['Cinnamon roll', 'Brioche roulée à la cannelle.', 3.20, 15],
            ],
            'Salé' => [
                ['Bagel saumon', 'Saumon fumé, cream cheese, aneth.', 7.50, 15],
                ['Croque-monsieur', 'Jambon, emmental, béchamel.', 6.00, 20],
                ['Quiche lorraine', 'Part de quiche maison.', 5.50, 10],
                ['Wrap végétarien', 'Houmous, crudités et feta.', 6.50, 15],
            ],
        ];

        foreach ($productsByCategory as $categoryName => $products) {
            // Récupère l'id de la catégorie à partir de son nom (créée par CategorySeeder).
            $categoryId = DB::selectOne('SELECT id FROM categories WHERE name = ?', [$categoryName])->id;

            foreach ($products as [$name, $description, $price, $stock]) {
                // Un produit sans stock est créé indisponible (active = 0).
                DB::insert(
                    'INSERT INTO products (category_id, name, description, price, stock, active, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$categoryId, $name, $description, $price, $stock, $stock > 0, now(), now()]
                );
            }
        }
    }
}
