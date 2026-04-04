<?php

use App\Models\Shoplist;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Flux\Flux;

new class extends Component {
	public ?int $productId = null;
	public int $quantity = 1;

	#[Computed]
	public function latestShoplist() {
		return auth()->user()
			->shoplists()
			->with(['products' => function ($query) {
				$query->orderBy('name', 'asc');
			}])
			->orderBy('date', 'desc')
			->first();
	}

	#[Computed]
	public function products() {
		return $this->latestShoplist?->products ?? collect();
	}

	#[Computed]
	public function allProducts() {
		return Product::query()->orderBy('name')->get();
	}

	#[Computed]
	public function groupedProducts() {
		return $this->products->groupBy(fn ($product) => $product->category->name);
	}

	#[Computed]
	public function textOutput(): string {
		return $this->latestShoplist?->formatted_list ?? '';
	}

	public function addProduct(): void {
		if (! $this->latestShoplist) {
			return;
		}

		$this->validate([
			'productId' => 'required|exists:products,id',
			'quantity' => 'required|integer|min:1',
		]);

		$this->latestShoplist->products()->syncWithoutDetaching([
			$this->productId => ['quantity' => $this->quantity],
		]);

		$this->productId = null;
		$this->quantity = 1;

		unset($this->latestShoplist);
		unset($this->products);
		unset($this->groupedProducts);
		unset($this->textOutput);

		Flux::modal('add-product-dashboard')->close();
		Flux::toast(__('Product added to list.'));
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
					<flux:modal.trigger name="add-product-dashboard">
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

			<flux:modal name="add-product-dashboard" class="md:w-96">
				<form wire:submit="addProduct" class="flex flex-col gap-6">
					<div>
						<flux:heading size="lg">{{ __('Add Product') }}</flux:heading>
						<flux:text>{{ __('Select a product and specify quantity to add it to your shopping list.') }}</flux:text>
					</div>

					<flux:select wire:model="productId" label="{{ __('Product') }}" placeholder="{{ __('Choose a product...') }}" searchable>
						@foreach ($this->allProducts as $product)
							<flux:select.option :value="$product->id">{{ $product->name }} ({{ $product->size }} {{ $product->size_type->value }})</flux:select.option>
						@endforeach
					</flux:select>

					<flux:input type="number" wire:model="quantity" label="{{ __('Quantity') }}" min="1" />

					<div class="flex gap-2">
						<flux:spacer />

						<flux:modal.close>
							<flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
						</flux:modal.close>

						<flux:button type="submit" variant="primary">{{ __('Add to list') }}</flux:button>
					</div>
				</form>
			</flux:modal>

			@if ($this->products->isNotEmpty())
				<div class="flex-1 overflow-y-auto">
					<flux:table>
						<flux:table.columns>
							<flux:table.column>{{ __('Product') }}</flux:table.column>
							<flux:table.column>{{ __('Category') }}</flux:table.column>
							<flux:table.column>{{ __('Quantity') }}</flux:table.column>
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
								</flux:table.row>
							@endforeach
						</flux:table.rows>
					</flux:table>
				</div>

				<div class="formatted-output hidden">
					{{ $this->textOutput }}
				</div>
			@else
				<div class="flex flex-1 items-center justify-center text-zinc-500 italic">
					{{ __('No products in the latest shopping list.') }}
				</div>
			@endif
		</flux:card>
	@else
		<flux:card class="h-full flex items-center justify-center text-zinc-500 italic">
			{{ __('No shopping lists found.') }}
		</flux:card>
	@endif
</div>
