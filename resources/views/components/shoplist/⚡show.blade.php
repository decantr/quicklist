<?php

use App\Models\Shoplist;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Flux\Flux;

new #[Title('Shopping List Details')] class extends Component {
	public Shoplist $shoplist;
	public string $date;

	public ?int $productId = null;
	public int $quantity = 1;

	public ?int $editingProductId = null;
	public int $editingQuantity = 1;

	public function mount(Shoplist $shoplist): void {
		$this->shoplist = $shoplist;
		$this->date = $shoplist->date->format('Y-m-d');
	}

	#[Computed]
	public function products() {
		return $this->shoplist->products()
			->orderBy('category', 'asc')
			->orderBy('name', 'asc')
			->get();
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
		return $this->shoplist->formatted_list;
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

	public function editProduct(int $productId, int $quantity): void {
		$this->editingProductId = $productId;
		$this->editingQuantity = $quantity;

		Flux::modal('edit-product')->show();
	}

	public function updateProduct(): void {
		$this->validate([
			'editingQuantity' => 'required|integer|min:1',
		]);

		$this->shoplist->products()->updateExistingPivot($this->editingProductId, [
			'quantity' => $this->editingQuantity,
		]);

		$this->editingProductId = null;
		$this->editingQuantity = 1;

		Flux::modal('edit-product')->close();
		Flux::toast(__('Product quantity updated.'));
	}

	public function removeProduct(int $productId): void {
		$this->shoplist->products()->detach($productId);

		unset($this->products);
		$this->shoplist->load('products');

		Flux::toast(__('Product removed from list.'));
	}

	public function updateDate(): void {
		$this->validate([
			'date' => 'required|date',
		]);

		$this->shoplist->update([
			'date' => $this->date,
		]);

		Flux::modal('edit-date')->close();
		Flux::toast(__('Shopping list date updated.'));
	}
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
	<div class="flex items-center justify-between">
		<div class="flex items-center gap-2">
			<flux:heading size="xl">
				{{ __('Shopping List') }}: {{ $shoplist->date->format('M d, Y') }}
			</flux:heading>

			<flux:modal.trigger name="edit-date">
				<flux:button variant="ghost" icon="pencil-square" size="sm" inset="top bottom" />
			</flux:modal.trigger>
		</div>

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

	<flux:modal name="edit-date" class="md:w-96">
		<form wire:submit="updateDate" class="flex flex-col gap-6">
			<div>
				<flux:heading size="lg">{{ __('Edit Date') }}</flux:heading>
				<flux:text>{{ __('Update the date for this shopping list.') }}</flux:text>
			</div>

			<flux:input type="date" wire:model="date" label="{{ __('Date') }}" />

			<div class="flex gap-2">
				<flux:spacer />

				<flux:modal.close>
					<flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
				</flux:modal.close>

				<flux:button type="submit" variant="primary">{{ __('Update Date') }}</flux:button>
			</div>
		</form>
	</flux:modal>

	<flux:modal name="edit-product" class="md:w-96">
		<form wire:submit="updateProduct" class="flex flex-col gap-6">
			<div>
				<flux:heading size="lg">{{ __('Edit Quantity') }}</flux:heading>
				<flux:text>{{ __('Update the quantity for this product in your shopping list.') }}</flux:text>
			</div>

			<flux:input type="number" wire:model="editingQuantity" label="{{ __('Quantity') }}" min="1" />

			<div class="flex gap-2">
				<flux:spacer />

				<flux:modal.close>
					<flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
				</flux:modal.close>

				<flux:button type="submit" variant="primary">{{ __('Update quantity') }}</flux:button>
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
				<flux:table.column />
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

						<flux:table.cell>
							<flux:dropdown align="end">
								<flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

								<flux:menu>
									<flux:menu.item wire:click="editProduct({{ $product->id }}, {{ $product->pivot->quantity }})" icon="pencil-square">{{ __('Edit') }}</flux:menu.item>
									<flux:menu.item wire:click="removeProduct({{ $product->id }})" wire:confirm="{{ __('Are you sure you want to remove this product from the list?') }}" icon="trash" variant="danger">{{ __('Remove') }}</flux:menu.item>
								</flux:menu>
							</flux:dropdown>
						</flux:table.cell>
					</flux:table.row>
				@empty
					<flux:table.row>
						<flux:table.cell colspan="5" class="text-center py-8 text-zinc-500">
							{{ __('No products in this shopping list.') }}
						</flux:table.cell>
					</flux:table.row>
				@endforelse
			</flux:table.rows>
		</flux:table>
	</flux:card>

	@if ($this->products->isNotEmpty())
		<flux:card class="relative group">
			<div class="flex items-center justify-between mb-4">
				<flux:heading size="lg">{{ __('Formatted List') }}</flux:heading>

				<flux:button
					x-on:click="window.navigator.clipboard.writeText($el.closest('.group').querySelector('.formatted-output').innerText.trim()); Flux.toast('{{ __('Copied to clipboard') }}')"
					icon="clipboard"
					size="sm"
					inset="top bottom"
				>{{ __('Copy') }}</flux:button>
			</div>

			<pre class="formatted-output font-mono text-sm bg-zinc-50 dark:bg-zinc-900 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 whitespace-pre-wrap">{{ $this->textOutput }}</pre>
		</flux:card>
	@endif
</div>
