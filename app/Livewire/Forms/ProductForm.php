<?php

namespace App\Livewire\Forms;

use App\Models\Product;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ProductForm extends Form
{
	#[Validate]
	public string $name = '';

	#[Validate(['required', 'numeric', 'min:0'])]
	public string $size = '';

	#[Validate(['required', 'string'])]
	public string $size_type = '';

	#[Validate(['required', 'string'])]
	public string $category = '';

	public function rules(): array {
		return [
			'name' => [
				'required',
				'string',
				'max:255',
				Rule::unique(Product::class)
					->where('size', $this->size)
					->where('size_type', $this->size_type),
			],
		];
	}

	public function store(): void {
		$this->validate();

		Product::create($this->only(['name', 'size', 'size_type', 'category']));

		$this->reset();
	}
}
