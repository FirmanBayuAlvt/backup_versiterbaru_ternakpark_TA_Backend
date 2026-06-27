<?php

namespace Database\Factories;

use App\Models\FeedPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedPurchaseFactory extends Factory
{
    protected $model = FeedPurchase::class;

    public function definition()
    {
        return [
            'date'           => $this->faker->dateTimeBetween('-1 year', 'now'),
            'supplier'       => $this->faker->company(),
            'feed_name'      => $this->faker->words(2, true),
            'price_per_unit' => $this->faker->randomFloat(2, 1000, 10000),
            'quantity'       => $this->faker->randomFloat(2, 10, 500),
            'unit'           => $this->faker->randomElement(['kg', 'sak']),
            'notes'          => $this->faker->optional()->sentence(),
        ];
    }
}