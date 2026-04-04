<?php

use App\Models\Shoplist;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
	#[Computed]
	public function shoplists(): Collection {
		return auth()
			->user()
			->shoplists()
			->withCount('products')
			->orderBy('date', 'desc')
			->get();
	}

	public function create(): void {
		$user = auth()->user();

		$previousShoplist = $user->shoplists()
			->orderBy('date', 'desc')
			->first();

		$shoplist = $user->shoplists()->create([
			'date' => now()->format('Y-m-d'),
		]);

		if ($previousShoplist) {
			$products = $previousShoplist->products->mapWithKeys(function ($product) {
				return [$product->id => ['quantity' => $product->pivot->quantity]];
			})->toArray();

			$shoplist->products()->attach($products);
		}

		unset($this->shoplists);

		Flux::toast(__('Shopping list created.'));
	}
	public function delete(Shoplist $shoplist): void {
		$shoplist->delete();
		unset($this->shoplists);
		Flux::toast(__('Shopping list deleted.'));
	}
};
?><div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
	<div class="flex items-center justify-between">
		<flux:heading size="xl">{{ __('My Shop Lists') }}</flux:heading>

		<flux:button wire:click="create" variant="primary" icon="plus">
			{{ __('Create list') }}
		</flux:button>
	</div>

	<flux:card class="p-0 overflow-hidden">
		<flux:table>
			<flux:table.columns>
				<flux:table.column>{{ __('Date') }}</flux:table.column>
				<flux:table.column>{{ __('Products') }}</flux:table.column>
				<flux:table.column>{{ __('Created At') }}</flux:table.column>
				<flux:table.column />
			</flux:table.columns>

			<flux:table.rows>
				@forelse ($this->shoplists as $shoplist)
					<flux:table.row :key="$shoplist->id">
						<flux:table.cell variant="strong">
							<flux:link :href="route('shoplists.show', $shoplist)" class="cursor-pointer">
								{{ $shoplist->date->format('M d, Y') }}
							</flux:link>
						</flux:table.cell>
						<flux:table.cell>
							<flux:badge size="sm" inset="top bottom">
								{{ trans_choice('{0} No products|{1} 1 product|[2,*] :count products', $shoplist->products_count) }}
							</flux:badge>
						</flux:table.cell>
						<flux:table.cell class="text-zinc-500 dark:text-zinc-400">
							{{ $shoplist->created_at->diffForHumans() }}
						</flux:table.cell>

						<flux:table.cell>
							<flux:dropdown align="end">
								<flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

								<flux:menu>
									<flux:menu.item :href="route('shoplists.show', $shoplist)" icon="pencil-square">{{ __('Edit') }}</flux:menu.item>
									<flux:menu.item wire:click="delete({{ $shoplist->id }})" wire:confirm="{{ __('Are you sure you want to delete this shopping list?') }}" icon="trash" variant="danger">{{ __('Delete') }}</flux:menu.item>
								</flux:menu>
							</flux:dropdown>
						</flux:table.cell>
					</flux:table.row>
				@empty
					<flux:table.row>
						<flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
							{{ __('No shop lists found.') }}
						</flux:table.cell>
					</flux:table.row>
				@endforelse
			</flux:table.rows>
		</flux:table>
	</flux:card>
</div>
