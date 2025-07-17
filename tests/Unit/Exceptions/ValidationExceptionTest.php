<?php

declare(strict_types=1);

namespace VmManagement\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use VmManagement\Exceptions\ValidationException;

/**
 * Unit tests for ValidationException class
 */
class ValidationExceptionTest extends TestCase
{
    public function testInvalidVMNameException(): void
    {
        $exception = ValidationException::invalidVMName('test-vm', 'contains invalid characters');

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals('Invalid VM name "test-vm": contains invalid characters', $exception->getMessage());
        $this->assertEquals(ValidationException::ERROR_INVALID_VM_NAME, $exception->getCode());
        $this->assertEquals(['vm_name' => 'test-vm', 'reason' => 'contains invalid characters'], $exception->getContext());
    }

    public function testInvalidUserException(): void
    {
        $exception = ValidationException::invalidUser('invalid-user');

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals('Invalid user "invalid-user". Must be one of: user1, user2, user3', $exception->getMessage());
        $this->assertEquals(ValidationException::ERROR_INVALID_USER, $exception->getCode());
        $this->assertEquals(['user' => 'invalid-user', 'valid_users' => ['user1', 'user2', 'user3']], $exception->getContext());
    }

    public function testInvalidCPUException(): void
    {
        $exception = ValidationException::invalidCPU(0);

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals('Invalid CPU count 0. Must be between 1 and 16', $exception->getMessage());
        $this->assertEquals(ValidationException::ERROR_INVALID_CPU, $exception->getCode());
        $this->assertEquals(['cpu' => 0, 'min' => 1, 'max' => 16], $exception->getContext());
    }

    public function testInvalidMemoryException(): void
    {
        $exception = ValidationException::invalidMemory(256);

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals('Invalid memory 256 MB. Must be between 512 and 32768', $exception->getMessage());
        $this->assertEquals(ValidationException::ERROR_INVALID_MEMORY, $exception->getCode());
        $this->assertEquals(['memory' => 256, 'min' => 512, 'max' => 32768], $exception->getContext());
    }

    public function testInvalidDiskException(): void
    {
        $exception = ValidationException::invalidDisk(5);

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals('Invalid disk size 5 GB. Must be between 10 and 1000', $exception->getMessage());
        $this->assertEquals(ValidationException::ERROR_INVALID_DISK, $exception->getCode());
        $this->assertEquals(['disk' => 5, 'min' => 10, 'max' => 1000], $exception->getContext());
    }

    public function testEmptyParameterException(): void
    {
        $exception = ValidationException::emptyParameter('name');

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals('Parameter "name" cannot be empty', $exception->getMessage());
        $this->assertEquals(ValidationException::ERROR_EMPTY_PARAMETER, $exception->getCode());
        $this->assertEquals(['parameter' => 'name'], $exception->getContext());
    }

    public function testParameterTooLongException(): void
    {
        $exception = ValidationException::parameterTooLong('name', 51, 50);

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals('Parameter "name" is too long (51 characters). Maximum allowed: 50', $exception->getMessage());
        $this->assertEquals(ValidationException::ERROR_PARAMETER_TOO_LONG, $exception->getCode());
        $this->assertEquals(['parameter' => 'name', 'length' => 51, 'max_length' => 50], $exception->getContext());
    }

    public function testInvalidCharactersException(): void
    {
        $exception = ValidationException::invalidCharacters('name', 'test vm!');

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals('Parameter "name" contains invalid characters: "test vm!"', $exception->getMessage());
        $this->assertEquals(ValidationException::ERROR_INVALID_CHARACTERS, $exception->getCode());
        $this->assertEquals(['parameter' => 'name', 'value' => 'test vm!'], $exception->getContext());
    }

    public function testParameterOutOfRangeException(): void
    {
        $exception = ValidationException::parameterOutOfRange('cpu', 17, 1, 16);

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals('Parameter "cpu" value 17 is out of range. Must be between 1 and 16', $exception->getMessage());
        $this->assertEquals(ValidationException::ERROR_PARAMETER_OUT_OF_RANGE, $exception->getCode());
        $this->assertEquals(['parameter' => 'cpu', 'value' => 17, 'min' => 1, 'max' => 16], $exception->getContext());
    }

    public function testContextCanBeSetAndRetrieved(): void
    {
        $exception = ValidationException::emptyParameter('test');
        $newContext = ['additional' => 'data'];

        $exception->setContext($newContext);
        $this->assertEquals($newContext, $exception->getContext());
    }
}
