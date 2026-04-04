<?php

use App\Models\Shoplist;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Flux\Flux;

new #[Title('Shopping List Details')] class extends Component {
	public Shoplist $shoplist;

	public ?int $productId = null;
	public int $quantity = 1;

	public function mount(Shoplist $shoplist): void {
		$this->shoplist = $shoplist;
	}

	#[Computed]
	public function products() {
		return $this->shoplist->products()
			->orderBy('name', 'asc')
			->get();
	}

	#[Computed]
	public function allProducts() {
		return Product::query()->orderBy('name')->get();
	}

	#[Computed]
	public function textOutput(): string {
		return $this->products
			->map(fn ($product) => "{$product->pivot->quantity}x {$product->name} ({$product->size} {$product->size_type->value})")
			->implode("\n");
	}

	public function addProduct(): void {
		$this->validate([
			'productId' => 'required|exists:products,id',
			'quantity' => 'required|integer|min:1',
		]);

		$this->shoplist->products()->syncWithoutDetaching([
			$this->productId => ['quantity' => $this->quantity],
		]);

		$this->productId = null;
		$this->quantity = 1;

		Flux::modal('add-product')->close();
	}
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
	<div class="flex items-center justify-between">
		<flux:heading size="xl">
			{{ __('Shopping List') }}: {{ $shoplist->date->format('M d, Y') }}
		</flux:heading>

		<div class="flex gap-2">
			<flux:modal.trigger name="add-product">
				<flux:button icon="plus" variant="primary">
					{{ __('Add Product') }}
				</flux:button>
			</flux:modal.trigger>

			<flux:button :href="route('shoplists.index')" variant="ghost" icon="chevron-left">
				{{ __('Back to lists') }}
			</flux:button>
		</div>
	</div>

	<flux:modal name="add-product" class="md:w-96">
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

	<flux:card class="p-0 overflow-hidden">
		<flux:table>
			<flux:table.columns>
				<flux:table.column>{{ __('Product') }}</flux:table.column>
				<flux:table.column>{{ __('Category') }}</flux:table.column>
				<flux:table.column>{{ __('Size') }}</flux:table.column>
				<flux:table.column>{{ __('Quantity') }}</flux:table.column>
			</flux:table.columns>

			<flux:table.rows>
				@forelse ($this->products as $product)
					<flux:table.row :key="$product->id">
						<flux:table.cell variant="strong">
							{{ $product->name }}
						</flux:table.cell>
						<flux:table.cell>
							<flux:badge size="sm" inset="top bottom" color="zinc">
								{{ $product->category->name }}
							</flux:badge>
						</flux:table.cell>
						<flux:table.cell>
							{{ $product->size }} {{ $product->size_type->value }}
						</flux:table.cell>
						<flux:table.cell>
							<flux:badge size="sm" inset="top bottom" color="blue">
								{{ $product->pivot->quantity }}
							</flux:badge>
						</flux:table.cell>
					</flux:table.row>
				@empty
					<flux:table.row>
						<flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
							{{ __('No products in this shopping list.') }}
						</flux:table.cell>
					</flux:table.row>
				@endforelse
			</flux:table.rows>
		</flux:table>
	</flux:card>

	@if ($this->products->isNotEmpty())
		<flux:card class="relative group">
			<flux:field>
				<div class="flex items-center justify-between">
					<flux:label>{{ __('Text Output') }}</flux:label>

					<flux:button
						x-on:click="window.navigator.clipboard.writeText($el.closest('.group').querySelector('textarea').value); Flux.toast('{{ __('Copied to clipboard') }}')"
						icon="clipboard"
						size="sm"
						variant="ghost"
						inset="top bottom"
					>{{ __('Copy') }}</flux:button>
				</div>

				<flux:description>{{ __('Copy this list to share it with others.') }}</flux:description>

				<flux:textarea
					rows="auto"
					readonly
				>{{ $this->textOutput }}</flux:textarea>
			</flux:field>
		</flux:card>
	@endif
</div>
