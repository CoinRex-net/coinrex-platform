<?php

namespace CoinRex\Http;

/**
 * Standardized HTTP response wrapper for CoinRex.
 *
 * Provides a consistent structure for JSON responses across the platform.
 *
 * @package CoinRex\Http
 */
class Response
{
    /** @var int HTTP status code */
    private int $statusCode;

    /** @var array<string, mixed> Response payload data */
    private array $data;

    /** @var array<string, string> Response headers */
    private array $headers;

    /**
     * @param int                  $statusCode HTTP status code.
     * @param array<string, mixed> $data       Response payload.
     * @param array<string, string> $headers   Additional headers.
     */
    public function __construct(
        int $statusCode = 200,
        array $data = [],
        array $headers = []
    ) {
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->headers = $headers;
    }

    /**
     * Create a success response.
     *
     * @param array<string, mixed> $data       Response data.
     * @param int                  $statusCode HTTP status code.
     * @param array<string, string> $headers   Additional headers.
     * @return self
     */
    public static function success(
        array $data = [],
        int $statusCode = 200,
        array $headers = []
    ): self {
        return new self($statusCode, array_merge(['success' => true], $data), $headers);
    }

    /**
     * Create an error response.
     *
     * @param string               $message    Error message.
     * @param int                  $statusCode HTTP status code.
     * @param array<string, mixed> $extra      Additional error data.
     * @param array<string, string> $headers   Additional headers.
     * @return self
     */
    public static function error(
        string $message,
        int $statusCode = 400,
        array $extra = [],
        array $headers = []
    ): self {
        return new self($statusCode, array_merge([
            'success' => false,
            'message' => $message,
        ], $extra), $headers);
    }

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $this->data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        exit;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the response data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
