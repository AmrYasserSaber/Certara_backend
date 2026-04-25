<?php

declare(strict_types=1);

namespace App\Helpers;

final class InputValidator
{
    private const EGYPTIAN_MOBILE_REGEX = '/^01[0-2,5][0-9]{8}$/';

    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $result = self::validateField($data, $field, $ruleString);
            $data = $result['data'];
            if ($result['error'] !== null) {
                $errors[$field] = $result['error'];
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }

    private static function validateField(array $data, string $field, string $ruleString): array
    {
        $rules = array_values(array_filter(array_map('trim', explode('|', $ruleString))));
        $isRequired = in_array('required', $rules, true);
        $isNullable = in_array('nullable', $rules, true);

        $value = $data[$field] ?? null;

        if (($value === null || $value === '') && $isRequired) {
            return ['data' => $data, 'error' => "{$field} is required"];
        }

        if ($value === null || $value === '') {
            if ($isNullable) {
                $data[$field] = null;
            }
            return ['data' => $data, 'error' => null];
        }

        foreach ($rules as $rule) {
            if ($rule === 'required' || $rule === 'nullable') {
                continue;
            }

            if ($rule === 'trim') {
                if (!is_string($value)) {
                    return ['data' => $data, 'error' => "{$field} must be a string"];
                }
                $value = trim($value);
                $data[$field] = $value;
                continue;
            }

            if ($rule === 'string') {
                if (!is_string($value)) {
                    return ['data' => $data, 'error' => "{$field} must be a string"];
                }
                continue;
            }

            if ($rule === 'email') {
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return ['data' => $data, 'error' => "{$field} must be a valid email"];
                }
                continue;
            }

            if (preg_match('/^min:(\d+)$/', $rule, $m) === 1) {
                $min = (int) $m[1];
                if (!is_string($value) || mb_strlen($value) < $min) {
                    return ['data' => $data, 'error' => "{$field} must be at least {$min} characters"];
                }
                continue;
            }

            if (preg_match('/^max:(\d+)$/', $rule, $m) === 1) {
                $max = (int) $m[1];
                if (!is_string($value) || mb_strlen($value) > $max) {
                    return ['data' => $data, 'error' => "{$field} must be at most {$max} characters"];
                }
                continue;
            }

            if (str_starts_with($rule, 'regex:')) {
                $pattern = substr($rule, strlen('regex:'));
                if (!is_string($value) || $pattern === '' || @preg_match($pattern, '') === false) {
                    return ['data' => $data, 'error' => "{$field} has invalid validation rule"];
                }
                if (preg_match($pattern, $value) !== 1) {
                    return ['data' => $data, 'error' => "{$field} format is invalid"];
                }
                continue;
            }

            if ($rule === 'phone_eg') {
                if (!is_string($value) || preg_match(self::EGYPTIAN_MOBILE_REGEX, $value) !== 1) {
                    return ['data' => $data, 'error' => "{$field} must be a valid Egyptian phone number"];
                }
                continue;
            }
        }

        return ['data' => $data, 'error' => null];
    }
}

