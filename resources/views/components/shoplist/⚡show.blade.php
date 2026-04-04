<?php

use App\Models\Shoplist;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shopping List Details')] class extends Component {
	public Shoplist $shoplist;

	public function mount(Shoplist $shoplist): void {
		$this->shoplist = $shoplist;
	}

	#[Computed]
	public function products() {
		return $this->shoplist->products()
			->orderBy('name', 'asc')
			->get();
	}
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
	<div class="flex items-center justify-between">
		<flux:heading size="xl">
			{{ __('Shopping List') }}: {{ $shoplist->date->format('M d, Y') }}
		</flux:heading>

		<flux:button :href="route('shoplists.index')" variant="ghost" icon="chevron-left">
			{{ __('Back to lists') }}
		</flux:button>
	</div>

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
</div>
