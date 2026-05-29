<?php

namespace Tests\Feature\Auth;

use App\Rules\PasswordComplexity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordComplexityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_must_be_at_least_10_characters(): void
    {
        $validator = Validator::make(
            ['password' => 'Short1!'],
            ['password' => ['required', new PasswordComplexity()]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('at least 10 characters', $validator->errors()->first('password'));
    }

    public function test_password_must_contain_uppercase_letter(): void
    {
        $validator = Validator::make(
            ['password' => 'lowercase123!'],
            ['password' => ['required', new PasswordComplexity()]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('uppercase letter', $validator->errors()->first('password'));
    }

    public function test_password_must_contain_lowercase_letter(): void
    {
        $validator = Validator::make(
            ['password' => 'UPPERCASE123!'],
            ['password' => ['required', new PasswordComplexity()]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('lowercase letter', $validator->errors()->first('password'));
    }

    public function test_password_must_contain_digit(): void
    {
        $validator = Validator::make(
            ['password' => 'NoDigitsHere!'],
            ['password' => ['required', new PasswordComplexity()]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('digit', $validator->errors()->first('password'));
    }

    public function test_password_must_contain_special_character(): void
    {
        $validator = Validator::make(
            ['password' => 'NoSpecial123'],
            ['password' => ['required', new PasswordComplexity()]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('special character', $validator->errors()->first('password'));
    }

    public function test_valid_password_passes_complexity_check(): void
    {
        $validator = Validator::make(
            ['password' => 'ValidPass123!'],
            ['password' => ['required', new PasswordComplexity()]]
        );

        $this->assertFalse($validator->fails());
    }

    public function test_password_with_all_requirements_passes(): void
    {
        $validPasswords = [
            'ComplexP@ss1',
            'MySecure#Pass2024',
            'Test!ng123Password',
            'Str0ng&Secure',
        ];

        foreach ($validPasswords as $password) {
            $validator = Validator::make(
                ['password' => $password],
                ['password' => ['required', new PasswordComplexity()]]
            );

            $this->assertFalse($validator->fails(), "Password '{$password}' should pass validation");
        }
    }

    public function test_password_complexity_returns_field_level_errors(): void
    {
        // Test with a password that fails multiple requirements
        $validator = Validator::make(
            ['password' => 'short'],
            ['password' => ['required', new PasswordComplexity()]]
        );

        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->get('password');
        
        // Should get the first error (minimum length)
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('at least 10 characters', $errors[0]);
    }
}
