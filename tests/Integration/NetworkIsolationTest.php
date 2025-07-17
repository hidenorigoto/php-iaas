<?php

declare(strict_types=1);

namespace VmManagement\Tests\Integration;

use PHPUnit\Framework\TestCase;
use VmManagement\SimpleVM;
use VmManagement\VMManager;

/**
 * Network isolation and VLAN separation tests
 *
 * These tests verify that VMs in different VLANs are properly isolated
 * and that network configuration is correct for each user.
 */
class NetworkIsolationTest extends TestCase
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
            $this->markTestSkipped('Libvirt not available, skipping network isolation tests');
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
     * Test VLAN network creation for all users
     *
     * @covers \VmManagement\VMManager::createUserNetwork
     * @covers \VmManagement\VMManager::ensureUserNetwork
     */
    public function testVLANNetworkCreation(): void
    {
        $users = ['user1', 'user2', 'user3'];

        foreach ($users as $user) {
            // Test network creation
            $result = $this->vmManager->createUserNetwork($user);
            $this->assertTrue($result);

            // Verify network exists
            $this->assertTrue($this->vmManager->networkExists($user));

            // Verify network name
            $networkName = $this->vmManager->getNetworkName($user);
            $expectedName = "vm-network-" . $this->getVlanId($user);
            $this->assertEquals($expectedName, $networkName);
        }
    }

    /**
     * Test IP address range assignment for each user
     *
     * @covers \VmManagement\VMManager::getUserIPRange
     * @covers \VmManagement\VMManager::getVMIPAddress
     */
    public function testIPAddressRangeAssignment(): void
    {
        $expectedRanges = [
            'user1' => '192.168.100.',
            'user2' => '192.168.101.',
            'user3' => '192.168.102.',
        ];

        foreach ($expectedRanges as $user => $expectedPrefix) {
            $ipRange = $this->vmManager->getUserIPRange($user);
            $this->assertIsArray($ipRange);
            $this->assertArrayHasKey('network', $ipRange);
            $this->assertArrayHasKey('netmask', $ipRange);
            $this->assertArrayHasKey('range_start', $ipRange);
            $this->assertArrayHasKey('range_end', $ipRange);

            // Verify network prefix
            $this->assertStringStartsWith($expectedPrefix, $ipRange['network']);
            $this->assertStringStartsWith($expectedPrefix, $ipRange['range_start']);
            $this->assertStringStartsWith($expectedPrefix, $ipRange['range_end']);
        }
    }

    /**
     * Test VM network assignment and IP allocation
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\VMManager::getSSHInfo
     */
    public function testVMNetworkAssignmentAndIPAllocation(): void
    {
        $vms = [];

        // Create VMs for each user
        foreach (['user1', 'user2', 'user3'] as $user) {
            $vmName = "test-network-{$user}-" . uniqid();
            $this->createdVMs[] = $vmName;

            $vm = new SimpleVM(
                name: $vmName,
                user: $user,
                cpu: 1,
                memory: 1024,
                disk: 10
            );

            $this->vmManager->createAndStartVM($vm);
            $vms[$user] = $vm;
        }

        // Verify each VM gets IP in correct range
        $expectedPrefixes = [
            'user1' => '192.168.100.',
            'user2' => '192.168.101.',
            'user3' => '192.168.102.',
        ];

        foreach ($vms as $user => $vm) {
            $sshInfo = $this->vmManager->getSSHInfo($vm);
            $this->assertIsArray($sshInfo);
            $this->assertArrayHasKey('ip', $sshInfo);

            $ip = $sshInfo['ip'];
            $expectedPrefix = $expectedPrefixes[$user];

            $this->assertStringStartsWith(
                $expectedPrefix,
                $ip,
                "VM for {$user} should have IP starting with {$expectedPrefix}, got {$ip}"
            );
        }
    }

    /**
     * Test DHCP lease assignment and tracking
     *
     * @covers \VmManagement\VMManager::getVMIPAddress
     * @covers \VmManagement\VMManager::getDHCPLeases
     */
    public function testDHCPLeaseAssignmentAndTracking(): void
    {
        $vmName = 'test-dhcp-' . uniqid();
        $this->createdVMs[] = $vmName;

        $vm = new SimpleVM(
            name: $vmName,
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $this->vmManager->createAndStartVM($vm);

        // Wait for VM to fully start and get IP
        sleep(5);

        // Get IP address
        $ipAddress = $this->vmManager->getVMIPAddress($vmName, 'user1');
        $this->assertIsString($ipAddress);
        $this->assertNotEmpty($ipAddress);

        // Verify IP is in correct range
        $this->assertStringStartsWith('192.168.100.', $ipAddress);

        // Check DHCP leases
        $leases = $this->vmManager->getDHCPLeases('user1');
        $this->assertIsArray($leases);

        // Find our VM in the leases
        $foundLease = false;
        foreach ($leases as $lease) {
            if ($lease['hostname'] === $vmName || $lease['ip'] === $ipAddress) {
                $foundLease = true;

                break;
            }
        }

        $this->assertTrue($foundLease, "VM not found in DHCP leases");
    }

    /**
     * Test network isolation between different VLANs
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\VMManager::getSSHInfo
     */
    public function testNetworkIsolationBetweenVLANs(): void
    {
        $vms = [];

        // Create VMs in different VLANs
        foreach (['user1', 'user2'] as $user) {
            $vmName = "test-isolation-{$user}-" . uniqid();
            $this->createdVMs[] = $vmName;

            $vm = new SimpleVM(
                name: $vmName,
                user: $user,
                cpu: 1,
                memory: 1024,
                disk: 10
            );

            $this->vmManager->createAndStartVM($vm);
            $vms[$user] = $vm;
        }

        // Wait for VMs to fully start
        sleep(10);

        // Get SSH info for both VMs
        $sshInfo1 = $this->vmManager->getSSHInfo($vms['user1']);
        $sshInfo2 = $this->vmManager->getSSHInfo($vms['user2']);

        $this->assertIsArray($sshInfo1);
        $this->assertIsArray($sshInfo2);
        $this->assertArrayHasKey('ip', $sshInfo1);
        $this->assertArrayHasKey('ip', $sshInfo2);

        $ip1 = $sshInfo1['ip'];
        $ip2 = $sshInfo2['ip'];

        // Verify IPs are in different subnets
        $this->assertStringStartsWith('192.168.100.', $ip1);
        $this->assertStringStartsWith('192.168.101.', $ip2);

        // Test network isolation (if ping is available)
        if ($this->canTestPing()) {
            // Try to ping from VM1 to VM2 - should fail due to VLAN isolation
            $isolationWorking = $this->testNetworkIsolation($sshInfo1, $ip2);
            $this->assertTrue($isolationWorking, "Network isolation between VLANs is not working");
        }
    }

    /**
     * Test network configuration XML generation
     *
     * @covers \VmManagement\VMManager::generateNetworkXml
     */
    public function testNetworkConfigurationXMLGeneration(): void
    {
        $vlanIds = [100, 101, 102];

        foreach ($vlanIds as $vlanId) {
            $xml = $this->vmManager->generateNetworkXml($vlanId);
            $this->assertIsString($xml);
            $this->assertNotEmpty($xml);

            // Parse XML to verify structure
            $dom = new \DOMDocument();
            $this->assertTrue($dom->loadXML($xml), "Generated XML is not valid");

            // Verify network name
            $networkName = $dom->getElementsByTagName('network')->item(0)->getElementsByTagName('name')->item(0)->nodeValue;
            $expectedName = "vm-network-{$vlanId}";
            $this->assertEquals($expectedName, $networkName);

            // Verify IP configuration
            $ipElement = $dom->getElementsByTagName('ip')->item(0);
            $this->assertNotNull($ipElement);

            $address = $ipElement->getAttribute('address');
            $expectedAddress = "192.168.{$vlanId}.1";
            $this->assertEquals($expectedAddress, $address);

            $netmask = $ipElement->getAttribute('netmask');
            $this->assertEquals('255.255.255.0', $netmask);
        }
    }

    /**
     * Test network persistence across restarts
     *
     * @covers \VmManagement\VMManager::createUserNetwork
     * @covers \VmManagement\VMManager::networkExists
     */
    public function testNetworkPersistenceAcrossRestarts(): void
    {
        $user = 'user1';

        // Create network
        $result = $this->vmManager->createUserNetwork($user);
        $this->assertTrue($result);

        // Verify network exists
        $this->assertTrue($this->vmManager->networkExists($user));

        // Simulate restart by creating new VMManager instance
        $newVMManager = new VMManager();
        $newVMManager->connect();

        // Verify network still exists
        $this->assertTrue($newVMManager->networkExists($user));

        // Verify network can be ensured (should not recreate)
        $result = $newVMManager->ensureUserNetwork($user);
        $this->assertTrue($result);
    }

    /**
     * Test multiple VMs in same VLAN can communicate
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\VMManager::getSSHInfo
     */
    public function testMultipleVMsInSameVLANCanCommunicate(): void
    {
        $vms = [];

        // Create two VMs in same VLAN (user1)
        for ($i = 1; $i <= 2; $i++) {
            $vmName = "test-same-vlan-{$i}-" . uniqid();
            $this->createdVMs[] = $vmName;

            $vm = new SimpleVM(
                name: $vmName,
                user: 'user1',
                cpu: 1,
                memory: 1024,
                disk: 10
            );

            $this->vmManager->createAndStartVM($vm);
            $vms[] = $vm;
        }

        // Wait for VMs to fully start
        sleep(10);

        // Get SSH info for both VMs
        $sshInfo1 = $this->vmManager->getSSHInfo($vms[0]);
        $sshInfo2 = $this->vmManager->getSSHInfo($vms[1]);

        $this->assertIsArray($sshInfo1);
        $this->assertIsArray($sshInfo2);
        $this->assertArrayHasKey('ip', $sshInfo1);
        $this->assertArrayHasKey('ip', $sshInfo2);

        $ip1 = $sshInfo1['ip'];
        $ip2 = $sshInfo2['ip'];

        // Verify both IPs are in same subnet
        $this->assertStringStartsWith('192.168.100.', $ip1);
        $this->assertStringStartsWith('192.168.100.', $ip2);

        // Test communication within same VLAN (if ping is available)
        if ($this->canTestPing()) {
            $canCommunicate = $this->testIntraVLANCommunication($sshInfo1, $ip2);
            $this->assertTrue($canCommunicate, "VMs in same VLAN cannot communicate");
        }
    }

    /**
     * Helper method to get VLAN ID for a user
     */
    private function getVlanId(string $user): int
    {
        $vlanMapping = [
            'user1' => 100,
            'user2' => 101,
            'user3' => 102,
        ];

        return $vlanMapping[$user] ?? 100;
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
     * Test network isolation by trying to ping between VLANs
     */
    private function testNetworkIsolation(array $sshInfo, string $targetIP): bool
    {
        // Try to ping from VM to target IP (should fail due to VLAN isolation)
        $command = sprintf(
            'ssh -o ConnectTimeout=5 -o StrictHostKeyChecking=no %s@%s "ping -c 1 -W 1 %s"',
            escapeshellarg($sshInfo['username']),
            escapeshellarg($sshInfo['ip']),
            escapeshellarg($targetIP)
        );

        exec($command, $output, $returnCode);

        // Return true if ping failed (isolation working)
        return $returnCode !== 0;
    }

    /**
     * Test intra-VLAN communication
     */
    private function testIntraVLANCommunication(array $sshInfo, string $targetIP): bool
    {
        // Try to ping within same VLAN (should succeed)
        $command = sprintf(
            'ssh -o ConnectTimeout=5 -o StrictHostKeyChecking=no %s@%s "ping -c 1 -W 1 %s"',
            escapeshellarg($sshInfo['username']),
            escapeshellarg($sshInfo['ip']),
            escapeshellarg($targetIP)
        );

        exec($command, $output, $returnCode);

        // Return true if ping succeeded
        return $returnCode === 0;
    }

    /**
     * Check if ping testing is possible
     */
    private function canTestPing(): bool
    {
        return ! empty(exec('which ping')) && ! empty(exec('which ssh'));
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
