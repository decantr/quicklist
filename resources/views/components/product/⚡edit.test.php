<?php

use App\Enums\Category;
use App\Enums\SizeType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
	actingAs(
		$this->user = User::factory()->create()
	);
});

it('can edit product', function () {
	$product = Product::factory()->create();

	livewire('product.edit', ['product' => $product])
		->set('form.name', 'New Name')
		->set('form.size', 200)
		->call('save')
		->assertHasNoErrors()
		->assertDispatched('product-updated')
		->assertDispatched('modal-close', name: 'edit-product');

	$product->refresh();

	expect($product->name)->toBe('New Name')
		->and($product->size)->toBe(200);
});

it('validation works for product editing', function () {
	$product = Product::factory()->create();

	livewire('product.edit', ['product' => $product])
		->set('form.name', '')
		->call('save')
		->assertHasErrors(['form.name' => 'required']);
});

it('can ignore current product in duplicate check when editing', function () {
	$product = Product::factory()
		->state([
			'category' => Category::Bakery,
		])
		->create();

	livewire('product.edit', ['product' => $product])
		->set('form.category', Category::Dairy)
		->call('save')
		->assertHasNoErrors();

	$product->refresh();

	expect($product->category)->toBe(Category::Dairy);
});

it('duplicate product editing is prevented', function () {
	$product1 = Product::factory()
		->state([
			'name' => 'Banana',
			'size' => 150,
			'size_type' => SizeType::Grams,
		])
		->create();

	$product2 = Product::factory()
		->state([
			'name' => 'Apple',
			'size' => 150,
			'size_type' => SizeType::Grams,
		])
		->create();

	livewire('product.edit', ['product' => $product2])
		->set('form.name', $product1->name)
		->set('form.size', $product1->size)
		->call('save')
		->assertHasErrors(['form.name']);

	$product2->refresh();

	expect($product2->name)->not->toBe($product1->name);
});
