<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
	Route::view('dashboard', 'dashboard')->name('dashboard');
	Route::livewire('shoplists', 'shoplist.index')->name('shoplists.index');
	Route::livewire('products', 'product.index')->name('products.index');
});

require __DIR__.'/settings.php';
