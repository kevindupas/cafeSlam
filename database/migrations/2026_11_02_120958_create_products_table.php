<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Clé étrangère : un produit appartient forcément à une catégorie qui existe
            // restrictOnDelete : la base refuse de supprimer une catégorie qui contient encore des produits
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('description');
            // decimal(6, 2) : prix exact au centime (jusqu'à 9999,99€), jamais de float pour de l'argent
            $table->decimal('price', 6, 2);
            // active = 0 : produit retiré de la carte. On ne supprime jamais un produit :
            // il a pu être commandé, et les anciennes commandes doivent rester lisibles
            $table->integer('stock');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Contraintes CHECK : la base refuse elle-même un prix nul ou négatif et un stock négatif,
        // même si une requête mal écrite essaie de les enregistrer
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_price CHECK (price > 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_stock CHECK (stock >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
