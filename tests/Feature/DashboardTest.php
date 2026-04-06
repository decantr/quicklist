<?php

use App\Models\Product;
use App\Models\Shoplist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
	$response = $this->get(route('dashboard'));
	$response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
	$user = User::factory()->create();
	$this->actingAs($user);

	$response = $this->get(route('dashboard'));
	$response->assertOk();
});

test('dashboard displays the latest shopping list', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => now()->subDay(),
	]);

	$product = Product::factory()->create(['name' => 'Apple']);
	$shoplist->products()->attach($product->id, ['quantity' => 5]);

	$this->actingAs($user)
		->get(route('dashboard'))
		->assertOk()
		->assertSee('Latest Shopping List')
		->assertSee($shoplist->date->format('M d, Y'))
		->assertSee('5x Apple');
});

test('dashboard shows message when no shopping lists exist', function () {
	$user = User::factory()->create();

	$this->actingAs($user)
		->get(route('dashboard'))
		->assertOk()
		->assertSee('No shopping lists found.');
});

test('dashboard shows message when latest shopping list is empty', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => now(),
	]);

	$this->actingAs($user)
		->get(route('dashboard'))
		->assertOk()
		->assertSee('No products in the latest shopping list.');
});

test('dashboard latest shoplist has copy button and add button when products exist', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);
	$product = Product::factory()->create(['name' => 'Apple']);
	$shoplist->products()->attach($product->id, ['quantity' => 5]);

	Livewire::actingAs($user)
		->test('dashboard.latest-shoplist')
		->assertSee('Copy')
		->assertSee('Add Product')
		->assertSee('Apple');
});

test('products can be added to the latest shopping list from the dashboard', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);
	$product = Product::factory()->create(['name' => 'Banana']);

	Livewire::actingAs($user)
		->test('dashboard.latest-shoplist')
		->set('productId', $product->id)
		->set('quantity', 3)
		->call('addProduct')
		->assertHasNoErrors()
		->assertSee('Banana')
		->assertSee('3');

	expect($shoplist->products()->where('product_id', $product->id)->first()->pivot->quantity)->toBe(3);
});

test('products in the latest shopping list can be edited from the dashboard', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);
	$product = Product::factory()->create(['name' => 'Apple']);
	$shoplist->products()->attach($product->id, ['quantity' => 2]);

	Livewire::actingAs($user)
		->test('dashboard.latest-shoplist')
		->assertSee('Apple')
		->assertSee('2')
		->call('editProduct', $product->id, 2)
		->set('editingQuantity', 10)
		->call('updateProduct')
		->assertHasNoErrors()
		->assertSee('10');

	expect($shoplist->products()->where('product_id', $product->id)->first()->pivot->quantity)->toBe(10);
});

test('duplicate products cannot be added to the latest shopping list from the dashboard', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id, 'date' => now()]);
	$product = Product::factory()->create(['name' => 'Milk']);
	$shoplist->products()->attach($product->id, ['quantity' => 1]);

	Livewire::actingAs($user)
		->test('dashboard.latest-shoplist')
		->set('productId', $product->id)
		->set('quantity', 2)
		->call('addProduct')
		->assertHasErrors(['productId']);

	expect($shoplist->products()->where('product_id', $product->id)->first()->pivot->quantity)->toBe(1);
});
