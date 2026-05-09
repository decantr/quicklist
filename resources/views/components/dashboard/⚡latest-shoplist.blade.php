<?php

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new
#[On('product-added')]
#[On('product-updated')]
class extends Component {
	#[Computed]
	public function latestShoplist() {
		return auth()->user()
			->latestShoplist()
			->with([
				'products' => fn($q) => $q->orderBy('category', 'asc')->orderBy('name', 'asc')
			])
			->first();
	}
};
?>

<flux:card class="relative group h-full flex flex-col">
	@if ($this->latestShoplist)
		<div class="flex items-center justify-between mb-4">
			<flux:heading size="lg">
				{{ __('Latest Shopping List') }}: {{ $this->latestShoplist->date->format('M d, Y') }}
			</flux:heading>

			<div class="flex gap-2">
				<flux:modal.trigger name="add-product">
					<flux:button icon="plus" size="sm" variant="primary">
						{{ __('Add Product') }}
					</flux:button>
				</flux:modal.trigger>

				@if ($this->latestShoplist->products->isNotEmpty())
					<flux:button
						x-on:click="window.navigator.clipboard.writeText($el.closest('.group').querySelector('.formatted-output').innerText.trim()); Flux.toast('{{ __('Copied to clipboard') }}')"
						icon="clipboard"
						size="sm"
					>
						{{ __('Copy') }}
					</flux:button>
				@endif
			</div>
		</div>

		<livewire:shoplist.products-table
			:shoplist="$this->latestShoplist"
		/>

		@if ($this->latestShoplist->products->isNotEmpty())
			<pre class="formatted-output hidden">{{ $this->latestShoplist->formatted_list }}</pre>
		@endif

		<livewire:shoplist.add-product :shoplist="$this->latestShoplist"/>
		<livewire:shoplist.edit-product :shoplist="$this->latestShoplist"/>
	@else
		<flux:text class="italic">
			{{ __('No shopping lists found.') }}
		</flux:text>
	@endif
</flux:card>
