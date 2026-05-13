<?php

use App\Models\Product;
use App\Models\Shoplist;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Flux\Flux;

new class extends Component {
	public Shoplist $shoplist;

	#[Validate(['required', 'exists:products,id'])]
	public int|null $product_id = null;

	#[Validate(['required', 'integer', 'min:1'])]
	public int $quantity = 1;

	#[On('edit-product')]
	public function edit(int $product_id): void {
		$product = $this->shoplist
			->products
			->find($product_id);

		if ($product === null) {
			Flux::toast('Product not on this Shop List', variant: 'warning');
			return;
		}

		$this->product_id = $product->id;
		$this->quantity = $product->pivot->quantity;

		Flux::modal('edit-product')->show();
	}

	public function save(): void {
		$validated = $this->validate();

		$this->shoplist->products()
			->updateExistingPivot($this->product_id, ['quantity' => $this->quantity]);

		$this->reset([
			'product_id',
			'quantity',
		]);

		$this->dispatch('product-updated');

		Flux::modal('edit-product')->close();
		Flux::toast(__('Product quantity updated.'), variant: 'success');
	}
};
?>

<flux:modal name="edit-product" class="md:w-96">
	<form wire:submit="save" class="flex flex-col gap-6">
		<div>
			<flux:heading size="lg">{{ __('Edit Quantity') }}</flux:heading>
			<flux:text>{{ __('Update the quantity for this product in your shopping list.') }}</flux:text>
		</div>

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

			<flux:button type="submit" variant="primary">{{ __('Update quantity') }}</flux:button>
		</div>
	</form>
</flux:modal>
