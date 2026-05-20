<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;

final class AuthValidator
{
    public static function validateLogin(array $data): array
    {
        $errors = [];

        // Email validation
        if (empty($data['email'])) {
            $errors['email'][] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Email must be a valid email address';
        } elseif (strlen($data['email']) > 255) {
            $errors['email'][] = 'Email cannot be longer than 255 characters';
        }

        // Password validation
        if (empty($data['password'])) {
            $errors['password'][] = 'Password is required';
        } elseif (!is_string($data['password'])) {
            $errors['password'][] = 'Password must be a string';
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        return [
            'email' => trim(strtolower($data['email'])),
            'password' => $data['password']
        ];
    }

    public static function validateLogout(array $data): array
    {
        // No validation needed for logout
        return [];
    }

    public static function validateRefresh(array $data): array
    {
        // No validation needed for refresh - token comes from cookie
        return [];
    }

    public static function validateForgotPassword(array $data): array
    {
        $errors = [];

        // Email validation
        if (empty($data['email'])) {
            $errors['email'][] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Email must be a valid email address';
        } elseif (strlen($data['email']) > 255) {
            $errors['email'][] = 'Email cannot be longer than 255 characters';
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        return [
            'email' => trim(strtolower($data['email']))
        ];
    }

    public static function validateResetPassword(array $data): array
    {
        $errors = [];

        // Token validation
        if (empty($data['token'])) {
            $errors['token'][] = 'Reset token is required';
        } elseif (!is_string($data['token'])) {
            $errors['token'][] = 'Reset token must be a string';
        }

        // Password validation
        if (empty($data['password'])) {
            $errors['password'][] = 'Password is required';
        } elseif (!is_string($data['password'])) {
            $errors['password'][] = 'Password must be a string';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'][] = 'Password must be at least 8 characters long';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $data['password'])) {
            $errors['password'][] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
        }

        // Confirm password validation
        if (empty($data['password_confirmation'])) {
            $errors['password_confirmation'][] = 'Password confirmation is required';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'][] = 'Password confirmation must match password';
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        return [
            'token' => $data['token'],
            'password' => $data['password']
        ];
    }

    public static function validateMfaVerify(array $data): array
    {
        $errors = [];

        // Challenge token validation
        if (empty($data['challenge_token'])) {
            $errors['challenge_token'][] = 'Challenge token is required';
        } elseif (!is_string($data['challenge_token'])) {
            $errors['challenge_token'][] = 'Challenge token must be a string';
        }

        // MFA code validation
        if (empty($data['code'])) {
            $errors['code'][] = 'MFA code is required';
        } elseif (!is_string($data['code'])) {
            $errors['code'][] = 'MFA code must be a string';
        } elseif (!preg_match('/^[0-9]{6}$/', $data['code'])) {
            $errors['code'][] = 'MFA code must be 6 digits';
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        return [
            'challenge_token' => $data['challenge_token'],
            'code' => $data['code']
        ];
    }
}