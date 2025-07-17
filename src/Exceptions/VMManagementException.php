<?php

declare(strict_types=1);

namespace VmManagement\Exceptions;

use Exception;

/**
 * Base exception class for all VM management related errors
 */
class VMManagementException extends Exception
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional context information about the error
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set additional context information
     *
     * @param array<string, mixed> $context
     * @return void
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
