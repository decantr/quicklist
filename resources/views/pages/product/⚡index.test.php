<?php

use App\Enums\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
	actingAs(
		$this->user = User::factory()->create()
	);
});

it('is accessible', function () {
	get(route('products.index'))
		->assertOk()
		->assertSee('All Products');
});

it('displays empty message when no products exist', function () {
	livewire('pages::product.index')
		->assertSee('No products found.');
});

it('displays products', function () {
	Product::factory()
		->sequence(
			['name' => 'Milk'],
			['name' => 'Bread'],
		)
		->count(2)
		->create();

	livewire('pages::product.index')
		->assertSee('Milk')
		->assertSee('Bread');
});

it('can edit products', function () {
	$product = Product::factory()
		->state(['name' => 'Milk'])
		->create();

	livewire('pages::product.index')
		->assertSet('editingProduct', null)
		->call('edit', $product->id)
		->assertSet('editingProduct.id', $product->id);
});

it('can filter by category', function () {
	Product::factory()
		->sequence(
			['name' => 'Milk', 'category' => Category::Dairy],
			['name' => 'Bread', 'category' => Category::Bakery]
		)
		->count(2)
		->create();

	livewire('pages::product.index')
		->assertSee('Milk')
		->assertSee('Bread')
		->set('category', Category::Dairy->value)
		->assertSee('Milk')
		->assertDontSee('Bread')
		->set('category', Category::Bakery->value)
		->assertDontSee('Milk')
		->assertSee('Bread')
		->set('category', '')
		->assertSee('Milk')
		->assertSee('Bread');
});

it('can delete product', function () {
	$product = Product::factory()->create();

	livewire('pages::product.index')
		->call('confirmDelete', $product->id)
		->call('delete')
		->assertHasNoErrors()
		->assertDispatched('modal-close', name: 'confirm-product-deletion');

	$this->assertDatabaseMissing('products', [
		'id' => $product->id,
	]);
});

it('can cancel product deletion', function () {
	$product = Product::factory()->create();

	livewire('pages::product.index')
		->set('productToDelete', $product)
		->call('cancelDelete')
		->assertSet('productToDelete', null);

	$this->assertDatabaseHas('products', [
		'id' => $product->id,
	]);
});
