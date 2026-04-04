<?php

use App\Models\Product;
use App\Models\Shoplist;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a shoplist can have multiple products with different quantities', function () {
    $shoplist = Shoplist::create([
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
    $date = '2026-05-15';
    $shoplist = Shoplist::create([
        'date' => $date,
    ]);

    expect($shoplist->date)->toBeInstanceOf(CarbonInterface::class);
    expect($shoplist->date->toDateString())->toBe($date);
});
