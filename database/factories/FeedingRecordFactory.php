<?php

namespace Database\Factories;

use App\Models\FeedingRecord;
use App\Models\Feed;
use App\Models\Livestock;
use App\Models\Pen;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedingRecordFactory extends Factory
{
    protected $model = FeedingRecord::class;

    public function definition()
    {
        return [
            'feed_id'      => Feed::factory(),
            'livestock_id' => Livestock::factory(),
            'pen_id'       => Pen::factory(),
            'quantity_kg'  => $this->faker->randomFloat(2, 1, 50),
            'feeding_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'notes'        => $this->faker->sentence(),
        ];
    }
}