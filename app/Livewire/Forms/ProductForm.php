<?php

namespace App\Livewire\Forms;

use App\Models\Product;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ProductForm extends Form
{
	public ?Product $product = null;

	#[Validate]
	public string $name = '';

	#[Validate(['required', 'integer', 'min:0'])]
	public string $size = '';

	#[Validate(['required', 'string'])]
	public string $size_type = '';

	#[Validate(['required', 'string'])]
	public string $category = '';

	public function setProduct(Product $product): void {
		$this->product = $product;

		$this->name = $product->name;
		$this->size = (string) $product->size;
		$this->size_type = $product->size_type->value;
		$this->category = $product->category->value;
	}

	public function rules(): array {
		return [
			'name' => [
				'required',
				'string',
				'max:255',
				Rule::unique(Product::class)
					->where('size', $this->size)
					->where('size_type', $this->size_type)
					->ignore($this->product?->id),
			],
		];
	}

	public function store(): void {
		$this->validate();

		Product::create($this->only(['name', 'size', 'size_type', 'category']));

		$this->reset();
	}

	public function update(): void {
		$this->validate();

		$this->product->update($this->only(['name', 'size', 'size_type', 'category']));
	}
}
