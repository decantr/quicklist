<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new
#[On('shoplist-created')]
#[On('shoplist-deleted')]
class extends Component {
	#[Computed]
	public function count(): int {
		return auth()->user()->shoplists()->count();
	}
};
?>

<x-stat-card :label="__('Total Lists')" :value="$this->count"/>

@placeholder
<x-stat-card :label="__('Total Lists')" :value="0" loading/>
@endplaceholder
