<?php

use App\Models\Product;
use App\Models\Shoplist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

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
			->assertSee('No products in this shopping list.');
	});
});

describe('stats', function () {
	beforeEach(function () {
		actingAs(
			$this->user = User::factory()->create()
		);
	});

	it('displays the correct stats on the dashboard', function () {
		Shoplist::factory()->for($this->user)->count(2)->create();

		Product::factory()->count(5)->create();

		$products = Product::all();
		$this->user->shoplists->first()->products()->attach($products[0]->id, ['quantity' => 10]);
		$this->user->shoplists->first()->products()->attach($products[1]->id, ['quantity' => 5]);

		livewire('dashboard.stats.total-lists')
			->assertSee('2');

		livewire('dashboard.stats.total-products')
			->assertSee('5');

		livewire('dashboard.stats.total-items')
			->assertSee('15');
	});

	it('refreshes stats when events are dispatched', function () {
		$listComponent = livewire('dashboard.stats.total-lists')
			->assertSee('0');

		Shoplist::factory()->for($this->user)->create();
		$listComponent->dispatch('shoplist-created')
			->assertSee('1');

		$productComponent = livewire('dashboard.stats.total-products')
			->assertSee('0');

		Product::factory()->create();
		$productComponent->dispatch('product-created')
			->assertSee('1');

		$itemsComponent = livewire('dashboard.stats.total-items')
			->assertSee('0');

		$shoplist = Shoplist::first();
		$product = Product::first();
		$shoplist->products()->attach($product->id, ['quantity' => 3]);

		$itemsComponent->dispatch('product-added')
			->assertSee('3');
	});
});
