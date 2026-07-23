<?php

use App\Enums\Category;
use App\Models\Product;
use App\Models\Shoplist;
use App\Models\User;
use Livewire\Livewire;

test('renders with category data from recent shoplists', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()
		->state([
			'user_id' => $user->id,
			'date' => now()->subDays(5),
		])
		->create();

	$produce = Product::factory()
		->state(['category' => Category::Produce])
		->create();
	$dairy = Product::factory()
		->state(['category' => Category::Dairy])
		->create();

	$shoplist->products()->attach([
		$produce->id => ['quantity' => 3],
		$dairy->id => ['quantity' => 2],
	]);

	$component = Livewire::actingAs($user)
		->test('dashboard.stats.shoplists-by-category');

	expect($component->data['labels'])->toContain('Produce')
		->and($component->data['labels'])->toContain('Dairy');
});

test('filters shoplists by date range', function () {
	$user = User::factory()->create();

	$recentShoplist = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => now()->subDays(3),
	]);
	$oldShoplist = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => now()->subDays(60),
	]);

	$frozen = Product::factory()->create(['category' => Category::Frozen]);
	$meat = Product::factory()->create(['category' => Category::Meat]);

	$recentShoplist->products()->attach([$frozen->id => ['quantity' => 4]]);
	$oldShoplist->products()->attach([$meat->id => ['quantity' => 1]]);

	$component = Livewire::actingAs($user)
		->test('dashboard.stats.shoplists-by-category');

	expect($component->data['labels'])->toContain('Frozen')
		->and($component->data['labels'])->not->toContain('Meat');

	$component->set('days', 90);

	expect($component->data['labels'])->toContain('Frozen')
		->and($component->data['labels'])->toContain('Meat');
});

test('dispatches chart-update event when days changes', function () {
	$user = User::factory()->create();
	$shoplist = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => now(),
	]);

	$produce = Product::factory()->create(['category' => Category::Produce]);
	$shoplist->products()->attach([$produce->id => ['quantity' => 2]]);

	Livewire::actingAs($user)
		->test('dashboard.stats.shoplists-by-category')
		->set('days', 7)
		->assertDispatched('chart-update');
});
