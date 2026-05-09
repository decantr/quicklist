<?php

use App\Models\Shoplist;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Flux\Flux;

new
#[On('product-added')]
#[On('product-updated')]
class extends Component {
	#[Computed]
	public function latestShoplist() {
		return auth()->user()
			->shoplists()
			->with(['products' => function ($query) {
				$query->orderBy('category', 'asc')->orderBy('name', 'asc');
			}])
			->orderBy('date', 'desc')
			->first();
	}

	#[Computed]
	public function textOutput(): string {
		return $this->latestShoplist?->formatted_list ?? '';
	}
};
?>

<div class="h-full w-full flex-1 rounded-xl">
	@if ($this->latestShoplist)
		<flux:card class="relative group h-full flex flex-col">
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
							x-data
							x-on:click="window.navigator.clipboard.writeText($el.closest('.group').querySelector('.formatted-output').innerText.trim()); Flux.toast('{{ __('Copied to clipboard') }}')"
							icon="clipboard"
							size="sm"
							inset="top bottom"
						>{{ __('Copy') }}</flux:button>
					@endif
				</div>
			</div>

			<div class="flex-1 overflow-y-auto">
				<livewire:shoplist.products-table
					:shoplist="$this->latestShoplist"
				/>
			</div>

			@if ($this->latestShoplist->products)
				<pre class="formatted-output hidden">{{ $this->textOutput }}</pre>
			@endif
		</flux:card>

		<livewire:shoplist.add-product :shoplist="$this->latestShoplist" />
		<livewire:shoplist.edit-product :shoplist="$this->latestShoplist" />
	@else
		<flux:card class="h-full flex items-center justify-center text-zinc-500 italic">
			{{ __('No shopping lists found.') }}
		</flux:card>
	@endif
</div>
