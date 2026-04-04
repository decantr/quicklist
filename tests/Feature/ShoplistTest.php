<?php

use App\Models\Product;
use App\Models\Shoplist;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a shoplist can have multiple products with different quantities', function () {
    $user = User::factory()->create();
    $shoplist = Shoplist::create([
        'user_id' => $user->id,
        'date' => now()->toDateString(),
    ]);

    $product1 = Product::factory()->create(['name' => 'Apple']);
    $product2 = Product::factory()->create(['name' => 'Banana']);

    $shoplist->products()->attach([
        $product1->id => ['quantity' => 5],
        $product2->id => ['quantity' => 10],
    ]);

    expect($shoplist->products)->toHaveCount(2);

    $retrievedProduct1 = $shoplist->products()->where('product_id', $product1->id)->first();
    $retrievedProduct2 = $shoplist->products()->where('product_id', $product2->id)->first();

    expect($retrievedProduct1->pivot->quantity)->toEqual(5);
    expect($retrievedProduct2->pivot->quantity)->toEqual(10);
});

test('shoplist date is correctly cast', function () {
    $user = User::factory()->create();
    $date = '2026-05-15';
    $shoplist = Shoplist::create([
        'user_id' => $user->id,
        'date' => $date,
    ]);

    expect($shoplist->date)->toBeInstanceOf(CarbonInterface::class);
    expect($shoplist->date->toDateString())->toBe($date);
});

test('a shoplist belongs to a user', function () {
    $user = User::factory()->create();
    $shoplist = Shoplist::factory()->create(['user_id' => $user->id]);

    expect($shoplist->user)->toBeInstanceOf(User::class);
    expect($shoplist->user->id)->toBe($user->id);
});

test('a user has many shoplists', function () {
    $user = User::factory()->create();
    Shoplist::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->shoplists)->toHaveCount(3);
});
