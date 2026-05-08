<?php

use App\Models\Product;
use App\Models\Shoplist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

describe('unauthenticated', function () {
	it('redirects guests to the login page', function () {
		$this->get(route('dashboard'))
			->assertRedirect(route('login'));
	});
});

describe('authenticated', function () {
	beforeEach(function () {
		actingAs(
			$this->user = User::factory()->create()
		);
	});

	it('allows authenticated users to visit the dashboard', function () {
		get(route('dashboard'))
			->assertOk();
	});

	it('displays the latest shopping list', function () {
		$shoplist = Shoplist::factory()->create([
			'user_id' => $this->user->id,
			'date' => now()->subDay(),
		]);

		$product = Product::factory()->create(['name' => 'Apple']);
		$shoplist->products()->attach($product->id, ['quantity' => 5]);

		get(route('dashboard'))
			->assertOk()
			->assertSee('Latest Shopping List')
			->assertSee($shoplist->date->format('M d, Y'))
			->assertSee('5x Apple');
	});

	it('shows message when no shopping lists exist', function () {
		get(route('dashboard'))
			->assertOk()
			->assertSee('No shopping lists found.');
	});

	it('shows message when latest shopping list is empty', function () {
		Shoplist::factory()->create([
			'user_id' => $this->user->id,
			'date' => now(),
		]);

		get(route('dashboard'))
			->assertOk()
			->assertSee('No products in the latest shopping list.');
	});
});
