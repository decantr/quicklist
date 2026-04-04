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

test('dashboard latest shoplist has copy button when products exist', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create(['user_id' => $user->id]);
	$product = Product::factory()->create(['name' => 'Apple']);
	$shoplist->products()->attach($product->id, ['quantity' => 5]);

	Livewire::actingAs($user)
		->test('dashboard.latest-shoplist')
		->assertSee('Copy')
		->assertSee('5x Apple');
});
