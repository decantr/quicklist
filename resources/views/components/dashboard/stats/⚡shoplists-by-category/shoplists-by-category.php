<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new
#[On('shoplist-created')]
#[On('shoplist-deleted')]
#[On('product-added')]
#[On('product-updated')]
#[On('product-deleted')]
class extends Component {
	public int $days = 30;

	public function dataset() {
		return $this->data;
	}

	#[Computed]
	public function data(): array {
		$categories = auth()->user()->shoplists()
			->whereDate('date', '>=', now()->subDays($this->days))
			->with('products')
			->get()
			->pluck('products')
			->flatten()
			->groupBy(fn ($product) => $product->category->name)
			->map(fn ($products) => $products->sum('pivot.quantity'))
			->toArray();

		return [
			'labels' => array_map(fn ($label) => ucfirst($label), array_keys($categories)),
			'values' => array_values($categories),
		];
	}

	public function updatedDays(): void {
		$this->dispatch('chart-update', data: $this->data);
	}
};
?>
