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

test('shopping list show page contains formatted text output separated by category', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);

	$milk = Product::factory()->create([
		'name' => 'Milk',
		'size' => 500,
		'size_type' => \App\Enums\SizeType::Millilitres,
		'category' => \App\Enums\Category::Dairy,
	]);

	$bread = Product::factory()->create([
		'name' => 'Bread',
		'size' => 1,
		'size_type' => \App\Enums\SizeType::Grams, // Assuming factory uses grams as unit
		'category' => \App\Enums\Category::Bakery,
	]);

	$shoplist->products()->attach([
		$milk->id => ['quantity' => 3],
		$bread->id => ['quantity' => 1],
	]);

	Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist])
		->assertSee('3x Milk (500 ml)')
		->assertSee('1x Bread (1 g)')
		->assertSee('Formatted List')
		->assertSee('Copy');

	$component = Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist]);

	$output = $component->get('textOutput');
	expect($output)->toContain('3x Milk (500 ml)')
		->and($output)->toContain('1x Bread (1 g)')
		->and($output)->toMatch('/\n\n/');
});

test('shopping list formatted output handles count size type', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);

	$eggs = Product::factory()->create([
		'name' => 'Eggs',
		'size' => 12,
		'size_type' => \App\Enums\SizeType::Count,
		'category' => \App\Enums\Category::Dairy,
	]);

	$shoplist->products()->attach($eggs->id, ['quantity' => 1]);

	Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist])
		->assertSee('1x Eggs (12 count)');
});

test('shopping list formatted output handles pint size type', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);

	$beer = Product::factory()->create([
		'name' => 'Beer',
		'size' => 1,
		'size_type' => \App\Enums\SizeType::Pint,
		'category' => \App\Enums\Category::Other,
	]);

	$shoplist->products()->attach($beer->id, ['quantity' => 2]);

	Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist])
		->assertSee('2x Beer (1 pt)');
});

test('shopping list formatted output handles fridge category', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);

	$yogurt = Product::factory()->create([
		'name' => 'Yogurt',
		'size' => 500,
		'size_type' => \App\Enums\SizeType::Grams,
		'category' => \App\Enums\Category::Fridge,
	]);

	$shoplist->products()->attach($yogurt->id, ['quantity' => 1]);

	Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist])
		->assertSee('Yogurt')
		->assertSee('Fridge');
});

test('shopping list date can be updated', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => '2026-04-01',
	]);

	Livewire::actingAs($user)
		->test('shoplist.show', ['shoplist' => $shoplist])
		->set('date', '2026-04-10')
		->call('updateDate')
		->assertHasNoErrors()
		->assertSee('Apr 10, 2026');

	expect($shoplist->fresh()->date->format('Y-m-d'))->toBe('2026-04-10');
});
