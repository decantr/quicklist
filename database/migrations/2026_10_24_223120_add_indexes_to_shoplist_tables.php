<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		// The Go app creates these same indexes on startup, so each one may already exist.
		if (! Schema::hasIndex('product_shoplist', 'product_shoplist_shoplist_id_product_id_unique')) {
			Schema::table('product_shoplist', function (Blueprint $table) {
				$table->unique(['shoplist_id', 'product_id']);
			});
		}

		if (! Schema::hasIndex('product_shoplist', 'product_shoplist_product_id_index')) {
			Schema::table('product_shoplist', function (Blueprint $table) {
				$table->index('product_id');
			});
		}

		if (! Schema::hasIndex('shoplists', 'shoplists_user_id_index')) {
			Schema::table('shoplists', function (Blueprint $table) {
				$table->index('user_id');
			});
		}

		if (! Schema::hasIndex('products', 'products_name_size_size_type_unique')) {
			Schema::table('products', function (Blueprint $table) {
				$table->unique(['name', 'size', 'size_type']);
			});
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::table('product_shoplist', function (Blueprint $table) {
			$table->dropUnique(['shoplist_id', 'product_id']);
			$table->dropIndex(['product_id']);
		});

		Schema::table('shoplists', function (Blueprint $table) {
			$table->dropIndex(['user_id']);
		});

		Schema::table('products', function (Blueprint $table) {
			$table->dropUnique(['name', 'size', 'size_type']);
		});
	}
};
