<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\Shoplist;
use Illuminate\Support\Collection;

new
#[On('shoplist-created')]
#[On('shoplist-deleted')]
#[On('product-added')]
#[On('product-updated')]
#[On('product-deleted')]
class extends Component {
	#[Computed]
	public function data(): Collection {
		return auth()->user()->shoplists()
            ->withSum('products as items_count', 'product_shoplist.quantity')
            ->orderByDesc('date')
            ->limit(10)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($shoplist) => [
                'date' => $shoplist->date->format('jS M'),
                'count' => (int) ($shoplist->items_count ?? 0),
            ]);
	}
};
?>

<x-stat-card :label="__('Size Over Time')">
    <x-slot name="value">
        <div class="h-6 w-full flex items-end gap-1 overflow-hidden">
            @forelse($this->data as $point)
                @php
                    $max = $this->data->max('count') ?: 1;
                    $height = ($point['count'] / $max) * 100;
                @endphp
				<flux:tooltip
					class="flex size-full items-end"
					position="bottom"
				>
					<div
						class="bg-accent flex-1 rounded-t-xs min-w-4"
						style="height: {{ max(10, $height) }}%"
					></div>

					<x-slot:content>
						<flux:heading>
							{{ $point['date'] }}
						</flux:heading>
						<flux:subheading>
							{{ $point['count'] }} Items
						</flux:subheading>
					</x-slot:content>
				</flux:tooltip>
            @empty
                <flux:text size="sm" class="text-zinc-400 italic">{{ __('No data') }}</flux:text>
            @endforelse
        </div>
    </x-slot>
</x-stat-card>

@placeholder
<x-stat-card :label="__('Size Over Time')">
    <x-slot name="value">
        <div class="h-6 w-full flex items-end gap-1 overflow-hidden">
            @foreach(range(1, 10) as $i)
				<flux:skeleton.line class="flex-1" style="height: {{ rand(20, 80) }}%" />
            @endforeach
        </div>
    </x-slot>
</x-stat-card>
@endplaceholder
