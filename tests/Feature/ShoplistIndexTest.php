<?php

use App\Models\Shoplist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('shop lists page is accessible', function () {
	$user = User::factory()->create();

	$this->actingAs($user)
		->get(route('shoplists.index'))
		->assertOk()
		->assertSee('My Shop Lists');
});

test('shop lists page displays user shop lists', function () {
	$user = User::factory()->create();
	$shoplist1 = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => '2026-04-01',
	]);
	$shoplist2 = Shoplist::factory()->create([
		'user_id' => $user->id,
		'date' => '2026-04-02',
	]);

	$otherUser = User::factory()->create();
	$otherShoplist = Shoplist::factory()->create([
		'user_id' => $otherUser->id,
		'date' => '2026-04-03',
	]);

	Livewire::actingAs($user)
		->test('shoplist.index')
		->assertSee('Apr 01, 2026')
		->assertSee('Apr 02, 2026')
		->assertDontSee('Apr 03, 2026');
});

test('shop lists page displays empty message when no shop lists exist', function () {
	$user = User::factory()->create();

	Livewire::actingAs($user)
		->test('shoplist.index')
		->assertSee('No shop lists found.');
});
