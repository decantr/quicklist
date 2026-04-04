<?php

use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
	public ?Product $editingProduct = null;

	#[On('product-created')]
	#[On('product-updated')]
	public function refresh(): void {
		unset($this->products);
	}

	public function edit(Product $product): void {
		$this->editingProduct = $product;

		$this->dispatch('modal-show', name: 'edit-product');
	}

	#[Computed]
	public function products(): Collection {
		return Product::query()
			->orderBy('name', 'asc')
			->get();
	}
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
	<div class="flex items-center justify-between">
		<flux:heading size="xl">{{ __('All Products') }}</flux:heading>

		<flux:modal.trigger name="create-product">
			<flux:button variant="primary" icon="plus">{{ __('Create product') }}</flux:button>
		</flux:modal.trigger>
	</div>

	<flux:modal name="create-product" class="min-w-[22rem]">
		<div class="space-y-6">
			<div>
				<flux:heading size="lg">{{ __('Create new product') }}</flux:heading>
				<flux:subheading>{{ __('Add a new product to your inventory.') }}</flux:subheading>
			</div>

			<livewire:product.create />
		</div>
	</flux:modal>

	<flux:modal name="edit-product" class="min-w-[22rem]">
		<div class="space-y-6">
			<div>
				<flux:heading size="lg">{{ __('Edit product') }}</flux:heading>
				<flux:subheading>{{ __('Update product details.') }}</flux:subheading>
			</div>

			@if ($editingProduct)
				<livewire:product.edit :product="$editingProduct" :key="$editingProduct->id" />
			@endif
		</div>
	</flux:modal>

	<flux:card class="p-0 overflow-hidden">
		<flux:table>
			<flux:table.columns>
				<flux:table.column>{{ __('Name') }}</flux:table.column>
				<flux:table.column>{{ __('Category') }}</flux:table.column>
				<flux:table.column>{{ __('Size') }}</flux:table.column>
				<flux:table.column>{{ __('Created At') }}</flux:table.column>
				<flux:table.column />
			</flux:table.columns>

			<flux:table.rows>
				@forelse ($this->products as $product)
					<flux:table.row :key="$product->id">
						<flux:table.cell>
							<flux:button
								wire:click="edit({{ $product->id }})"
								variant="ghost"
								class="-ml-3 !font-bold"
							>
								{{ $product->name }}
							</flux:button>
						</flux:table.cell>
						<flux:table.cell>
							<flux:badge size="sm" inset="top bottom" color="zinc">
								{{ $product->category->name }}
							</flux:badge>
						</flux:table.cell>
						<flux:table.cell>
							{{ $product->size }} {{ $product->size_type->value }}
						</flux:table.cell>
						<flux:table.cell class="text-zinc-500 dark:text-zinc-400">
							{{ $product->created_at->diffForHumans() }}
						</flux:table.cell>
						<flux:table.cell>
							<flux:dropdown align="end">
								<flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

								<flux:menu>
									<flux:menu.item wire:click="edit({{ $product->id }})" icon="pencil-square">
										{{ __('Edit') }}
									</flux:menu.item>
								</flux:menu>
							</flux:dropdown>
						</flux:table.cell>
					</flux:table.row>
				@empty
					<flux:table.row>
						<flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
							{{ __('No products found.') }}
						</flux:table.cell>
					</flux:table.row>
				@endforelse
			</flux:table.rows>
		</flux:table>
	</flux:card>
</div>
