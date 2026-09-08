<?php

class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            $this->errors[$field][] = "$field is required";
        }
        return $this;
    }

    public function numeric(string $field, bool $allowNull = true): self
    {
        if (isset($this->data[$field]) && $this->data[$field] !== null && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = "$field must be numeric";
        }
        return $this;
    }

    public function nonNegative(string $field): self
    {
        if (isset($this->data[$field]) && is_numeric($this->data[$field]) && $this->data[$field] < 0) {
            $this->errors[$field][] = "$field cannot be negative";
        }
        return $this;
    }

    public function positiveInt(string $field): self
    {
        if (isset($this->data[$field]) && (!is_numeric($this->data[$field]) || (int)$this->data[$field] <= 0)) {
            $this->errors[$field][] = "$field must be a positive integer";
        }
        return $this;
    }

    public function in(string $field, array $allowed): self
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field][] = "$field must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }

    public function email(string $field): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "$field must be a valid email address";
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
