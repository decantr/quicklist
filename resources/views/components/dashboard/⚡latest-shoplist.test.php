<?php

use App\Models\Product;
use App\Models\Shoplist;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
	actingAs(
		$this->user = User::factory()->create()
	);
});

it('shows message when no shopping lists exist', function () {
	livewire('pages::dashboard')
		->assertOk()
		->assertSee('No shopping lists found.');
});

it('shows message when latest shopping list is empty', function () {
	Shoplist::factory()
		->state([
			'date' => now(),
		])
		->for($this->user)
		->create();

	livewire('pages::dashboard')
		->assertOk()
		->assertSee('No products in the latest shopping list.');
});

it('displays the latest shopping list', function () {
	$shoplist = Shoplist::factory()
		->state([
			'date' => now()->subDay(),
		])
		->for($this->user)
		->create();

	$product = Product::factory()->create(['name' => 'Apple']);
	$shoplist->products()->attach($product->id, ['quantity' => 5]);

	livewire('dashboard.latest-shoplist', ['latestShoplist' => $shoplist])
		->assertOk()
		->assertSee('Latest Shopping List')
		->assertSee($shoplist->date->format('M d, Y'))
		->assertSee('5x Apple');
});
