<?php

use App\Enums\SizeType;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a product can be created', function () {
    $product = Product::factory()->create([
        'name' => 'Milk',
        'size' => 1.5,
        'size_type' => SizeType::Millilitres,
    ]);

    expect($product->name)->toBe('Milk')
        ->and($product->size)->toBe('1.50') // Decimal cast as string in PHP if not cast to float
        ->and($product->size_type)->toBe(SizeType::Millilitres);
});

test('a product can be created with grams', function () {
    $product = Product::factory()->create([
        'name' => 'Eggs',
        'size' => 12,
        'size_type' => SizeType::Grams,
    ]);

    expect($product->name)->toBe('Eggs')
        ->and($product->size)->toBe('12.00')
        ->and($product->size_type)->toBe(SizeType::Grams);
});
