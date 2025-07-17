<?php

declare(strict_types=1);

namespace VmManagement\Tests\Integration;

use PHPUnit\Framework\TestCase;
use VmManagement\Exceptions\LibvirtConnectionException;
use VmManagement\Exceptions\ValidationException;
use VmManagement\Exceptions\VMCreationException;
use VmManagement\SimpleVM;
use VmManagement\VMManager;

/**
 * Comprehensive error handling tests for various failure scenarios
 *
 * These tests verify that the system handles errors gracefully and provides
 * meaningful error messages for different failure conditions.
 */
class ErrorHandlingTest extends TestCase
{
    private VMManager $vmManager;
    private array $createdVMs = [];

    protected function setUp(): void
    {
        $this->vmManager = new VMManager();
    }

    protected function tearDown(): void
    {
        // Clean up any VMs created during tests
        foreach ($this->createdVMs as $vmName) {
            $this->cleanupVM($vmName);
        }
    }

    /**
     * Test libvirt connection failure scenarios
     *
     * @covers \VmManagement\VMManager::connect
     * @covers \VmManagement\Exceptions\LibvirtConnectionException
     */
    public function testLibvirtConnectionFailures(): void
    {
        // Test connection to invalid URI
        $vmManagerWithBadURI = new VMManager();

        // Since we can't easily mock libvirt_connect, we'll test the exception handling
        // by attempting operations when not connected
        $this->expectException(LibvirtConnectionException::class);

        // Try to create VM without connecting
        $vm = new SimpleVM(
            name: 'test-vm',
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $vmManagerWithBadURI->createAndStartVM($vm);
    }

    /**
     * Test VM creation failures
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\Exceptions\VMCreationException
     */
    public function testVMCreationFailures(): void
    {
        // Test with invalid parameters
        $this->expectException(ValidationException::class);

        $vm = new SimpleVM(
            name: '', // Invalid empty name
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $this->vmManager->createAndStartVM($vm);
    }

    /**
     * Test invalid user scenarios
     *
     * @covers \VmManagement\VMManager::validateVMParams
     * @covers \VmManagement\Exceptions\ValidationException
     */
    public function testInvalidUserScenarios(): void
    {
        $invalidUsers = ['', 'invalid_user', 'user4', 'admin', 'root'];

        foreach ($invalidUsers as $invalidUser) {
            $this->expectException(ValidationException::class);

            $vm = new SimpleVM(
                name: 'test-vm',
                user: $invalidUser,
                cpu: 1,
                memory: 1024,
                disk: 10
            );

            try {
                $this->vmManager->createAndStartVM($vm);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid user', $e->getMessage());

                continue;
            }

            $this->fail("Expected ValidationException for invalid user: {$invalidUser}");
        }
    }

    /**
     * Test invalid VM name scenarios
     *
     * @covers \VmManagement\VMManager::validateVMParams
     * @covers \VmManagement\Exceptions\ValidationException
     */
    public function testInvalidVMNameScenarios(): void
    {
        $invalidNames = [
            '',
            'vm with spaces',
            'vm@with#special',
            'vm.with.dots',
            str_repeat('a', 256), // Too long
            '123-starts-with-number',
            'VM-UPPERCASE',
        ];

        foreach ($invalidNames as $invalidName) {
            $this->expectException(ValidationException::class);

            $vm = new SimpleVM(
                name: $invalidName,
                user: 'user1',
                cpu: 1,
                memory: 1024,
                disk: 10
            );

            try {
                $this->vmManager->createAndStartVM($vm);
            } catch (ValidationException $e) {
                // Different invalid names have different error messages
                $this->assertTrue(
                    str_contains($e->getMessage(), 'Invalid') ||
                    str_contains($e->getMessage(), 'empty') ||
                    str_contains($e->getMessage(), 'characters') ||
                    str_contains($e->getMessage(), 'too long'),
                    "Expected validation error for invalid VM name: {$invalidName}, got: " . $e->getMessage()
                );

                continue;
            }

            $this->fail("Expected ValidationException for invalid VM name: {$invalidName}");
        }
    }

    /**
     * Test invalid resource allocation scenarios
     *
     * @covers \VmManagement\VMManager::validateVMParams
     * @covers \VmManagement\Exceptions\ValidationException
     */
    public function testInvalidResourceAllocationScenarios(): void
    {
        $invalidConfigs = [
            ['cpu' => 0, 'memory' => 1024, 'disk' => 10, 'field' => 'CPU'],
            ['cpu' => 17, 'memory' => 1024, 'disk' => 10, 'field' => 'CPU'],
            ['cpu' => 1, 'memory' => 0, 'disk' => 10, 'field' => 'memory'],
            ['cpu' => 1, 'memory' => 32769, 'disk' => 10, 'field' => 'memory'],
            ['cpu' => 1, 'memory' => 1024, 'disk' => 0, 'field' => 'disk'],
            ['cpu' => 1, 'memory' => 1024, 'disk' => 1025, 'field' => 'disk'],
        ];

        foreach ($invalidConfigs as $config) {
            $this->expectException(ValidationException::class);

            $vm = new SimpleVM(
                name: 'test-vm',
                user: 'user1',
                cpu: $config['cpu'],
                memory: $config['memory'],
                disk: $config['disk']
            );

            try {
                $this->vmManager->createAndStartVM($vm);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid', $e->getMessage());

                continue;
            }

            $this->fail("Expected ValidationException for invalid {$config['field']} config");
        }
    }

    /**
     * Test duplicate VM name scenarios
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\Exceptions\VMCreationException
     */
    public function testDuplicateVMNameScenarios(): void
    {
        if (! $this->checkLibvirtAvailability()) {
            $this->markTestSkipped('Libvirt not available');
        }

        $vmName = 'test-duplicate-' . uniqid();
        $this->createdVMs[] = $vmName;

        // Create first VM
        $vm1 = new SimpleVM(
            name: $vmName,
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $this->vmManager->createAndStartVM($vm1);

        // Try to create second VM with same name
        $vm2 = new SimpleVM(
            name: $vmName,
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $this->expectException(VMCreationException::class);
        $this->vmManager->createAndStartVM($vm2);
    }

    /**
     * Test network configuration failures
     *
     * @covers \VmManagement\VMManager::createUserNetwork
     * @covers \VmManagement\Exceptions\NetworkException
     */
    public function testNetworkConfigurationFailures(): void
    {
        // Test with invalid user for network creation
        $this->expectException(ValidationException::class);
        $this->vmManager->createUserNetwork('invalid_user');
    }

    /**
     * Test SSH connection failures
     *
     * @covers \VmManagement\VMManager::getSSHInfo
     * @covers \VmManagement\Exceptions\VMCreationException
     */
    public function testSSHConnectionFailures(): void
    {
        // Test SSH info for non-existent VM
        $vm = new SimpleVM(
            name: 'non-existent-vm',
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $sshInfo = $this->vmManager->getSSHInfo($vm);

        // Should return error information
        $this->assertIsArray($sshInfo);
        $this->assertArrayHasKey('error', $sshInfo);
        $this->assertStringContainsString('not running', $sshInfo['error']);
    }

    /**
     * Test resource exhaustion scenarios
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\Exceptions\VMCreationException
     */
    public function testResourceExhaustionScenarios(): void
    {
        if (! $this->checkLibvirtAvailability()) {
            $this->markTestSkipped('Libvirt not available');
        }

        // Try to create VM with maximum resources
        $vm = new SimpleVM(
            name: 'test-max-resources-' . uniqid(),
            user: 'user1',
            cpu: 16,
            memory: 32768,
            disk: 1000
        );

        $this->createdVMs[] = $vm->name;

        // This might fail due to resource constraints
        // The test is to ensure we handle the failure gracefully
        try {
            $this->vmManager->createAndStartVM($vm);
            $this->assertTrue(true, 'VM creation succeeded');
        } catch (VMCreationException $e) {
            $this->assertStringContainsString('Failed to', $e->getMessage());
            $this->assertNotEmpty($e->getContext());
        }
    }

    /**
     * Test storage pool failures
     *
     * @covers \VmManagement\VMManager::getStoragePool
     * @covers \VmManagement\Exceptions\VMCreationException
     */
    public function testStoragePoolFailures(): void
    {
        if (! $this->checkLibvirtAvailability()) {
            $this->markTestSkipped('Libvirt not available');
        }

        $this->expectException(VMCreationException::class);

        // Try to get non-existent storage pool
        $this->vmManager->getStoragePool('non-existent-pool');
    }

    /**
     * Test disk creation failures
     *
     * @covers \VmManagement\VMManager::createDiskVolume
     * @covers \VmManagement\Exceptions\VMCreationException
     */
    public function testDiskCreationFailures(): void
    {
        if (! $this->checkLibvirtAvailability()) {
            $this->markTestSkipped('Libvirt not available');
        }

        $this->expectException(VMCreationException::class);

        // Try to create disk with invalid parameters
        $this->vmManager->createDiskVolume('', 0, 'non-existent-pool');
    }

    /**
     * Test XML generation failures
     *
     * @covers \VmManagement\VMManager::buildVMConfig
     * @covers \VmManagement\Exceptions\VMCreationException
     */
    public function testXMLGenerationFailures(): void
    {
        // Test with invalid user
        $vm = new SimpleVM(
            name: 'test-vm',
            user: 'invalid_user',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $this->expectException(VMCreationException::class);
        $this->vmManager->buildVMConfig($vm, '/tmp/test.qcow2');
    }

    /**
     * Test logging functionality during errors
     *
     * @covers \VmManagement\VMManager::logError
     * @covers \VmManagement\VMManager::logInfo
     */
    public function testLoggingFunctionalityDuringErrors(): void
    {
        // Test that errors are properly logged
        $vm = new SimpleVM(
            name: 'test-logging',
            user: 'invalid_user',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        try {
            $this->vmManager->createAndStartVM($vm);
        } catch (ValidationException $e) {
            // Expected exception
            $this->assertStringContainsString('Invalid user', $e->getMessage());
        }

        // Verify logger is working
        $logger = $this->vmManager->getLogger();
        $this->assertNotNull($logger);

        // Test manual logging
        $this->vmManager->logError('Test error message', ['test' => 'context']);
        $this->vmManager->logInfo('Test info message', ['test' => 'context']);

        $this->assertTrue(true, 'Logging completed without errors');
    }

    /**
     * Test context information in exceptions
     *
     * @covers \VmManagement\Exceptions\VMManagementException::getContext
     */
    public function testContextInformationInExceptions(): void
    {
        $vm = new SimpleVM(
            name: 'test-context',
            user: 'invalid_user',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        try {
            $this->vmManager->createAndStartVM($vm);
        } catch (ValidationException $e) {
            $context = $e->getContext();
            $this->assertIsArray($context);
            $this->assertArrayHasKey('user', $context);
            $this->assertEquals('invalid_user', $context['user']);
        }
    }

    /**
     * Test error recovery scenarios
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     */
    public function testErrorRecoveryScenarios(): void
    {
        if (! $this->checkLibvirtAvailability()) {
            $this->markTestSkipped('Libvirt not available');
        }

        // Test that after an error, we can still create valid VMs
        $invalidVM = new SimpleVM(
            name: 'test-invalid',
            user: 'invalid_user',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        try {
            $this->vmManager->createAndStartVM($invalidVM);
        } catch (ValidationException $e) {
            // Expected
        }

        // Now create a valid VM
        $validVM = new SimpleVM(
            name: 'test-valid-' . uniqid(),
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $this->createdVMs[] = $validVM->name;

        // This should succeed
        $this->vmManager->createAndStartVM($validVM);
        $this->assertTrue($this->vmManager->isVMRunning($validVM->name));
    }

    /**
     * Test timeout scenarios
     *
     * @covers \VmManagement\VMManager::waitForSSHReady
     */
    public function testTimeoutScenarios(): void
    {
        // Test SSH timeout with invalid IP
        $result = $this->vmManager->waitForSSHReady('192.168.255.255', 'ubuntu', 1);
        $this->assertFalse($result);
    }

    /**
     * Check if libvirt is available for testing
     */
    private function checkLibvirtAvailability(): bool
    {
        try {
            $connection = $this->vmManager->connect();

            return $connection && $this->vmManager->isConnected();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clean up a VM (stop and undefine)
     */
    private function cleanupVM(string $vmName): void
    {
        try {
            if ($this->vmManager->isConnected()) {
                $domain = $this->vmManager->getDomainByName($vmName);
                if ($domain !== false) {
                    // Stop the VM if running
                    @libvirt_domain_shutdown($domain);

                    // Wait a moment for shutdown
                    sleep(2);

                    // Force stop if still running
                    @libvirt_domain_destroy($domain);

                    // Undefine the VM
                    @libvirt_domain_undefine($domain);
                }
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors in tests
        }
    }
}
