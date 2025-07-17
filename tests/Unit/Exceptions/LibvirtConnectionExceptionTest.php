<?php

declare(strict_types=1);

namespace VmManagement\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use VmManagement\Exceptions\LibvirtConnectionException;

/**
 * Unit tests for LibvirtConnectionException class
 */
class LibvirtConnectionExceptionTest extends TestCase
{
    public function testConnectionFailedException(): void
    {
        $exception = LibvirtConnectionException::connectionFailed('qemu:///system');

        $this->assertInstanceOf(LibvirtConnectionException::class, $exception);
        $this->assertEquals('Failed to connect to libvirt at "qemu:///system"', $exception->getMessage());
        $this->assertEquals(LibvirtConnectionException::ERROR_CONNECTION_FAILED, $exception->getCode());
        $this->assertEquals(['uri' => 'qemu:///system'], $exception->getContext());
    }

    public function testConnectionFailedWithLibvirtError(): void
    {
        $exception = LibvirtConnectionException::connectionFailed('qemu:///system', 'Permission denied');

        $this->assertInstanceOf(LibvirtConnectionException::class, $exception);
        $this->assertEquals('Failed to connect to libvirt at "qemu:///system": Permission denied', $exception->getMessage());
        $this->assertEquals(LibvirtConnectionException::ERROR_CONNECTION_FAILED, $exception->getCode());
        $this->assertEquals(['uri' => 'qemu:///system', 'libvirt_error' => 'Permission denied'], $exception->getContext());
    }

    public function testDisconnectionFailedException(): void
    {
        $exception = LibvirtConnectionException::disconnectionFailed();

        $this->assertInstanceOf(LibvirtConnectionException::class, $exception);
        $this->assertEquals('Failed to disconnect from libvirt', $exception->getMessage());
        $this->assertEquals(LibvirtConnectionException::ERROR_DISCONNECTION_FAILED, $exception->getCode());
        $this->assertEquals([], $exception->getContext());
    }

    public function testDisconnectionFailedWithLibvirtError(): void
    {
        $exception = LibvirtConnectionException::disconnectionFailed('Internal error');

        $this->assertInstanceOf(LibvirtConnectionException::class, $exception);
        $this->assertEquals('Failed to disconnect from libvirt: Internal error', $exception->getMessage());
        $this->assertEquals(LibvirtConnectionException::ERROR_DISCONNECTION_FAILED, $exception->getCode());
        $this->assertEquals(['libvirt_error' => 'Internal error'], $exception->getContext());
    }

    public function testAlreadyConnectedException(): void
    {
        $exception = LibvirtConnectionException::alreadyConnected();

        $this->assertInstanceOf(LibvirtConnectionException::class, $exception);
        $this->assertEquals('Already connected to libvirt', $exception->getMessage());
        $this->assertEquals(LibvirtConnectionException::ERROR_ALREADY_CONNECTED, $exception->getCode());
        $this->assertEquals([], $exception->getContext());
    }

    public function testNotConnectedException(): void
    {
        $exception = LibvirtConnectionException::notConnected();

        $this->assertInstanceOf(LibvirtConnectionException::class, $exception);
        $this->assertEquals('Not connected to libvirt', $exception->getMessage());
        $this->assertEquals(LibvirtConnectionException::ERROR_NOT_CONNECTED, $exception->getCode());
        $this->assertEquals([], $exception->getContext());
    }

    public function testPermissionDeniedException(): void
    {
        $exception = LibvirtConnectionException::permissionDenied('qemu:///system');

        $this->assertInstanceOf(LibvirtConnectionException::class, $exception);
        $this->assertEquals('Permission denied connecting to libvirt at "qemu:///system"', $exception->getMessage());
        $this->assertEquals(LibvirtConnectionException::ERROR_PERMISSION_DENIED, $exception->getCode());
        $this->assertEquals(['uri' => 'qemu:///system'], $exception->getContext());
    }
}
