<?php

namespace Database\Factories;

use App\Models\Pen;
use Illuminate\Database\Eloquent\Factories\Factory;

class PenFactory extends Factory
{
    protected $model = Pen::class;

    /**
     * Daftar kategori kandang yang valid sesuai dengan enum di database.
     * 
     * @var array<int, string>
     */
    protected $validCategories = [
        'Melahirkan',
        'Menyusui',
        'Kawin',
        'Karantina',
        'Persiapan Breeding',
        'Lapak',
        'Fattening',          // Gunakan 'Fattening' bukan 'Fattening Percobaan'
        'Prasapih',
        'Kambing',
        'Kambing Jantan',
        'Breeding',
    ];

    public function definition(): array
    {
        return [
            'name'     => $this->faker->unique()->word(),
            'code'     => null, // hindari duplikasi kode
            'category' => $this->faker->randomElement($this->validCategories),
            'abk'      => $this->faker->randomElement(Pen::ABK_OPTIONS),
            'capacity' => $this->faker->numberBetween(10, 100),
            'status'   => 'active',
        ];
    }
}