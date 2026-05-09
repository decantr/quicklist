<?php

use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new
#[On('product-created')]
#[On('product-deleted')]
class extends Component {
	#[Computed]
	public function count(): int {
		return Product::count();
	}
};
?>

<x-stat-card :label="__('Product Catalog')" :value="$this->count"/>

@placeholder
<x-stat-card :label="__('Product Catalog')" :value="0" loading/>
@endplaceholder
