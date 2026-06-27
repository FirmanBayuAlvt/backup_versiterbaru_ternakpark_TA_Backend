<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'               => $this->faker->uuid(),
            'type'             => 'App\Notifications\TestNotification',
            'notifiable_type'  => 'App\Models\User',
            'notifiable_id'    => User::factory(),
            'data'             => [
                'title'   => $this->faker->sentence(4),
                'message' => $this->faker->paragraph(2),
            ],
            'read_at'          => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }
}