<?php

use App\Enums\Category;
use App\Enums\SizeType;
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
	$shoplist = Shoplist::factory()
		->for($this->user)
		->create();

	get(route('shoplists.show', $shoplist))
		->assertOk()
		->assertSee($shoplist->date->format('M d, Y'));
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

	livewire('pages::shoplist.show', ['shoplist' => $shoplist])
		->assertSee('Milk')
		->assertSee('2')
		->assertSee('Bread')
		->assertSee('1');
});

it('shows empty message when no products', function () {
	$shoplist = Shoplist::factory()->for($this->user)->create();

	livewire('pages::shoplist.show', ['shoplist' => $shoplist])
		->assertSee('No products in this shopping list.');
});

it('can update the date', function () {
	$shoplist = Shoplist::factory()->create([
		'user_id' => $this->user->id,
		'date' => '2026-04-01',
	]);

	livewire('pages::shoplist.show', ['shoplist' => $shoplist])
		->set('date', '2026-04-10')
		->call('updateDate')
		->assertHasNoErrors()
		->assertSee('Apr 10, 2026');

	expect($shoplist->fresh()->date->format('Y-m-d'))->toBe('2026-04-10');
});

it('can update product quantity via table action', function () {
	$shoplist = Shoplist::factory()->for($this->user)->create();
	$product = Product::factory()->create(['name' => 'Apple']);
	$shoplist->products()->attach($product->id, ['quantity' => 1]);

	livewire('pages::shoplist.show', ['shoplist' => $shoplist])
		->assertSee('Apple')
		->assertSee('1')
		->call('editProduct', $product->id, 1)
		->set('editingQuantity', 10)
		->call('updateProduct')
		->assertHasNoErrors()
		->assertSee('Apple')
		->assertSee('10');

	expect($shoplist->products()->count())->toBe(1)
		->and($shoplist->products()->where('product_id', $product->id)->first()->pivot->quantity)->toBe(10);
});

it('can remove products from shopping list', function () {
	$shoplist = Shoplist::factory()->for($this->user)->create();
	$product = Product::factory()->create(['name' => 'Apple']);
	$shoplist->products()->attach($product->id, ['quantity' => 1]);

	livewire('pages::shoplist.show', ['shoplist' => $shoplist])
		->assertSee('Apple')
		->call('removeProduct', $product->id)
		->assertHasNoErrors();

	expect($shoplist->products()->count())->toBe(0);
});

it('can add products to shopping list with quantity', function () {
	$shoplist = Shoplist::factory()->for($this->user)->create();
	$product = Product::factory()->create(['name' => 'Apple']);

	$parent = livewire('pages::shoplist.show', ['shoplist' => $shoplist]);

	livewire('shoplist.add-product', ['shoplist' => $shoplist])
		->set('productId', $product->id)
		->set('quantity', 5)
		->call('addProduct')
		->assertHasNoErrors();

	$parent->refresh()
		->assertSee('Apple')
		->assertSee('5');

	expect($shoplist->products()->where('product_id', $product->id)->exists())->toBeTrue()
		->and($shoplist->products()->where('product_id', $product->id)->first()->pivot->quantity)->toBe(5);
});

it('cannot add duplicate products to shopping list', function () {
	$shoplist = Shoplist::factory()->for($this->user)->create();
	$product = Product::factory()->create(['name' => 'Apple']);
	$shoplist->products()->attach($product->id, ['quantity' => 1]);

	livewire('shoplist.add-product', ['shoplist' => $shoplist])
		->set('productId', $product->id)
		->set('quantity', 10)
		->call('addProduct')
		->assertHasErrors(['productId']);

	expect($shoplist->products()->count())->toBe(1)
		->and($shoplist->products()->where('product_id', $product->id)->first()->pivot->quantity)->toBe(1);
});

describe('copy output', function () {
	it('contains formatted text output separated by category', function () {
		$shoplist = Shoplist::factory()->for($this->user)->create();

		$milk = Product::factory()
			->state([
				'name' => 'Milk',
				'size' => 500,
				'size_type' => SizeType::Millilitres,
				'category' => Category::Dairy,
			])
			->create();

		$bread = Product::factory()
			->state([
				'name' => 'Bread',
				'size' => 1,
				'size_type' => SizeType::Grams,
				'category' => Category::Bakery,
			])
			->create();

		$shoplist->products()->attach([
			$milk->id => ['quantity' => 3],
			$bread->id => ['quantity' => 1],
		]);

		livewire('pages::shoplist.show', ['shoplist' => $shoplist])
			->assertSee('3x Milk (500 ml)')
			->assertSee('1x Bread (1 g)')
			->assertSee('Formatted List')
			->assertSee('Copy');

		$component = livewire('pages::shoplist.show', ['shoplist' => $shoplist]);

		$output = $component->get('textOutput');
		expect($output)->toContain('3x Milk (500 ml)')
			->and($output)->toContain('1x Bread (1 g)')
			->and($output)->toMatch('/\n\n/');
	});

	it('formatted output handles count size type', function () {
		$shoplist = Shoplist::factory()->for($this->user)->create();

		$eggs = Product::factory()->create([
			'name' => 'Eggs',
			'size' => 12,
			'size_type' => SizeType::Count,
			'category' => Category::Dairy,
		]);

		$shoplist->products()->attach($eggs->id, ['quantity' => 1]);

		livewire('pages::shoplist.show', ['shoplist' => $shoplist])
			->assertSee('1x Eggs (12 count)');
	});

	it('formatted output handles pint size type', function () {
		$shoplist = Shoplist::factory()->for($this->user)->create();

		$beer = Product::factory()->create([
			'name' => 'Beer',
			'size' => 1,
			'size_type' => SizeType::Pint,
			'category' => Category::Other,
		]);

		$shoplist->products()->attach($beer->id, ['quantity' => 2]);

		livewire('pages::shoplist.show', ['shoplist' => $shoplist])
			->assertSee('2x Beer (1 pt)');
	});

	it('formatted output handles fridge category', function () {
		$shoplist = Shoplist::factory()->for($this->user)->create();

		$yogurt = Product::factory()->create([
			'name' => 'Yogurt',
			'size' => 500,
			'size_type' => SizeType::Grams,
			'category' => Category::Fridge,
		]);

		$shoplist->products()->attach($yogurt->id, ['quantity' => 1]);

		livewire('pages::shoplist.show', ['shoplist' => $shoplist])
			->assertSee('Yogurt')
			->assertSee('Fridge');
	});
});
