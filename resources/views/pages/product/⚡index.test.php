<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
	actingAs(
		$this->user = User::factory()->create()
	);
});

it('can delete product', function () {
	$product = Product::factory()->create();

	livewire('pages::product.index')
		->call('confirmDelete', $product->id)
		->call('delete')
		->assertHasNoErrors()
		->assertDispatched('modal-close', name: 'confirm-product-deletion');

	$this->assertDatabaseMissing('products', [
		'id' => $product->id,
	]);
});
