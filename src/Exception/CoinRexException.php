<?php

namespace CoinRex\Exception;

/**
 * Base exception class for all CoinRex domain exceptions.
 *
 * @package CoinRex\Exception
 */
class CoinRexException extends \RuntimeException
{
    /**
     * @var array<string, mixed> Additional context data for the exception.
     */
    private array $context = [];

    /**
     * Create a new CoinRexException with optional context.
     *
     * @param string          $message  The exception message.
     * @param int             $code     The exception code.
     * @param \Throwable|null $previous Previous exception for chaining.
     * @param array           $context  Additional context data.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the context data associated with this exception.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
