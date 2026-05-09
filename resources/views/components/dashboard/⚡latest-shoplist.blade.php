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
	public function products() {
		return $this->latestShoplist?->products ?? collect();
	}

	#[Computed]
	public function groupedProducts() {
		return $this->products->groupBy(fn ($product) => $product->category->name);
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

					@if ($this->products->isNotEmpty())
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


			@if ($this->products->isNotEmpty())
				<div class="flex-1 overflow-y-auto">
					<flux:table>
						<flux:table.columns>
							<flux:table.column>{{ __('Product') }}</flux:table.column>
							<flux:table.column>{{ __('Category') }}</flux:table.column>
							<flux:table.column>{{ __('Quantity') }}</flux:table.column>
							<flux:table.column />
						</flux:table.columns>

						<flux:table.rows>
							@foreach ($this->products as $product)
								<flux:table.row :key="$product->id">
									<flux:table.cell variant="strong">
										{{ $product->name }} ({{ $product->size }} {{ $product->size_type->value }})
									</flux:table.cell>
									<flux:table.cell>
										<flux:badge size="sm" inset="top bottom" color="zinc">
											{{ $product->category->name }}
										</flux:badge>
									</flux:table.cell>
									<flux:table.cell>
										<flux:badge size="sm" inset="top bottom" color="blue">
											{{ $product->pivot->quantity }}
										</flux:badge>
									</flux:table.cell>

									<flux:table.cell>
										<flux:dropdown align="end">
											<flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

											<flux:menu>
												<flux:menu.item wire:click="$dispatch('edit-product', { productId: {{ $product->id }}, quantity: {{ $product->pivot->quantity }} })" icon="pencil-square">{{ __('Edit') }}</flux:menu.item>
											</flux:menu>
										</flux:dropdown>
									</flux:table.cell>
								</flux:table.row>
							@endforeach
						</flux:table.rows>
					</flux:table>
				</div>

				<pre class="formatted-output hidden">{{ $this->textOutput }}</pre>
			@else
				<div class="flex flex-1 items-center justify-center text-zinc-500 italic">
					{{ __('No products in the latest shopping list.') }}
				</div>
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
