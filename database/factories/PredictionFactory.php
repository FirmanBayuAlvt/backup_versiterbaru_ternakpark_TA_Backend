<?php

namespace Database\Factories;

use App\Models\Prediction;
use App\Models\Livestock;
use Illuminate\Database\Eloquent\Factories\Factory;

class PredictionFactory extends Factory
{
    protected $model = Prediction::class;

    public function definition()
    {
        return [
            'livestock_id'     => Livestock::factory(),
            'prediction_days'  => $this->faker->numberBetween(7, 90),
            'predicted_gain'   => $this->faker->randomFloat(3, 0.5, 15),
            'confidence'       => $this->faker->randomFloat(2, 0.5, 0.99),
            'interval_lower'   => $this->faker->randomFloat(3, 0.2, 5),
            'interval_upper'   => $this->faker->randomFloat(3, 6, 20),
            'recommendations'  => [$this->faker->sentence()],
            'input_features'   => [
                'initial_weight'   => $this->faker->randomFloat(2, 15, 40),
                'age_days'         => $this->faker->numberBetween(30, 500),
                'feed_silase'      => $this->faker->randomFloat(2, 10, 200),
                'feed_concentrate' => $this->faker->randomFloat(2, 5, 100),
                'gender'           => $this->faker->randomElement(['male', 'female']),
                'pen_category'     => $this->faker->randomElement(['Fattening', 'Kawin']),
            ],
        ];
    }
}