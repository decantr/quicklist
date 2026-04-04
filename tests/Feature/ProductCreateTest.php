<?php

use App\Enums\Category;
use App\Enums\SizeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('can create product', function () {
	$user = User::factory()->create();

	Livewire::actingAs($user)
		->test('product.create')
		->set('form.name', 'Apple')
		->set('form.size', 150)
		->set('form.size_type', SizeType::Grams->value)
		->set('form.category', Category::Produce->value)
		->call('save')
		->assertHasNoErrors()
		->assertDispatched('product-created')
		->assertDispatched('modal-close', name: 'create-product');

	$this->assertDatabaseHas('products', [
		'name' => 'Apple',
		'size' => 150,
		'size_type' => SizeType::Grams->value,
		'category' => Category::Produce->value,
	]);
});

test('can create product and stay on modal', function () {
	$user = User::factory()->create();

	Livewire::actingAs($user)
		->test('product.create')
		->set('form.name', 'Apple')
		->set('form.size', 150)
		->set('form.size_type', SizeType::Grams->value)
		->set('form.category', Category::Produce->value)
		->call('save', false)
		->assertHasNoErrors()
		->assertDispatched('product-created')
		->assertNotDispatched('modal-close', ['name' => 'create-product']);

	$this->assertDatabaseHas('products', [
		'name' => 'Apple',
		'size' => 150,
		'size_type' => SizeType::Grams->value,
		'category' => Category::Produce->value,
	]);
});

test('validation works for product creation', function () {
	$user = User::factory()->create();

	Livewire::actingAs($user)
		->test('product.create')
		->set('form.name', '')
		->set('form.size', '')
		->set('form.size_type', '')
		->set('form.category', '')
		->call('save')
		->assertHasErrors(['form.name' => 'required', 'form.size' => 'required', 'form.size_type' => 'required', 'form.category' => 'required']);
});

test('duplicate product creation is prevented', function () {
	$user = User::factory()->create();

	// Create first product
	Livewire::actingAs($user)
		->test('product.create')
		->set('form.name', 'Apple')
		->set('form.size', 150)
		->set('form.size_type', SizeType::Grams->value)
		->set('form.category', Category::Produce->value)
		->call('save')
		->assertHasNoErrors();

	// Try to create the same product again with a different category
	Livewire::actingAs($user)
		->test('product.create')
		->set('form.name', 'Apple')
		->set('form.size', 150)
		->set('form.size_type', SizeType::Grams->value)
		->set('form.category', Category::Dairy->value)
		->call('save')
		->assertHasErrors(['form.name']);
});
