<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    // shop lists
    Route::livewire('shoplists', 'pages::shoplist.index')->name('shoplists.index');
    Route::livewire('shoplists/{shoplist}', 'pages::shoplist.show')->name('shoplists.show');

    // products
    Route::livewire('products', 'pages::product.index')->name('products.index');
});

require __DIR__.'/settings.php';
