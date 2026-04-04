<?php

namespace App\Models;

use Database\Factories\ShoplistFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Shoplist extends Model
{
	/** @use HasFactory<ShoplistFactory> */
	use HasFactory;

	protected $fillable = [
		'user_id',
		'date',
	];

	public function user(): BelongsTo {
		return $this->belongsTo(User::class);
	}

	public function products(): BelongsToMany {
		return $this->belongsToMany(Product::class)
			->withPivot('quantity')
			->withTimestamps();
	}

	protected function formattedList(): Attribute {
		return Attribute::make(
			get: function () {
				return $this->products
					->groupBy(fn ($product) => $product->category->name)
					->map(function ($products) {
						return $products->map(fn ($product) => "{$product->pivot->quantity}x {$product->name} ({$product->size} {$product->size_type->value})")
							->implode("\n");
					})
					->implode("\n\n");
			}
		);
	}

	protected function casts(): array {
		return [
			'date' => 'date',
		];
	}
}
