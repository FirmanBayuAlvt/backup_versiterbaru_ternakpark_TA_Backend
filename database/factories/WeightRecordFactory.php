<?php

namespace Database\Factories;

use App\Models\WeightRecord;
use App\Models\Livestock;
use Illuminate\Database\Eloquent\Factories\Factory;

class WeightRecordFactory extends Factory
{
    protected $model = WeightRecord::class;

    public function definition()
    {
        return [
            'livestock_id' => Livestock::factory(),
            'weight_kg'    => $this->faker->randomFloat(2, 10, 100),
            'record_date'  => $this->faker->dateTimeBetween('-6 months', 'now'),
            'notes'        => $this->faker->sentence(),
        ];
    }
}