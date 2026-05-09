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

it('updates product quantity in the shoplist', function () {
	$product = Product::factory()->create(['name' => 'Orange']);
	$this->shoplist->products()->attach($product->id, ['quantity' => 1]);

	livewire('shoplist.edit-product', ['shoplist' => $this->shoplist])
		->call('edit', $product->id, 5)
		->assertSet('product_id', $product->id)
		->assertSet('quantity', 5)
		->call('save')
		->assertHasNoErrors()
		->assertDispatched('product-updated');

	$this->shoplist->fresh();

	expect($this->shoplist->products->first()->pivot->quantity)->toBe(5);
});

it('opens modal on edit-product event', function () {
	$product = Product::factory()->create();

	livewire('shoplist.edit-product', ['shoplist' => $this->shoplist])
		->dispatch('edit-product', product_id: $product->id, quantity: 10)
		->assertSet('product_id', $product->id)
		->assertSet('quantity', 10)
		->assertDispatched('modal-show', name: 'edit-product');
});

it('validates quantity', function () {
	$product = Product::factory()->create();
	$this->shoplist->products()->attach($product->id, ['quantity' => 1]);

	livewire('shoplist.edit-product', ['shoplist' => $this->shoplist])
		->set('product_id', $product->id)
		->set('quantity', 0)
		->call('save')
		->assertHasErrors(['quantity' => 'min']);
});
