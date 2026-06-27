<?php

namespace Database\Factories;

use App\Models\Livestock;
use App\Models\Pen;
use Illuminate\Database\Eloquent\Factories\Factory;

class LivestockFactory extends Factory
{
    protected $model = Livestock::class;

    public function definition()
    {
        return [
            'ear_tag'          => $this->faker->unique()->bothify('SMPL#####'),
            'breed_type'       => $this->faker->randomElement(['domba_lokal', 'domba_garut', 'domba_dorper']),
            'gender'           => $this->faker->randomElement(['male', 'female']),
            'birth_date'       => $this->faker->dateTimeBetween('-2 years', 'now'),
            'initial_weight'   => $this->faker->randomFloat(2, 15, 40),
            'health_status'    => $this->faker->randomElement(['excellent', 'good', 'fair', 'poor']),
            'condition'        => $this->faker->randomElement(['sehat', 'hamil', 'menyusui', 'sakit']),
            'date_in'          => $this->faker->dateTimeBetween('-1 year', 'now'),
            'status'           => true,
            'pen_id'           => Pen::factory(),
            'image_url'        => null,
            'notes'            => null,
            'purchase_price'   => $this->faker->randomFloat(2, 500000, 3000000),
        ];
    }
}