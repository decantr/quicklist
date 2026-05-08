<?php

use App\Models\Product;
use App\Models\Shoplist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
	$this->user = User::factory()->create();
	$this->shoplist = Shoplist::factory()
		->for($this->user)
		->create();
});

it('computes products correctly', function () {
	$products = Product::factory()
		->sequence(
			['name' => 'Apple'],
			['name' => 'Banana'],
		)
		->count(2)
		->create();

	$this->shoplist
		->products()
		->attach($products->first()->id, ['quantity' => 1]);

	$component = livewire('shoplist.add-product', ['shoplist' => $this->shoplist])
		->assertCount('products', 1);

	expect($component->get('products')->first()->id)->toBe($products->last()->id);
});

it('adds a product to the shoplist', function () {
	$product = Product::factory()->create(['name' => 'Orange']);

	livewire('shoplist.add-product', ['shoplist' => $this->shoplist])
		->set('productId', $product->id)
		->set('quantity', 3)
		->call('addProduct')
		->assertHasNoErrors()
		->assertDispatched('product-added');

	$this->shoplist->fresh();

	expect($this->shoplist->products)->toHaveCount(1)
		->and($this->shoplist->products->first()->pivot->quantity)->toBe(3);
});

it('validates required fields', function () {
	livewire('shoplist.add-product', ['shoplist' => $this->shoplist])
		->set('productId', null)
		->set('quantity', 0)
		->call('addProduct')
		->assertHasErrors(['productId' => 'required', 'quantity' => 'min']);
});

it('validates duplicate products', function () {
	$product = Product::factory()->create();

	$this->shoplist->products()->attach($product->id, ['quantity' => 1]);

	livewire('shoplist.add-product', ['shoplist' => $this->shoplist])
		->set('productId', $product->id)
		->call('addProduct')
		->assertHasErrors(['productId']);
});
