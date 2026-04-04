<?php

use App\Models\Product;
use App\Enums\SizeType;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
	#[Validate('required|string|max:255')]
	public string $name = '';

	#[Validate('required|numeric|min:0')]
	public string $size = '';

	#[Validate(['required', 'string'])]
	public string $size_type = '';

	public function save(): void {
		$this->validate();

		Product::create([
			'name' => $this->name,
			'size' => $this->size,
			'size_type' => $this->size_type,
		]);

		$this->reset(['name', 'size', 'size_type']);

		$this->dispatch('product-created');

		Flux::toast(__('Product created successfully.'));
	}
};
?>

<form wire:submit="save" class="space-y-6">
	<flux:input
		wire:model="name"
		:label="__('Name')"
		placeholder="{{ __('Apple') }}"
		autofocus
	/>

	<div class="grid grid-cols-2 gap-4">
		<flux:input
			wire:model="size"
			type="number"
			inputmode="decimal"
			step="0.01"
			:label="__('Size')"
			placeholder="150"
		/>

		<flux:select wire:model="size_type" :label="__('Unit')">
			<flux:select.option value="">{{ __('Select unit') }}</flux:select.option>
			@foreach (SizeType::cases() as $case)
				<flux:select.option :value="$case->value">{{ $case->name }} ({{ $case->value }})</flux:select.option>
			@endforeach
		</flux:select>
	</div>

	<div class="flex justify-end">
		<flux:button type="submit" variant="primary">
			{{ __('Create Product') }}
		</flux:button>
	</div>
</form>
