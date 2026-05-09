<?php

use App\Models\Product;
use App\Models\Shoplist;
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

it('displays products and quantities', function () {
	$shoplist = Shoplist::factory()
		->for($this->user)
		->create();

	$product1 = Product::factory()->create(['name' => 'Milk']);
	$product2 = Product::factory()->create(['name' => 'Bread']);

	$shoplist->products()->attach([
		$product1->id => ['quantity' => 2],
		$product2->id => ['quantity' => 1],
	]);

	livewire('shoplist.products-table', ['shoplist' => $shoplist])
		->assertSee('Milk')
		->assertSee('2')
		->assertSee('Bread')
		->assertSee('1');
});

it('can remove products from shopping list', function () {
	$shoplist = Shoplist::factory()->for($this->user)->create();
	$product = Product::factory()->create(['name' => 'Apple']);
	$shoplist->products()->attach($product->id, ['quantity' => 1]);

	livewire('shoplist.products-table', ['shoplist' => $shoplist])
		->assertSee('Apple')
		->call('removeProduct', $product->id)
		->assertHasNoErrors()
		->assertDispatched('product-updated');

	expect($shoplist->fresh()->products()->count())->toBe(0);
});
