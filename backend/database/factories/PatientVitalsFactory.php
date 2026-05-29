<?php

namespace Database\Factories;

use App\Models\PatientRecord;
use App\Models\PatientVitals;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PatientVitalsFactory extends Factory
{
    protected $model = PatientVitals::class;

    public function definition(): array
    {
        $weight = $this->faker->numberBetween(50, 100);
        $height = $this->faker->numberBetween(150, 200);
        $heightInMeters = $height / 100;
        $bmi = round($weight / ($heightInMeters * $heightInMeters), 2);

        return [
            'id' => Str::uuid(),
            'tenant_id' => app('current_tenant_id'),
            'patient_record_id' => PatientRecord::factory(),
            'recorded_by' => User::factory(),
            'blood_pressure' => $this->faker->numberBetween(90, 140) . '/' . $this->faker->numberBetween(60, 90),
            'temperature' => $this->faker->randomFloat(1, 36.0, 38.0),
            'pulse_rate' => $this->faker->numberBetween(60, 100),
            'respiratory_rate' => $this->faker->numberBetween(12, 20),
            'oxygen_saturation' => $this->faker->numberBetween(95, 100),
            'weight' => $weight,
            'height' => $height,
            'bmi' => $bmi,
            'blood_glucose' => $this->faker->numberBetween(70, 140),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
