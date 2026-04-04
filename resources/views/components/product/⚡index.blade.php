<?php

use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
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
	</div>

	<flux:card class="p-0 overflow-hidden">
		<flux:table>
			<flux:table.columns>
				<flux:table.column>{{ __('Name') }}</flux:table.column>
				<flux:table.column>{{ __('Size') }}</flux:table.column>
				<flux:table.column>{{ __('Created At') }}</flux:table.column>
			</flux:table.columns>

			<flux:table.rows>
				@forelse ($this->products as $product)
					<flux:table.row :key="$product->id">
						<flux:table.cell variant="strong">
							{{ $product->name }}
						</flux:table.cell>
						<flux:table.cell>
							{{ $product->size }} {{ $product->size_type->value }}
						</flux:table.cell>
						<flux:table.cell class="text-zinc-500 dark:text-zinc-400">
							{{ $product->created_at->diffForHumans() }}
						</flux:table.cell>
					</flux:table.row>
				@empty
					<flux:table.row>
						<flux:table.cell colspan="3" class="text-center py-8 text-zinc-500">
							{{ __('No products found.') }}
						</flux:table.cell>
					</flux:table.row>
				@endforelse
			</flux:table.rows>
		</flux:table>
	</flux:card>
</div>
