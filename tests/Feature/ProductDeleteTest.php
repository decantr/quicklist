<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('can delete product', function () {
	$user = User::factory()->create();
	$product = Product::factory()->create();

	Livewire::actingAs($user)
		->test('product.index')
		->call('confirmDelete', $product->id)
		->call('delete')
		->assertHasNoErrors()
		->assertDispatched('modal-close', name: 'confirm-product-deletion');

	$this->assertDatabaseMissing('products', [
		'id' => $product->id,
	]);
});

test('can cancel product deletion', function () {
	$user = User::factory()->create();
	$product = Product::factory()->create();

	Livewire::actingAs($user)
		->test('product.index')
		->set('productToDelete', $product)
		->call('cancelDelete')
		->assertSet('productToDelete', null);

	$this->assertDatabaseHas('products', [
		'id' => $product->id,
	]);
});
