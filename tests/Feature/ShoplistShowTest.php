<?php

use App\Models\Product;
use App\Models\Shoplist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('shopping list detail page is accessible', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);

	$this->actingAs($user)
		->get(route('shoplists.show', $shoplist))
		->assertOk()
		->assertSee($shoplist->date->format('M d, Y'));
});

test('shopping list detail page displays products and quantities', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);
	$product1 = Product::factory()->create(['name' => 'Milk']);
	$product2 = Product::factory()->create(['name' => 'Bread']);

	$shoplist->products()->attach([
		$product1->id => ['quantity' => 2],
		$product2->id => ['quantity' => 1],
	]);

	Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist])
		->assertSee('Milk')
		->assertSee('2')
		->assertSee('Bread')
		->assertSee('1');
});

test('shopping list detail page shows empty message when no products', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);

	Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist])
		->assertSee('No products in this shopping list.');
});

test('products can be added to shopping list with quantity', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);
	$product = Product::factory()->create(['name' => 'Apple']);

	Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist])
		->set('productId', $product->id)
		->set('quantity', 5)
		->call('addProduct')
		->assertHasNoErrors()
		->assertSee('Apple')
		->assertSee('5');

	expect($shoplist->products()->where('product_id', $product->id)->exists())->toBeTrue();
	expect($shoplist->products()->where('product_id', $product->id)->first()->pivot->quantity)->toBe(5);
});

test('existing products in shopping list can have their quantity updated', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);
	$product = Product::factory()->create(['name' => 'Apple']);
	$shoplist->products()->attach($product->id, ['quantity' => 1]);

	Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist])
		->set('productId', $product->id)
		->set('quantity', 10)
		->call('addProduct')
		->assertHasNoErrors()
		->assertSee('Apple')
		->assertSee('10');

	expect($shoplist->products()->count())->toBe(1);
	expect($shoplist->products()->where('product_id', $product->id)->first()->pivot->quantity)->toBe(10);
});

test('shopping list show page contains formatted text output', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);
	$product = Product::factory()->create([
		'name' => 'Milk',
		'size' => 500,
		'size_type' => \App\Enums\SizeType::Millilitres,
	]);

	$shoplist->products()->attach($product->id, ['quantity' => 3]);

	Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist])
		->assertSee('3x Milk (500 ml)')
		->assertSee('Text Output')
		->assertSeeHtml('readonly')
		->assertSeeHtml('>3x Milk (500 ml)</textarea>');
});
