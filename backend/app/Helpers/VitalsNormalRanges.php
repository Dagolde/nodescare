<?php

namespace App\Helpers;

class VitalsNormalRanges
{
    // Blood Pressure (mmHg)
    public const SYSTOLIC_BP_MIN = 90;
    public const SYSTOLIC_BP_MAX = 120;
    public const DIASTOLIC_BP_MIN = 60;
    public const DIASTOLIC_BP_MAX = 80;

    // Temperature (°C)
    public const TEMPERATURE_MIN = 36.1;
    public const TEMPERATURE_MAX = 37.2;

    // Pulse Rate (bpm)
    public const PULSE_MIN = 60;
    public const PULSE_MAX = 100;

    // Respiratory Rate (breaths/min)
    public const RESPIRATORY_RATE_MIN = 12;
    public const RESPIRATORY_RATE_MAX = 20;

    // Oxygen Saturation (%)
    public const OXYGEN_SATURATION_MIN = 95;
    public const OXYGEN_SATURATION_MAX = 100;

    // BMI
    public const BMI_MIN = 18.5;
    public const BMI_MAX = 24.9;

    /**
     * Check if systolic blood pressure is within normal range.
     *
     * @param int|float $systolic
     * @return bool
     */
    public static function isNormalSystolic($systolic): bool
    {
        return $systolic >= self::SYSTOLIC_BP_MIN && $systolic <= self::SYSTOLIC_BP_MAX;
    }

    /**
     * Check if diastolic blood pressure is within normal range.
     *
     * @param int|float $diastolic
     * @return bool
     */
    public static function isNormalDiastolic($diastolic): bool
    {
        return $diastolic >= self::DIASTOLIC_BP_MIN && $diastolic <= self::DIASTOLIC_BP_MAX;
    }

    /**
     * Check if temperature is within normal range.
     *
     * @param float $temperature
     * @return bool
     */
    public static function isNormalTemperature(float $temperature): bool
    {
        return $temperature >= self::TEMPERATURE_MIN && $temperature <= self::TEMPERATURE_MAX;
    }

    /**
     * Check if pulse rate is within normal range.
     *
     * @param int $pulseRate
     * @return bool
     */
    public static function isNormalPulse(int $pulseRate): bool
    {
        return $pulseRate >= self::PULSE_MIN && $pulseRate <= self::PULSE_MAX;
    }

    /**
     * Check if respiratory rate is within normal range.
     *
     * @param int $respiratoryRate
     * @return bool
     */
    public static function isNormalRespiratoryRate(int $respiratoryRate): bool
    {
        return $respiratoryRate >= self::RESPIRATORY_RATE_MIN && $respiratoryRate <= self::RESPIRATORY_RATE_MAX;
    }

    /**
     * Check if oxygen saturation is within normal range.
     *
     * @param int $oxygenSaturation
     * @return bool
     */
    public static function isNormalOxygenSaturation(int $oxygenSaturation): bool
    {
        return $oxygenSaturation >= self::OXYGEN_SATURATION_MIN && $oxygenSaturation <= self::OXYGEN_SATURATION_MAX;
    }

    /**
     * Check if BMI is within normal range.
     *
     * @param float $bmi
     * @return bool
     */
    public static function isNormalBMI(float $bmi): bool
    {
        return $bmi >= self::BMI_MIN && $bmi <= self::BMI_MAX;
    }

    /**
     * Calculate BMI from weight and height.
     *
     * @param float $weight Weight in kilograms
     * @param float $height Height in centimeters
     * @return float BMI value
     */
    public static function calculateBMI(float $weight, float $height): float
    {
        $heightInMeters = $height / 100;
        return round($weight / ($heightInMeters * $heightInMeters), 2);
    }

    /**
     * Parse blood pressure string and check if both values are normal.
     *
     * @param string $bloodPressure Format: "120/80"
     * @return array ['systolic' => int, 'diastolic' => int, 'is_normal' => bool]
     */
    public static function parseAndCheckBloodPressure(string $bloodPressure): array
    {
        $parts = explode('/', $bloodPressure);
        
        if (count($parts) !== 2) {
            return [
                'systolic' => null,
                'diastolic' => null,
                'is_normal' => false,
            ];
        }

        $systolic = (int) trim($parts[0]);
        $diastolic = (int) trim($parts[1]);

        return [
            'systolic' => $systolic,
            'diastolic' => $diastolic,
            'is_normal' => self::isNormalSystolic($systolic) && self::isNormalDiastolic($diastolic),
        ];
    }

    /**
     * Get all normal ranges as an array.
     *
     * @return array
     */
    public static function getAllRanges(): array
    {
        return [
            'blood_pressure' => [
                'systolic' => [
                    'min' => self::SYSTOLIC_BP_MIN,
                    'max' => self::SYSTOLIC_BP_MAX,
                    'unit' => 'mmHg',
                ],
                'diastolic' => [
                    'min' => self::DIASTOLIC_BP_MIN,
                    'max' => self::DIASTOLIC_BP_MAX,
                    'unit' => 'mmHg',
                ],
            ],
            'temperature' => [
                'min' => self::TEMPERATURE_MIN,
                'max' => self::TEMPERATURE_MAX,
                'unit' => '°C',
            ],
            'pulse_rate' => [
                'min' => self::PULSE_MIN,
                'max' => self::PULSE_MAX,
                'unit' => 'bpm',
            ],
            'respiratory_rate' => [
                'min' => self::RESPIRATORY_RATE_MIN,
                'max' => self::RESPIRATORY_RATE_MAX,
                'unit' => 'breaths/min',
            ],
            'oxygen_saturation' => [
                'min' => self::OXYGEN_SATURATION_MIN,
                'max' => self::OXYGEN_SATURATION_MAX,
                'unit' => '%',
            ],
            'bmi' => [
                'min' => self::BMI_MIN,
                'max' => self::BMI_MAX,
                'unit' => '',
            ],
        ];
    }
}
