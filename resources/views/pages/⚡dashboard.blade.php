<?php

use Livewire\Component;

new class extends Component {
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
	<div class="grid auto-rows-min gap-4 md:grid-cols-4">
		<livewire:dashboard.stats.total-lists lazy />
		<livewire:dashboard.stats.total-products lazy />
		<livewire:dashboard.stats.total-items lazy />
		<livewire:dashboard.stats.shoplist-size-over-time lazy />
	</div>
	<div class="grid auto-rows-min gap-4 md:grid-cols-2">
		<x-stat-card :label="__('Shoplists by Category')">
			<x-slot name="value">
				<livewire:dashboard.stats.shoplists-by-category lazy />
			</x-slot>
		</x-stat-card>
	</div>
	<div class="relative h-full flex-1 overflow-hidden">
		<livewire:dashboard.latest-shoplist/>
	</div>
</div>
