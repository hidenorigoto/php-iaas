<?php

declare(strict_types=1);

namespace VmManagement\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use VmManagement\Exceptions\VMCreationException;

/**
 * Unit tests for VMCreationException class
 */
class VMCreationExceptionTest extends TestCase
{
    /**

     * @covers \VmManagement\Exceptions\VMCreationException

     */

    public function testDomainDefineFailedException(): void
    {
        $exception = VMCreationException::domainDefineFailed('test-vm');

        $this->assertInstanceOf(VMCreationException::class, $exception);
        $this->assertEquals('Failed to define VM domain "test-vm"', $exception->getMessage());
        $this->assertEquals(VMCreationException::ERROR_DOMAIN_DEFINE_FAILED, $exception->getCode());
        $this->assertEquals(['vm_name' => 'test-vm'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\VMCreationException


     */


    public function testDomainDefineFailedWithLibvirtError(): void
    {
        $exception = VMCreationException::domainDefineFailed('test-vm', 'Invalid XML');

        $this->assertInstanceOf(VMCreationException::class, $exception);
        $this->assertEquals('Failed to define VM domain "test-vm": Invalid XML', $exception->getMessage());
        $this->assertEquals(VMCreationException::ERROR_DOMAIN_DEFINE_FAILED, $exception->getCode());
        $this->assertEquals(['vm_name' => 'test-vm', 'libvirt_error' => 'Invalid XML'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\VMCreationException


     */


    public function testDomainStartFailedException(): void
    {
        $exception = VMCreationException::domainStartFailed('test-vm');

        $this->assertInstanceOf(VMCreationException::class, $exception);
        $this->assertEquals('Failed to start VM "test-vm"', $exception->getMessage());
        $this->assertEquals(VMCreationException::ERROR_DOMAIN_START_FAILED, $exception->getCode());
        $this->assertEquals(['vm_name' => 'test-vm'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\VMCreationException


     */


    public function testDomainNotFound(): void
    {
        $exception = VMCreationException::domainNotFound('test-vm');

        $this->assertInstanceOf(VMCreationException::class, $exception);
        $this->assertEquals('VM domain "test-vm" not found', $exception->getMessage());
        $this->assertEquals(VMCreationException::ERROR_DOMAIN_NOT_FOUND, $exception->getCode());
        $this->assertEquals(['vm_name' => 'test-vm'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\VMCreationException


     */


    public function testStoragePoolNotFound(): void
    {
        $exception = VMCreationException::storagePoolNotFound('default');

        $this->assertInstanceOf(VMCreationException::class, $exception);
        $this->assertEquals('Storage pool "default" not found', $exception->getMessage());
        $this->assertEquals(VMCreationException::ERROR_STORAGE_POOL_NOT_FOUND, $exception->getCode());
        $this->assertEquals(['pool_name' => 'default'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\VMCreationException


     */


    public function testVolumeCreateFailed(): void
    {
        $exception = VMCreationException::volumeCreateFailed('test-vm.qcow2');

        $this->assertInstanceOf(VMCreationException::class, $exception);
        $this->assertEquals('Failed to create storage volume "test-vm.qcow2"', $exception->getMessage());
        $this->assertEquals(VMCreationException::ERROR_VOLUME_CREATE_FAILED, $exception->getCode());
        $this->assertEquals(['volume_name' => 'test-vm.qcow2'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\VMCreationException


     */


    public function testDiskImageFailed(): void
    {
        $exception = VMCreationException::diskImageFailed('/path/to/disk.qcow2', 'Insufficient space');

        $this->assertInstanceOf(VMCreationException::class, $exception);
        $this->assertEquals('Failed to create disk image "/path/to/disk.qcow2": Insufficient space', $exception->getMessage());
        $this->assertEquals(VMCreationException::ERROR_DISK_IMAGE_FAILED, $exception->getCode());
        $this->assertEquals(['image_path' => '/path/to/disk.qcow2', 'error' => 'Insufficient space'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\VMCreationException


     */


    public function testXmlGenerationFailed(): void
    {
        $exception = VMCreationException::xmlGenerationFailed('test-vm', 'Invalid network configuration');

        $this->assertInstanceOf(VMCreationException::class, $exception);
        $this->assertEquals('Failed to generate XML configuration for VM "test-vm": Invalid network configuration', $exception->getMessage());
        $this->assertEquals(VMCreationException::ERROR_XML_GENERATION_FAILED, $exception->getCode());
        $this->assertEquals(['vm_name' => 'test-vm', 'error' => 'Invalid network configuration'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\VMCreationException


     */


    public function testVmAlreadyExists(): void
    {
        $exception = VMCreationException::vmAlreadyExists('test-vm');

        $this->assertInstanceOf(VMCreationException::class, $exception);
        $this->assertEquals('VM "test-vm" already exists', $exception->getMessage());
        $this->assertEquals(VMCreationException::ERROR_VM_ALREADY_EXISTS, $exception->getCode());
        $this->assertEquals(['vm_name' => 'test-vm'], $exception->getContext());
    }

    /**


     * @covers \VmManagement\Exceptions\VMCreationException


     */


    public function testSshInfoFailed(): void
    {
        $exception = VMCreationException::sshInfoFailed('test-vm', 'VM not running');

        $this->assertInstanceOf(VMCreationException::class, $exception);
        $this->assertEquals('Failed to get SSH info for VM "test-vm": VM not running', $exception->getMessage());
        $this->assertEquals(VMCreationException::ERROR_SSH_INFO_FAILED, $exception->getCode());
        $this->assertEquals(['vm_name' => 'test-vm', 'reason' => 'VM not running'], $exception->getContext());
    }
}
