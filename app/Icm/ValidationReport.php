<?php

namespace App\Icm;

/**
 * Every problem the validator found in a folder, ready to show to the owner.
 */
readonly class ValidationReport
{
    /**
     * @param  list<array{file: string, field: ?string, message: string}>  $errors  In the order they were found.
     */
    public function __construct(public array $errors = []) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Whether any error was found in the file at this path.
     */
    public function hasErrorsFor(string $file): bool
    {
        foreach ($this->errors as $error) {
            if ($error['file'] === $file) {
                return true;
            }
        }

        return false;
    }

    /**
     * One "<file>: <message>" line per error, grouped by file in path order.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $sorted = $this->errors;

        usort($sorted, fn (array $a, array $b) => strcmp($a['file'], $b['file']));

        return array_map(fn (array $error) => "{$error['file']}: {$error['message']}", $sorted);
    }
}
