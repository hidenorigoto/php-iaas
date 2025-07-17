<?php

declare(strict_types=1);

namespace VmManagement\Tests\Integration;

use PHPUnit\Framework\TestCase;
use VmManagement\SimpleVM;
use VmManagement\VMManager;

/**
 * End-to-end integration tests for complete VM lifecycle
 *
 * These tests verify the full VM creation, startup, SSH connectivity,
 * and cleanup flow with real libvirt backend when available.
 */
class EndToEndTest extends TestCase
{
    private VMManager $vmManager;
    private array $createdVMs = [];
    private bool $hasLibvirt = false;

    protected function setUp(): void
    {
        $this->vmManager = new VMManager();

        // Check if libvirt is available
        $this->hasLibvirt = $this->checkLibvirtAvailability();

        if (! $this->hasLibvirt) {
            $this->markTestSkipped('Libvirt not available, skipping end-to-end tests');
        }
    }

    protected function tearDown(): void
    {
        // Clean up any VMs created during tests
        foreach ($this->createdVMs as $vmName) {
            $this->cleanupVM($vmName);
        }
    }

    /**
     * Test complete VM lifecycle for user1
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\VMManager::getSSHInfo
     * @covers \VmManagement\VMManager::isVMRunning
     */
    public function testCompleteVMLifecycleForUser1(): void
    {
        $vmName = 'test-vm-user1-' . uniqid();
        $this->createdVMs[] = $vmName;

        // Create VM instance
        $vm = new SimpleVM(
            name: $vmName,
            user: 'user1',
            cpu: 2,
            memory: 2048,
            disk: 20
        );

        // Test VM creation and startup
        $this->vmManager->createAndStartVM($vm);

        // Verify VM is running
        $this->assertTrue($this->vmManager->isVMRunning($vmName));

        // Test SSH information retrieval
        $sshInfo = $this->vmManager->getSSHInfo($vm);
        $this->assertIsArray($sshInfo);
        $this->assertArrayHasKey('ip', $sshInfo);
        $this->assertArrayHasKey('username', $sshInfo);
        $this->assertArrayHasKey('password', $sshInfo);

        // Verify IP is in user1's range (192.168.100.x)
        $this->assertMatchesRegularExpression('/^192\.168\.100\./', $sshInfo['ip']);

        // Test SSH connectivity (if possible)
        if ($this->canTestSSH()) {
            $this->assertTrue($this->testSSHConnection($sshInfo));
        }
    }

    /**
     * Test complete VM lifecycle for user2
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\VMManager::getSSHInfo
     */
    public function testCompleteVMLifecycleForUser2(): void
    {
        $vmName = 'test-vm-user2-' . uniqid();
        $this->createdVMs[] = $vmName;

        $vm = new SimpleVM(
            name: $vmName,
            user: 'user2',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $this->vmManager->createAndStartVM($vm);
        $this->assertTrue($this->vmManager->isVMRunning($vmName));

        $sshInfo = $this->vmManager->getSSHInfo($vm);
        $this->assertIsArray($sshInfo);

        // Verify IP is in user2's range (192.168.101.x)
        $this->assertMatchesRegularExpression('/^192\.168\.101\./', $sshInfo['ip']);
    }

    /**
     * Test complete VM lifecycle for user3
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\VMManager::getSSHInfo
     */
    public function testCompleteVMLifecycleForUser3(): void
    {
        $vmName = 'test-vm-user3-' . uniqid();
        $this->createdVMs[] = $vmName;

        $vm = new SimpleVM(
            name: $vmName,
            user: 'user3',
            cpu: 4,
            memory: 4096,
            disk: 40
        );

        $this->vmManager->createAndStartVM($vm);
        $this->assertTrue($this->vmManager->isVMRunning($vmName));

        $sshInfo = $this->vmManager->getSSHInfo($vm);
        $this->assertIsArray($sshInfo);

        // Verify IP is in user3's range (192.168.102.x)
        $this->assertMatchesRegularExpression('/^192\.168\.102\./', $sshInfo['ip']);
    }

    /**
     * Test concurrent VM creation for multiple users
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     */
    public function testConcurrentVMCreation(): void
    {
        $vms = [];

        // Create VMs for all users
        foreach (['user1', 'user2', 'user3'] as $user) {
            $vmName = "test-concurrent-{$user}-" . uniqid();
            $this->createdVMs[] = $vmName;

            $vm = new SimpleVM(
                name: $vmName,
                user: $user,
                cpu: 1,
                memory: 1024,
                disk: 10
            );

            $vms[$user] = $vm;
            $this->vmManager->createAndStartVM($vm);
        }

        // Verify all VMs are running
        foreach ($vms as $user => $vm) {
            $this->assertTrue($this->vmManager->isVMRunning($vm->name));

            // Verify SSH info can be retrieved
            $sshInfo = $this->vmManager->getSSHInfo($vm);
            $this->assertIsArray($sshInfo);
            $this->assertArrayHasKey('ip', $sshInfo);
        }
    }

    /**
     * Test VM listing functionality
     *
     * @covers \VmManagement\VMManager::listVMs
     * @covers \VmManagement\VMManager::listAllVMs
     */
    public function testVMListingFunctionality(): void
    {
        // Create a test VM
        $vmName = 'test-list-vm-' . uniqid();
        $this->createdVMs[] = $vmName;

        $vm = new SimpleVM(
            name: $vmName,
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $this->vmManager->createAndStartVM($vm);

        // Test VM listing
        $vms = $this->vmManager->listVMs();
        $this->assertIsArray($vms);

        // Find our test VM in the list
        $foundVM = false;
        foreach ($vms as $listedVM) {
            if ($listedVM['name'] === $vmName) {
                $foundVM = true;
                $this->assertEquals('running', $listedVM['status']);

                break;
            }
        }

        $this->assertTrue($foundVM, "Created VM not found in VM list");
    }

    /**
     * Test resource allocation verification
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     */
    public function testResourceAllocationVerification(): void
    {
        $vmName = 'test-resources-' . uniqid();
        $this->createdVMs[] = $vmName;

        $vm = new SimpleVM(
            name: $vmName,
            user: 'user1',
            cpu: 4,
            memory: 4096,
            disk: 50
        );

        $this->vmManager->createAndStartVM($vm);

        // Verify VM is running
        $this->assertTrue($this->vmManager->isVMRunning($vmName));

        // Get domain information to verify resource allocation
        $domain = $this->vmManager->getDomainByName($vmName);
        if ($domain !== false) {
            $domainInfo = @libvirt_domain_get_info($domain);
            if ($domainInfo !== false) {
                // Verify CPU and memory allocation
                $this->assertEquals(4, $domainInfo['nrVirtCpu']);
                $this->assertEquals(4096 * 1024, $domainInfo['memory']); // libvirt reports in KB
            }
        }
    }

    /**
     * Test VM cleanup and resource deallocation
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     */
    public function testVMCleanupAndResourceDeallocation(): void
    {
        $vmName = 'test-cleanup-' . uniqid();

        $vm = new SimpleVM(
            name: $vmName,
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        // Create and start VM
        $this->vmManager->createAndStartVM($vm);
        $this->assertTrue($this->vmManager->isVMRunning($vmName));

        // Perform cleanup
        $this->cleanupVM($vmName);

        // Verify VM is no longer running
        $this->assertFalse($this->vmManager->isVMRunning($vmName));
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
     * Test SSH connection to a VM
     */
    private function testSSHConnection(array $sshInfo): bool
    {
        $command = sprintf(
            'ssh -o ConnectTimeout=5 -o StrictHostKeyChecking=no %s@%s echo "test"',
            escapeshellarg($sshInfo['username']),
            escapeshellarg($sshInfo['ip'])
        );

        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Check if SSH testing is possible
     */
    private function canTestSSH(): bool
    {
        return ! empty(exec('which ssh'));
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
