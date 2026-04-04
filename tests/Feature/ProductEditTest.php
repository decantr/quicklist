<?php

use App\Enums\Category;
use App\Enums\SizeType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('can edit product', function () {
	$user = User::factory()->create();
	$product = Product::factory()->create([
		'name' => 'Old Name',
		'size' => 100,
		'size_type' => SizeType::Grams->value,
		'category' => Category::Produce->value,
	]);

	Livewire::actingAs($user)
		->test('product.edit', ['product' => $product])
		->set('form.name', 'New Name')
		->set('form.size', 200)
		->call('save')
		->assertHasNoErrors()
		->assertDispatched('product-updated')
		->assertDispatched('modal-close', name: 'edit-product');

	$this->assertDatabaseHas('products', [
		'id' => $product->id,
		'name' => 'New Name',
		'size' => 200,
	]);
});

test('validation works for product editing', function () {
	$user = User::factory()->create();
	$product = Product::factory()->create();

	Livewire::actingAs($user)
		->test('product.edit', ['product' => $product])
		->set('form.name', '')
		->call('save')
		->assertHasErrors(['form.name' => 'required']);
});

test('can ignore current product in duplicate check when editing', function () {
	$user = User::factory()->create();
	$product = Product::factory()->create([
		'name' => 'Apple',
		'size' => 150,
		'size_type' => SizeType::Grams->value,
	]);

	// Edit the same product without changing unique fields
	Livewire::actingAs($user)
		->test('product.edit', ['product' => $product])
		->set('form.category', Category::Dairy->value)
		->call('save')
		->assertHasNoErrors();
});

test('duplicate product editing is prevented', function () {
	$user = User::factory()->create();
	Product::factory()->create([
		'name' => 'Banana',
		'size' => 150,
		'size_type' => SizeType::Grams->value,
	]);
	$product = Product::factory()->create([
		'name' => 'Apple',
		'size' => 100,
		'size_type' => SizeType::Grams->value,
	]);

	// Try to edit Apple to Banana (which already exists with 150g)
	Livewire::actingAs($user)
		->test('product.edit', ['product' => $product])
		->set('form.name', 'Banana')
		->set('form.size', 150)
		->call('save')
		->assertHasErrors(['form.name']);
});
