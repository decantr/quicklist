<?php

use App\Enums\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
	#[Url]
	public string $category = '';

	public ?Product $editingProduct = null;

	public ?Product $productToDelete = null;

	#[On('product-created')]
	#[On('product-updated')]
	public function refresh(): void {
		unset($this->products);
	}

	public function edit(Product $product): void {
		$this->editingProduct = $product;

		$this->dispatch('modal-show', name: 'edit-product');
	}

	public function confirmDelete(Product $product): void {
		$this->productToDelete = $product;

		$this->dispatch('modal-show', name: 'confirm-product-deletion');
	}

	public function cancelDelete(): void {
		$this->productToDelete = null;
	}

	public function delete(): void {
		$this->productToDelete->delete();

		$this->productToDelete = null;

		$this->dispatch('modal-close', name: 'confirm-product-deletion');

		$this->refresh();

		Flux::toast(__('Product deleted successfully.'));
	}

	#[Computed]
	public function products(): Collection {
		return Product::query()
			->when($this->category, fn ($query) => $query->where('category', $this->category))
			->orderBy('name', 'asc')
			->get();
	}
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
	<div class="flex items-center justify-between">
		<flux:heading size="xl">{{ __('All Products') }}</flux:heading>

		<div class="flex items-center gap-4">
			<flux:select wire:model.live="category" class="min-w-48" placeholder="{{ __('All categories') }}">
				<flux:select.option value="">{{ __('All categories') }}</flux:select.option>
				@foreach (Category::cases() as $case)
					<flux:select.option value="{{ $case->value }}">{{ $case->name }}</flux:select.option>
				@endforeach
			</flux:select>

			<flux:modal.trigger name="create-product">
				<flux:button variant="primary" icon="plus">{{ __('Create product') }}</flux:button>
			</flux:modal.trigger>
		</div>
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

	<flux:modal name="confirm-product-deletion" class="min-w-[22rem]">
		<form wire:submit="delete" class="space-y-6">
			<div>
				<flux:heading size="lg">{{ __('Are you sure you want to delete this product?') }}</flux:heading>
				<flux:subheading>
					{{ __('Once this product is deleted, it will be permanently removed.') }}
				</flux:subheading>
			</div>

			<div class="flex justify-end gap-3">
				<flux:modal.close>
					<flux:button variant="ghost" wire:click="cancelDelete">
						{{ __('Cancel') }}
					</flux:button>
				</flux:modal.close>

				<flux:button type="submit" variant="danger">
					{{ __('Delete Product') }}
				</flux:button>
			</div>
		</form>
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
							<flux:link
								wire:click="edit({{ $product->id }})"
								class="cursor-pointer font-bold"
							>
								{{ $product->name }}
							</flux:link>
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

									<flux:menu.item wire:click="confirmDelete({{ $product->id }})" icon="trash" variant="danger">
										{{ __('Delete') }}
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
