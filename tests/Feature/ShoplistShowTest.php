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
