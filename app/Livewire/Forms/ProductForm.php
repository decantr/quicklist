<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class ProductForm extends Form
{
	#[Validate('required|string|max:255')]
	public string $name = '';

	#[Validate('required|numeric|min:0')]
	public string $size = '';

	#[Validate(['required', 'string'])]
	public string $size_type = '';

	#[Validate(['required', 'string'])]
	public string $category = '';

	public function store(): void {
		$this->validate();

		\App\Models\Product::create($this->only(['name', 'size', 'size_type', 'category']));

		$this->reset();
	}
}
