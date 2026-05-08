<?php

use App\Models\Product;
use App\Models\Shoplist;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Flux\Flux;

new class extends Component {
	public Shoplist|null $shoplist = null;

	public int|null $productId = null;

	public int $quantity = 1;

	#[Computed]
	public function products() {
		$existingProductIds = $this->shoplist
			?->products()
			->pluck('product_id')
			->toArray() ?? [];

		return Product::query()
			->whereNotIn('id', $existingProductIds)
			->orderBy('name')
			->get();
	}

	public function addProduct(): void {
		if (!$this->shoplist) {
			return;
		}

		$this->validate([
			'productId' => [
				'required',
				'exists:products,id',
				function ($attribute, $value, $fail) {
					if ($this->shoplist->products()->where('product_id', $value)->exists()) {
						$fail(__('This product is already in the shopping list.'));
					}
				},
			],
			'quantity' => 'required|integer|min:1',
		]);

		$this->shoplist->products()->syncWithoutDetaching([
			$this->productId => ['quantity' => $this->quantity],
		]);

		$this->productId = null;
		$this->quantity = 1;

		$this->dispatch('product-added');

		Flux::modal('add-product')->close();
		Flux::toast(__('Product added to list.'));
	}
};
?>

<flux:modal name="add-product" class="md:w-96">
	<form wire:submit="addProduct" class="flex flex-col gap-6">
		<div>
			<flux:heading size="lg">{{ __('Add Product') }}</flux:heading>
			<flux:text>{{ __('Select a product and specify quantity to add it to your shopping list.') }}</flux:text>
		</div>

		<flux:select
			:label="__('Product')"
			:placeholder="__('Choose a product...')"
			searchable
			wire:model="productId"
		>
			@foreach ($this->products as $product)
				<flux:select.option :value="$product->id">{{ $product->name }}
					({{ $product->size }} {{ $product->size_type->value }})
				</flux:select.option>
			@endforeach
		</flux:select>

		<flux:input
			:label="__('Quantity')"
			min="1"
			type="number"
			wire:model="quantity"
		/>

		<div class="flex gap-2">
			<flux:spacer/>

			<flux:modal.close>
				<flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
			</flux:modal.close>

			<flux:button type="submit" variant="primary">{{ __('Add to list') }}</flux:button>
		</div>
	</form>
</flux:modal>
