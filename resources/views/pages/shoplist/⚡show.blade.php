<?php

use App\Models\Shoplist;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Flux\Flux;

new
#[On('product-added')]
#[On('product-updated')]
#[Title('Shopping List Details')] class extends Component {
	public Shoplist $shoplist;
	public string $date;

	public function mount(Shoplist $shoplist): void {
		$this->shoplist = $shoplist;
		$this->date = $shoplist->date->format('Y-m-d');
	}

	#[Computed]
	public function products() {
		return $this->shoplist->products;
	}

	#[Computed]
	public function textOutput(): string {
		return $this->shoplist->formatted_list;
	}


	public function updateDate(): void {
		$this->validate([
			'date' => 'required|date',
		]);

		$this->shoplist->update([
			'date' => $this->date,
		]);

		Flux::modal('edit-date')->close();
		Flux::toast(__('Shopping list date updated.'));
	}
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
	<div class="flex items-center justify-between">
		<div class="flex items-center gap-2">
			<flux:heading size="xl">
				{{ __('Shopping List') }}: {{ $shoplist->date->format('M d, Y') }}
			</flux:heading>

			<flux:modal.trigger name="edit-date">
				<flux:button variant="ghost" icon="pencil-square" size="sm" inset="top bottom" />
			</flux:modal.trigger>
		</div>

		<div class="flex gap-2">
			<flux:modal.trigger name="add-product">
				<flux:button icon="plus" variant="primary">
					{{ __('Add Product') }}
				</flux:button>
			</flux:modal.trigger>

			<flux:button :href="route('shoplists.index')" variant="ghost" icon="chevron-left" wire:navigate.hover>
				{{ __('Back to lists') }}
			</flux:button>
		</div>
	</div>

	<flux:modal name="edit-date" class="md:w-96">
		<form wire:submit="updateDate" class="flex flex-col gap-6">
			<div>
				<flux:heading size="lg">{{ __('Edit Date') }}</flux:heading>
				<flux:text>{{ __('Update the date for this shopping list.') }}</flux:text>
			</div>

			<flux:input type="date" wire:model="date" label="{{ __('Date') }}" />

			<div class="flex gap-2">
				<flux:spacer />

				<flux:modal.close>
					<flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
				</flux:modal.close>

				<flux:button type="submit" variant="primary">{{ __('Update Date') }}</flux:button>
			</div>
		</form>
	</flux:modal>


	<flux:card class="overflow-hidden">
		<livewire:shoplist.products-table :$shoplist show-size-column allow-removal />
	</flux:card>

	@if ($this->products->isNotEmpty())
		<flux:card class="relative group">
			<div class="flex items-center justify-between mb-4">
				<flux:heading size="lg">{{ __('Formatted List') }}</flux:heading>

				<flux:button
					x-on:click="window.navigator.clipboard.writeText($el.closest('.group').querySelector('.formatted-output').innerText.trim()); Flux.toast('{{ __('Copied to clipboard') }}')"
					icon="clipboard"
					size="sm"
					inset="top bottom"
				>{{ __('Copy') }}</flux:button>
			</div>

			<pre class="formatted-output font-mono text-sm bg-zinc-50 dark:bg-zinc-900 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 whitespace-pre-wrap">{{ $this->textOutput }}</pre>
		</flux:card>
	@endif

	<livewire:shoplist.add-product :$shoplist />
	<livewire:shoplist.edit-product :$shoplist />
</div>
