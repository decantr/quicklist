<?php

use App\Enums\SizeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('can create product', function () {
	$user = User::factory()->create();

	Livewire::actingAs($user)
		->test('product.create')
		->set('name', 'Apple')
		->set('size', 150)
		->set('size_type', SizeType::Grams->value)
		->call('save')
		->assertHasNoErrors()
		->assertDispatched('product-created');

	$this->assertDatabaseHas('products', [
		'name' => 'Apple',
		'size' => 150.00,
		'size_type' => SizeType::Grams->value,
	]);
});

test('validation works for product creation', function () {
	$user = User::factory()->create();

	Livewire::actingAs($user)
		->test('product.create')
		->set('name', '')
		->set('size', '')
		->set('size_type', '')
		->call('save')
		->assertHasErrors(['name' => 'required', 'size' => 'required', 'size_type' => 'required']);
});
