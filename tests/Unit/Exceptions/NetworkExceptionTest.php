<?php

declare(strict_types=1);

namespace VmManagement\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use VmManagement\Exceptions\NetworkException;

/**
 * Unit tests for NetworkException class
 */
class NetworkExceptionTest extends TestCase
{
    /**

     * @covers \VmManagement\Exceptions\NetworkException

     */

    public function testNetworkDefineFailed(): void
    {
        $exception = NetworkException::networkDefineFailed('vm-network-100');

        $this->assertInstanceOf(NetworkException::class, $exception);
        $this->assertEquals('Failed to define network "vm-network-100"', $exception->getMessage());
        $this->assertEquals(NetworkException::ERROR_NETWORK_DEFINE_FAILED, $exception->getCode());
        $this->assertEquals(['network_name' => 'vm-network-100'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\NetworkException


     */


    public function testNetworkDefineFailedWithLibvirtError(): void
    {
        $exception = NetworkException::networkDefineFailed('vm-network-100', 'Invalid XML');

        $this->assertInstanceOf(NetworkException::class, $exception);
        $this->assertEquals('Failed to define network "vm-network-100": Invalid XML', $exception->getMessage());
        $this->assertEquals(NetworkException::ERROR_NETWORK_DEFINE_FAILED, $exception->getCode());
        $this->assertEquals(['network_name' => 'vm-network-100', 'libvirt_error' => 'Invalid XML'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\NetworkException


     */


    public function testNetworkStartFailed(): void
    {
        $exception = NetworkException::networkStartFailed('vm-network-100');

        $this->assertInstanceOf(NetworkException::class, $exception);
        $this->assertEquals('Failed to start network "vm-network-100"', $exception->getMessage());
        $this->assertEquals(NetworkException::ERROR_NETWORK_START_FAILED, $exception->getCode());
        $this->assertEquals(['network_name' => 'vm-network-100'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\NetworkException


     */


    public function testNetworkNotFound(): void
    {
        $exception = NetworkException::networkNotFound('vm-network-100');

        $this->assertInstanceOf(NetworkException::class, $exception);
        $this->assertEquals('Network "vm-network-100" not found', $exception->getMessage());
        $this->assertEquals(NetworkException::ERROR_NETWORK_NOT_FOUND, $exception->getCode());
        $this->assertEquals(['network_name' => 'vm-network-100'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\NetworkException


     */


    public function testInvalidNetworkConfig(): void
    {
        $exception = NetworkException::invalidNetworkConfig('user4', 'Unknown user');

        $this->assertInstanceOf(NetworkException::class, $exception);
        $this->assertEquals('Invalid network configuration for user "user4": Unknown user', $exception->getMessage());
        $this->assertEquals(NetworkException::ERROR_INVALID_NETWORK_CONFIG, $exception->getCode());
        $this->assertEquals(['user' => 'user4', 'reason' => 'Unknown user'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\NetworkException


     */


    public function testDhcpLeaseFailed(): void
    {
        $exception = NetworkException::dhcpLeaseFailed('vm-network-100');

        $this->assertInstanceOf(NetworkException::class, $exception);
        $this->assertEquals('Failed to retrieve DHCP leases for network "vm-network-100"', $exception->getMessage());
        $this->assertEquals(NetworkException::ERROR_DHCP_LEASE_FAILED, $exception->getCode());
        $this->assertEquals(['network_name' => 'vm-network-100'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\NetworkException


     */


    public function testDhcpLeaseFailedWithLibvirtError(): void
    {
        $exception = NetworkException::dhcpLeaseFailed('vm-network-100', 'Network not active');

        $this->assertInstanceOf(NetworkException::class, $exception);
        $this->assertEquals('Failed to retrieve DHCP leases for network "vm-network-100": Network not active', $exception->getMessage());
        $this->assertEquals(NetworkException::ERROR_DHCP_LEASE_FAILED, $exception->getCode());
        $this->assertEquals(['network_name' => 'vm-network-100', 'libvirt_error' => 'Network not active'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\NetworkException


     */


    public function testIpAddressNotFound(): void
    {
        $exception = NetworkException::ipAddressNotFound('test-vm', 'vm-network-100');

        $this->assertInstanceOf(NetworkException::class, $exception);
        $this->assertEquals('IP address not found for VM "test-vm" on network "vm-network-100"', $exception->getMessage());
        $this->assertEquals(NetworkException::ERROR_IP_ADDRESS_NOT_FOUND, $exception->getCode());
        $this->assertEquals(['vm_name' => 'test-vm', 'network_name' => 'vm-network-100'], $exception->getContext());
    }
}
