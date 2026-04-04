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
};
?>

<div>
<x-layouts::app :title="__('My Shop Lists')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('My Shop Lists') }}</flux:heading>
        </div>

        <flux:card class="p-0 overflow-hidden">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Products') }}</flux:table.column>
                    <flux:table.column>{{ __('Created At') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->shoplists as $shoplist)
                        <flux:table.row :key="$shoplist->id">
                            <flux:table.cell variant="strong">
                                {{ $shoplist->date->format('M d, Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" inset="top bottom">
                                    {{ trans_choice('{0} No products|{1} 1 product|[2,*] :count products', $shoplist->products_count) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500 dark:text-zinc-400">
                                {{ $shoplist->created_at->diffForHumans() }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center py-8 text-zinc-500">
                                {{ __('No shop lists found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</x-layouts::app>
</div>
