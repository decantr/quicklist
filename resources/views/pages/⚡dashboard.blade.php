<?php

use Livewire\Component;

new class extends Component {
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
	<div class="grid auto-rows-min gap-4 md:grid-cols-3">
		<livewire:dashboard.stats.total-lists lazy />
		<livewire:dashboard.stats.total-products lazy />
		<livewire:dashboard.stats.total-items lazy />
	</div>
	<div class="relative h-full flex-1 overflow-hidden">
		<livewire:dashboard.latest-shoplist/>
	</div>
</div>
