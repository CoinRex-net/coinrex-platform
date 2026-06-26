<?php

namespace CoinRex\Http;

/**
 * Centralized HTTP request wrapper for CoinRex.
 *
 * Provides safe access to request data with consistent validation.
 *
 * @package CoinRex\Http
 */
class Request
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $body;

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $files;

    /**
     * @param array<string, mixed> $query  $_GET data.
     * @param array<string, mixed> $body   $_POST data.
     * @param array<string, mixed> $server $_SERVER data.
     * @param array<string, mixed> $files  $_FILES data.
     */
    public function __construct(
        array $query = [],
        array $body = [],
        array $server = [],
        array $files = []
    ) {
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
        $this->files = $files;
    }

    /**
     * Create a Request from the current PHP superglobals.
     *
     * @return self
     */
    public static function fromGlobals(): self
    {
        return new self(
            $_GET,
            $_POST,
            $_SERVER,
            $_FILES
        );
    }

    /**
     * Get a value from the query string ($_GET).
     *
     * @param string $key     The parameter name.
     * @param mixed  $default Default value if not found.
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get a value from the request body ($_POST).
     *
     * @param string $key     The parameter name.
     * @param mixed  $default Default value if not found.
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Get all request body data.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->body;
    }

    /**
     * Get the HTTP method.
     *
     * @return string Uppercase HTTP method (GET, POST, etc.).
     */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Check if the request method matches.
     *
     * @param string $method The method to check (case-insensitive).
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    /**
     * Get the request URI path.
     *
     * @return string
     */
    public function path(): string
    {
        $uri = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return $uri ?: '/';
    }

    /**
     * Get a server variable.
     *
     * @param string $key     The server variable name.
     * @param mixed  $default Default value if not found.
     * @return mixed
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Get an uploaded file.
     *
     * @param string $key The file input name.
     * @return array|null The file data array, or null if not present.
     */
    public function file(string $key): ?array
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE
            ? $this->files[$key]
            : null;
    }

    /**
     * Check if the request has a file for the given key.
     *
     * @param string $key The file input name.
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        return $this->file($key) !== null;
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    public function ip(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            $ip = $this->server($header);
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    /**
     * Sanitize a string input.
     *
     * @param string $value The raw input value.
     * @return string The sanitized value.
     */
    public static function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate that required fields are present.
     *
     * @param string[] $fields List of required field names.
     * @return array<string, string[]> Map of missing fields to error messages.
     */
    public function validateRequired(array $fields): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $value = $this->input($field);
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $errors[$field][] = "The {$field} field is required.";
            }
        }

        return $errors;
    }
}
