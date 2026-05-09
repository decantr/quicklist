<?php

use App\Models\Product;
use App\Models\Shoplist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
	actingAs(
		$this->user = User::factory()->create()
	);
});

it('is accessible', function () {
	get(route('shoplists.index'))
		->assertOk()
		->assertSee('My Shop Lists');
});

it('displays empty message when no shop lists exist', function () {
	livewire('pages::shoplist.index')
		->assertSee('No shop lists found.');
});

it('displays user shop lists', function () {
	$otherUser = User::factory()->create();
	Shoplist::factory()
		->sequence(
			['user_id' => $this->user->id, 'date' => '2026-04-01'],
			['user_id' => $this->user->id, 'date' => '2026-04-02'],
			['user_id' => $otherUser->id, 'date' => '2026-04-03']
		)
		->count(3)
		->create();

	livewire('pages::shoplist.index')
		->assertSee('Apr 01, 2026')
		->assertSee('Apr 02, 2026')
		->assertDontSee('Apr 03, 2026');
});

it('can create a shopping list from index page', function () {
	expect(Shoplist::count())->toBe(0);

	livewire('pages::shoplist.index')
		->call('create')
		->assertHasNoErrors();

	expect(Shoplist::count())->toBe(1)
		->and($this->user->shoplists()->count())->toBe(1);
});

it('copies products from the previous list when creating a new one', function () {
	$products = Product::factory()->count(2)->create();

	$previous_list = Shoplist::factory()
		->for($this->user)
		->state([
			'date' => now()->subDay(),
		])
		->create();

	$previous_list->products()->attach([
		$products[0]->id => ['quantity' => 2],
		$products[1]->id => ['quantity' => 1],
	]);

	livewire('pages::shoplist.index')
		->call('create')
		->assertHasNoErrors();

	$newList = Shoplist::where('user_id', $this->user->id)
		->where('date', today())
		->first();

	expect($newList->products)->toHaveCount(2)
		->and($newList->products[0]->pivot->quantity)->toBe(2)
		->and($newList->products[1]->pivot->quantity)->toBe(1);
});

it('displays correct product count', function () {
	$shoplist = Shoplist::factory()
		->for($this->user)
		->create();
	$products = Product::factory()->count(3)->create();

	$shoplist->products()->attach(
		$products->pluck('id')->mapWithKeys(fn($id) => [$id => ['quantity' => 1]])->toArray()
	);

	livewire('pages::shoplist.index')
		->assertSee('3 products');
});

it('can delete a shopping list', function () {
	$shoplist = Shoplist::factory()->create(['user_id' => $this->user->id]);

	livewire('pages::shoplist.index')
		->call('delete', $shoplist->id)
		->assertHasNoErrors();

	$this->assertDatabaseMissing('shoplists', [
		'id' => $shoplist->id,
	]);
});
