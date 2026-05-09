<?php

use App\Enums\Category;
use App\Enums\SizeType;
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

it('can create product', function () {
	livewire('product.create')
		->set('form.name', 'Apple')
		->set('form.size', 150)
		->set('form.size_type', SizeType::Grams)
		->set('form.category', Category::Produce)
		->call('save')
		->assertHasNoErrors()
		->assertDispatched('product-created')
		->assertDispatched('modal-close', name: 'create-product');

	$this->assertDatabaseHas('products', [
		'name' => 'Apple',
		'size' => 150,
		'size_type' => SizeType::Grams,
		'category' => Category::Produce,
	]);
});

it('can create product and stay on modal', function () {
	livewire('product.create')
		->set('form.name', 'Apple')
		->set('form.size', 150)
		->set('form.size_type', SizeType::Grams)
		->set('form.category', Category::Produce)
		->call('save', false)
		->assertHasNoErrors()
		->assertDispatched('product-created')
		->assertNotDispatched('modal-close', ['name' => 'create-product']);

	$this->assertDatabaseHas('products', [
		'name' => 'Apple',
		'size' => 150,
		'size_type' => SizeType::Grams,
		'category' => Category::Produce,
	]);
});

it('validation works for product creation', function () {
	livewire('product.create')
		->set('form.name', '')
		->set('form.size', '')
		->set('form.size_type', '')
		->set('form.category', '')
		->call('save')
		->assertHasErrors([
			'form.name' => 'required',
			'form.size' => 'required',
			'form.size_type' => 'required',
			'form.category' => 'required',
		]);
});

it('duplicate product creation is prevented', function () {
	// create first product
	livewire('product.create')
		->set('form.name', 'Apple')
		->set('form.size', 150)
		->set('form.size_type', SizeType::Grams)
		->set('form.category', Category::Produce)
		->call('save')
		->assertHasNoErrors();

	// create the same product again with a different category
	livewire('product.create')
		->set('form.name', 'Apple')
		->set('form.size', 150)
		->set('form.size_type', SizeType::Grams)
		->set('form.category', Category::Dairy)
		->call('save')
		->assertHasErrors(['form.name']);
});
