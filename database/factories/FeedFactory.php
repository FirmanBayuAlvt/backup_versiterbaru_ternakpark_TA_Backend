<?php

namespace Database\Factories;

use App\Models\Feed;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedFactory extends Factory
{
    /**
     * Nama model yang terkait dengan factory ini.
     *
     * @var string
     */
    protected $model = Feed::class;

    /**
     * Mendefinisikan nilai default untuk model Feed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'          => $this->faker->unique()->word(),
            'category'      => $this->faker->randomElement(['silase', 'konsentrat', 'hijauan']),
            'current_stock' => $this->faker->randomFloat(2, 0, 500),
            'price_per_kg'  => $this->faker->randomFloat(2, 1000, 10000),
            'unit'          => 'kg',
            'is_active'     => true,
        ];
    }
}