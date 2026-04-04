<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('products page is accessible', function () {
	$user = User::factory()->create();

	$this->actingAs($user)
		->get(route('products.index'))
		->assertOk()
		->assertSee('All Products');
});

test('products page displays products', function () {
	$user = User::factory()->create();
	$product1 = Product::factory()->create(['name' => 'Milk']);
	$product2 = Product::factory()->create(['name' => 'Bread']);

	Livewire::actingAs($user)
		->test('product.index')
		->assertSee('Milk')
		->assertSee('Bread');
});

test('products page displays empty message when no products exist', function () {
	$user = User::factory()->create();

	Livewire::actingAs($user)
		->test('product.index')
		->assertSee('No products found.');
});
