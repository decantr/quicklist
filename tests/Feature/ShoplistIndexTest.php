<?php

use App\Models\Product;
use App\Models\Shoplist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('shop lists page is accessible', function () {
	$user = User::factory()->create();

	$this->actingAs($user)
		->get(route('shoplists.index'))
		->assertOk()
		->assertSee('My Shop Lists');
});

test('shop lists page displays user shop lists', function () {
	$user = User::factory()->create();
	$shoplist1 = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => '2026-04-01',
	]);
	$shoplist2 = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => '2026-04-02',
	]);

	$otherUser = User::factory()->create();
	$otherShoplist = Shoplist::factory()->create([
		'user_id' => $otherUser->id,
		'date' => '2026-04-03',
	]);

	Livewire::actingAs($user)
		->test('shoplist.index')
		->assertSee('Apr 01, 2026')
		->assertSee('Apr 02, 2026')
		->assertDontSee('Apr 03, 2026');
});

test('a shopping list can be created from index page', function () {
	$user = User::factory()->create();
	$today = now()->format('Y-m-d');

	Livewire::actingAs($user)
		->test('shoplist.index')
		->call('create')
		->assertHasNoErrors();

	$this->assertDatabaseHas('shoplists', [
		'user_id' => $user->id,
		'date' => $today.' 00:00:00',
	]);
});

test('a shopping list copies products from the previous list', function () {
	$user = User::factory()->create();
	$products = Product::factory()->count(2)->create();

	$previousList = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => now()->subDay()->format('Y-m-d'),
	]);

	$previousList->products()->attach([
		$products[0]->id => ['quantity' => 2],
		$products[1]->id => ['quantity' => 1],
	]);

	Livewire::actingAs($user)
		->test('shoplist.index')
		->call('create')
		->assertHasNoErrors();

	$newList = Shoplist::where('user_id', $user->id)
		->where('date', now()->format('Y-m-d').' 00:00:00')
		->first();

	expect($newList->products)->toHaveCount(2)
		->and($newList->products[0]->pivot->quantity)->toBe(2)
		->and($newList->products[1]->pivot->quantity)->toBe(1);
});

test('shopping list displays correct product count', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);
	$products = Product::factory()->count(3)->create();

	$shoplist->products()->attach(
		$products->pluck('id')->mapWithKeys(fn ($id) => [$id => ['quantity' => 1]])->toArray()
	);

	Livewire::actingAs($user)
		->test('shoplist.index')
		->assertSee('3 products');
});

test('shop lists page displays empty message when no shop lists exist', function () {
	$user = User::factory()->create();

	Livewire::actingAs($user)
		->test('shoplist.index')
		->assertSee('No shop lists found.');
});
