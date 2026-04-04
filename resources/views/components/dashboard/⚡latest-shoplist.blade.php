<?php

use App\Models\Shoplist;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
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
	public function groupedProducts() {
		return $this->products->groupBy(fn ($product) => $product->category->name);
	}

	#[Computed]
	public function textOutput(): string {
		return $this->groupedProducts
			->map(function ($products) {
				return $products->map(fn ($product) => "{$product->pivot->quantity}x {$product->name} ({$product->size} {$product->size_type->value})")
					->implode("\n");
			})
			->implode("\n\n");
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

			@if ($this->products->isNotEmpty())
				<div class="formatted-output font-mono text-sm bg-zinc-50 dark:bg-zinc-900 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-4 overflow-y-auto flex-1">
					@foreach ($this->groupedProducts as $category => $products)
						<div class="space-y-1">
							@foreach ($products as $product)
								<div>{{ $product->pivot->quantity }}x {{ $product->name }} ({{ $product->size }} {{ $product->size_type->value }})</div>
							@endforeach
						</div>
					@endforeach
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
