<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new
#[On('product-added')]
#[On('product-updated')]
#[On('shoplist-created')]
#[On('shoplist-deleted')]
class extends Component {
    #[Computed]
    public function count(): int
    {
        return DB::table('product_shoplist')
            ->whereIn('shoplist_id', auth()->user()->shoplists()->select('id'))
            ->sum('quantity');
    }
};
?>

<x-stat-card :label="__('Total Items')" :value="(int) $this->count"  />

@placeholder
<x-stat-card :label="__('Total Items')" :value="0" loading  />
@endplaceholder
