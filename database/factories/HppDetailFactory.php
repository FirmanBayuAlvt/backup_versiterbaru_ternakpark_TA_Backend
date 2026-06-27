<?php

namespace Database\Factories;

use App\Models\HppDetail;
use App\Models\Livestock;
use Illuminate\Database\Eloquent\Factories\Factory;

class HppDetailFactory extends Factory
{
    protected $model = HppDetail::class;

    public function definition()
    {
        return [
            'livestock_id'      => Livestock::factory(),
            'purchase_cost'     => $this->faker->randomFloat(2, 500000, 3000000),
            'feed_cost'         => $this->faker->randomFloat(2, 100000, 1000000),
            'operational_cost'  => $this->faker->randomFloat(2, 50000, 500000),
        ];
    }
}