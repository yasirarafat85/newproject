<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;

/**
 * নিয়মভিত্তিক ইনপুট যাচাই।
 *
 *   Validator::make($request->all(), [
 *       'email'    => 'required|email|max:190',
 *       'password' => 'required|min:8|confirmed',
 *       'dept_id'  => 'required|exists:departments,id',
 *   ])->validate();
 */
final class Validator
{
    private array $errors = [];

    private function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $labels = [],
    ) {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $rules = explode('|', $ruleString);

            // nullable/optional ফিল্ড ফাঁকা থাকলে বাকি নিয়ম পরীক্ষা করার মানে নেই
            if (!in_array('required', $rules, true) && $this->isEmpty($value)) {
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                $this->apply($field, $value, $name, $parameter);

                if (isset($this->errors[$field])) {
                    break; // প্রতি ফিল্ডে প্রথম ভুলটিই দেখাই
                }
            }
        }

        return $this->errors === [];
    }

    public function validate(): array
    {
        if (!$this->passes()) {
            throw new ValidationException($this->errors, $this->safeOld());
        }

        $validated = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function apply(string $field, mixed $value, string $rule, ?string $parameter): void
    {
        $label = $this->labels[$field] ?? $field;

        switch ($rule) {
            case 'required':
                if ($this->isEmpty($value)) {
                    $this->fail($field, "{$label} অবশ্যই পূরণ করতে হবে।");
                }
                break;

            case 'email':
                if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->fail($field, "{$label} একটি সঠিক ইমেইল ঠিকানা হতে হবে।");
                }
                break;

            case 'min':
                if (mb_strlen((string) $value, 'UTF-8') < (int) $parameter) {
                    $this->fail($field, "{$label} অন্তত {$parameter} অক্ষরের হতে হবে।");
                }
                break;

            case 'max':
                if (mb_strlen((string) $value, 'UTF-8') > (int) $parameter) {
                    $this->fail($field, "{$label} সর্বোচ্চ {$parameter} অক্ষরের হতে পারে।");
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->fail($field, "{$label} একটি সংখ্যা হতে হবে।");
                }
                break;

            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->fail($field, "{$label} একটি পূর্ণ সংখ্যা হতে হবে।");
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $parameter);
                if (!in_array((string) $value, $allowed, true)) {
                    $this->fail($field, "{$label} এর মান গ্রহণযোগ্য নয়।");
                }
                break;

            case 'confirmed':
                if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                    $this->fail($field, "{$label} দুবার একই রকম লিখুন।");
                }
                break;

            case 'same':
                if (($this->data[$parameter] ?? null) !== $value) {
                    $this->fail($field, "{$label} মিলছে না।");
                }
                break;

            case 'exists':
                // exists:table,column — মান ডেটাবেসে আছে কি না
                [$table, $column] = array_pad(explode(',', (string) $parameter), 2, 'id');
                if (!QueryBuilder::table($table)->where($column, $value)->exists()) {
                    $this->fail($field, "নির্বাচিত {$label} খুঁজে পাওয়া যায়নি।");
                }
                break;

            case 'unique':
                // unique:table,column,ignoreId — এডিট করার সময় নিজের রো বাদ দিতে
                [$table, $column, $ignore] = array_pad(explode(',', (string) $parameter), 3, null);
                $query = QueryBuilder::table($table)->where($column ?? $field, $value);
                if ($ignore !== null && $ignore !== '') {
                    $query->where('id', '!=', $ignore);
                }
                if ($query->exists()) {
                    $this->fail($field, "এই {$label} ইতিমধ্যে ব্যবহৃত হয়েছে।");
                }
                break;

            case 'date':
                if (strtotime((string) $value) === false) {
                    $this->fail($field, "{$label} একটি সঠিক তারিখ হতে হবে।");
                }
                break;

            case 'phone':
                if (!preg_match('/^[0-9+\-\s()]{6,20}$/', (string) $value)) {
                    $this->fail($field, "{$label} একটি সঠিক ফোন নম্বর হতে হবে।");
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    $this->fail($field, "{$label} এর গঠন সঠিক নয়।");
                }
                break;
        }
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    /** ফর্ম রি-ফিল করার জন্য পুরনো মান — পাসওয়ার্ড কখনো ফেরত যায় না। */
    private function safeOld(): array
    {
        $old = $this->data;
        foreach (array_keys($old) as $key) {
            if (str_contains((string) $key, 'password') || $key === '_token') {
                unset($old[$key]);
            }
        }

        return $old;
    }
}
