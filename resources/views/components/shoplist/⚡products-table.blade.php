<?php

use App\Models\Shoplist;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new
#[On('product-added')]
#[On('product-updated')]
class extends Component {
	#[Locked]
	public Shoplist $shoplist;

	#[Computed]
	public function products() {
		return $this->shoplist->products()
			->orderBy('category', 'asc')
			->orderBy('name', 'asc')
			->get();
	}

	public function removeProduct(int $productId): void {
		$this->shoplist->products()->detach($productId);

		$this->dispatch('product-updated');
		Flux::toast(__('Product removed from list.'));
	}
};
?>

<div>
	<flux:table>
		<flux:table.columns>
			<flux:table.column class="w-4">{{ __('Quantity') }}</flux:table.column>
			<flux:table.column>{{ __('Product') }}</flux:table.column>
			<flux:table.column>{{ __('Category') }}</flux:table.column>
			<flux:table.column>{{ __('Size') }}</flux:table.column>
			<flux:table.column class="w-4"/>
		</flux:table.columns>

		<flux:table.rows>
			@forelse ($this->products as $product)
				<flux:table.row :key="$product->id">
					<flux:table.cell class="text-center">
						<flux:badge size="sm" inset="top bottom" color="blue">
							{{ $product->pivot->quantity }}
						</flux:badge>
					</flux:table.cell>

					<flux:table.cell variant="strong">
						{{ $product->name }}
					</flux:table.cell>

					<flux:table.cell>
						{{ $product->category->name }}
					</flux:table.cell>

					<flux:table.cell>
						{{ $product->size }} {{ $product->size_type->value }}
					</flux:table.cell>

					<flux:table.cell>
						<flux:dropdown align="end">
							<flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"/>

							<flux:menu>
								<flux:menu.item
									icon="pencil-square"
									wire:click="$dispatch('edit-product', {{ $product->id }})"
								>
									{{ __('Edit') }}
								</flux:menu.item>

								<flux:menu.item
									icon="trash"
									variant="danger"
									wire:click="removeProduct({{ $product->id }})"
									wire:confirm="{{ __('Are you sure you want to remove this product from the list?') }}"
								>
									{{ __('Remove') }}
								</flux:menu.item>
							</flux:menu>
						</flux:dropdown>
					</flux:table.cell>
				</flux:table.row>
			@empty
				<flux:table.row>
					<flux:table.cell colspan="5" class="text-center py-8">
						{{ __('No products in this shopping list.') }}
					</flux:table.cell>
				</flux:table.row>
			@endforelse
		</flux:table.rows>
	</flux:table>
</div>
