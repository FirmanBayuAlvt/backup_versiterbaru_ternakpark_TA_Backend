<?php

namespace Database\Factories;

use App\Models\Logbook;
use App\Models\Livestock;
use App\Models\Pen;
use Illuminate\Database\Eloquent\Factories\Factory;

class LogbookFactory extends Factory
{
    protected $model = Logbook::class;

    public function definition()
    {
        return [
            'livestock_id'      => Livestock::factory(),
            'event_date'        => $this->faker->dateTimeBetween('-1 year', 'now'),
            'event_type'        => $this->faker->randomElement(['Vaksin', 'Sakit', 'Pindah Kandang', 'Melahirkan', 'Kawin', 'IB', 'Mati', 'Terjual']),
            'description'       => $this->faker->sentence(),
            'handling'          => $this->faker->optional()->sentence(),
            'new_tag'           => $this->faker->optional()->bothify('NEW###'),
            'new_pen_id'        => $this->faker->optional()->passthrough(Pen::factory()),
            'new_pen_category'  => $this->faker->optional()->randomElement(['Fattening', 'Kawin', 'Melahirkan']),
            'officer_name'      => $this->faker->optional()->name(),
            'pregnancy_date'    => $this->faker->optional()->date(),
        ];
    }
}