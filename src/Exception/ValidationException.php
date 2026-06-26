<?php

namespace CoinRex\Exception;

/**
 * Exception thrown when input validation fails.
 *
 * @package CoinRex\Exception
 */
class ValidationException extends CoinRexException
{
    /**
     * @var array<string, string[]> Map of field names to error messages.
     */
    private array $errors = [];

    /**
     * Create a new validation exception.
     *
     * @param string               $message  The exception message.
     * @param array<string, string[]> $errors   Field-level validation errors.
     * @param int                  $code     The exception code.
     * @param \Throwable|null      $previous Previous exception for chaining.
     */
    public function __construct(
        string $message = 'Validation failed.',
        array $errors = [],
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get the field-level validation errors.
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
