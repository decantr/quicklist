<?php

use App\Enums\Category;
use App\Enums\SizeType;
use App\Livewire\Forms\ProductForm;
use Livewire\Component;

new class extends Component {
	public ProductForm $form;

	public function save(bool $close = true): void {
		$this->form->store();

		$this->dispatch('product-created');

		if ($close) {
			$this->dispatch('modal-close', name: 'create-product');
		}

		Flux::toast(__('Product created successfully.'));
	}
};
?>

<form wire:submit="save" class="space-y-6">
	<flux:input
		wire:model="form.name"
		:label="__('Name')"
		placeholder="{{ __('Apple') }}"
		autofocus
	/>

	<div class="grid grid-cols-2 gap-4">
		<flux:input
			wire:model="form.size"
			type="number"
			inputmode="decimal"
			step="0.01"
			:label="__('Size')"
			placeholder="150"
		/>

		<flux:select wire:model="form.size_type" :label="__('Unit')">
			<flux:select.option value="">{{ __('Select unit') }}</flux:select.option>
			@foreach (SizeType::cases() as $case)
				<flux:select.option :value="$case->value">{{ $case->name }} ({{ $case->value }})</flux:select.option>
			@endforeach
		</flux:select>
	</div>

	<flux:select wire:model="form.category" :label="__('Category')">
		<flux:select.option value="">{{ __('Select category') }}</flux:select.option>
		@foreach (Category::cases() as $case)
			<flux:select.option :value="$case->value">{{ $case->name }}</flux:select.option>
		@endforeach
	</flux:select>

	<div class="flex justify-end gap-3">
		<flux:modal.close>
			<flux:button variant="ghost">
				{{ __('Cancel') }}
			</flux:button>
		</flux:modal.close>

		<flux:button wire:click="save(false)" variant="filled">
			{{ __('Create & Add Another') }}
		</flux:button>

		<flux:button type="submit" variant="primary">
			{{ __('Create Product') }}
		</flux:button>
	</div>
</form>
