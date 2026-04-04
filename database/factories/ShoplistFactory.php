<?php

namespace Database\Factories;

use App\Models\Shoplist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shoplist>
 */
class ShoplistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => $this->faker->date(),
        ];
    }
}
