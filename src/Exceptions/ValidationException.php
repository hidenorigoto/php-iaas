<?php

declare(strict_types=1);

namespace VmManagement\Exceptions;

/**
 * Exception thrown when input validation fails
 */
class ValidationException extends VMManagementException
{
    public const ERROR_INVALID_VM_NAME = 4001;
    public const ERROR_INVALID_USER = 4002;
    public const ERROR_INVALID_CPU = 4003;
    public const ERROR_INVALID_MEMORY = 4004;
    public const ERROR_INVALID_DISK = 4005;
    public const ERROR_EMPTY_PARAMETER = 4006;
    public const ERROR_PARAMETER_TOO_LONG = 4007;
    public const ERROR_INVALID_CHARACTERS = 4008;
    public const ERROR_PARAMETER_OUT_OF_RANGE = 4009;

    public static function invalidVMName(string $vmName, string $reason): self
    {
        return new self(
            sprintf('Invalid VM name "%s": %s', $vmName, $reason),
            self::ERROR_INVALID_VM_NAME,
            null,
            ['vm_name' => $vmName, 'reason' => $reason]
        );
    }

    public static function invalidUser(string $user): self
    {
        return new self(
            sprintf('Invalid user "%s". Must be one of: user1, user2, user3', $user),
            self::ERROR_INVALID_USER,
            null,
            ['user' => $user, 'valid_users' => ['user1', 'user2', 'user3']]
        );
    }

    public static function invalidCPU(int $cpu): self
    {
        return new self(
            sprintf('Invalid CPU count %d. Must be between 1 and 16', $cpu),
            self::ERROR_INVALID_CPU,
            null,
            ['cpu' => $cpu, 'min' => 1, 'max' => 16]
        );
    }

    public static function invalidMemory(int $memory): self
    {
        return new self(
            sprintf('Invalid memory %d MB. Must be between 512 and 32768', $memory),
            self::ERROR_INVALID_MEMORY,
            null,
            ['memory' => $memory, 'min' => 512, 'max' => 32768]
        );
    }

    public static function invalidDisk(int $disk): self
    {
        return new self(
            sprintf('Invalid disk size %d GB. Must be between 10 and 1000', $disk),
            self::ERROR_INVALID_DISK,
            null,
            ['disk' => $disk, 'min' => 10, 'max' => 1000]
        );
    }

    public static function emptyParameter(string $parameter): self
    {
        return new self(
            sprintf('Parameter "%s" cannot be empty', $parameter),
            self::ERROR_EMPTY_PARAMETER,
            null,
            ['parameter' => $parameter]
        );
    }

    public static function parameterTooLong(string $parameter, int $length, int $maxLength): self
    {
        return new self(
            sprintf('Parameter "%s" is too long (%d characters). Maximum allowed: %d', $parameter, $length, $maxLength),
            self::ERROR_PARAMETER_TOO_LONG,
            null,
            ['parameter' => $parameter, 'length' => $length, 'max_length' => $maxLength]
        );
    }

    public static function invalidCharacters(string $parameter, string $value): self
    {
        return new self(
            sprintf('Parameter "%s" contains invalid characters: "%s"', $parameter, $value),
            self::ERROR_INVALID_CHARACTERS,
            null,
            ['parameter' => $parameter, 'value' => $value]
        );
    }

    public static function parameterOutOfRange(string $parameter, mixed $value, mixed $min, mixed $max): self
    {
        return new self(
            sprintf('Parameter "%s" value %s is out of range. Must be between %s and %s', $parameter, (string)$value, (string)$min, (string)$max),
            self::ERROR_PARAMETER_OUT_OF_RANGE,
            null,
            ['parameter' => $parameter, 'value' => $value, 'min' => $min, 'max' => $max]
        );
    }
}
