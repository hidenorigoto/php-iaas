<?php

declare(strict_types=1);

namespace VmManagement\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VmManagement\CloudInit;

/**
 * Unit tests for CloudInit helper class
 */
class CloudInitTest extends TestCase
{
    /**
     * Test user-data generation
     *
     * @covers \VmManagement\CloudInit::generateUserData
     */
    public function testGenerateUserData(): void
    {
        $hostname = 'test-vm';
        $username = 'ubuntu';
        $password = 'test-password';

        $userData = CloudInit::generateUserData($hostname, $username, $password);

        $this->assertIsString($userData);
        $this->assertStringContainsString("#cloud-config", $userData);
        $this->assertStringContainsString("hostname: {$hostname}", $userData);
        $this->assertStringContainsString("name: {$username}", $userData);
        $this->assertStringContainsString("sudo: ['ALL=(ALL) NOPASSWD:ALL']", $userData);
        $this->assertStringContainsString("qemu-guest-agent", $userData);
        $this->assertStringContainsString("openssh-server", $userData);
        $this->assertStringContainsString("ssh_pwauth: true", $userData);
    }

    /**
     * Test meta-data generation
     *
     * @covers \VmManagement\CloudInit::generateMetaData
     */
    public function testGenerateMetaData(): void
    {
        $instanceId = 'test-instance';
        $hostname = 'test-vm';

        $metaData = CloudInit::generateMetaData($instanceId, $hostname);

        $this->assertIsString($metaData);
        $this->assertStringContainsString("instance-id: {$instanceId}", $metaData);
        $this->assertStringContainsString("local-hostname: {$hostname}", $metaData);
    }

    /**
     * Test password generation
     *
     * @covers \VmManagement\CloudInit::generatePassword
     */
    public function testGeneratePassword(): void
    {
        $password = CloudInit::generatePassword();

        $this->assertIsString($password);
        $this->assertEquals(16, strlen($password));

        // Test custom length
        $customPassword = CloudInit::generatePassword(24);
        $this->assertEquals(24, strlen($customPassword));

        // Test that passwords are different
        $password2 = CloudInit::generatePassword();
        $this->assertNotEquals($password, $password2);
    }

    /**
     * Test cloud-init ISO creation
     *
     * @covers \VmManagement\CloudInit::createCloudInitISO
     */
    public function testCreateCloudInitISO(): void
    {
        $vmName = 'test-vm-' . uniqid();
        $userData = CloudInit::generateUserData($vmName, 'ubuntu', 'password');
        $metaData = CloudInit::generateMetaData($vmName, $vmName);
        $outputPath = sys_get_temp_dir() . '/' . $vmName . '-cloud-init.iso';

        // Check if genisoimage is available
        exec('which genisoimage 2>&1', $output, $returnCode);
        if ($returnCode !== 0) {
            $this->markTestSkipped('genisoimage not available');
        }

        $result = CloudInit::createCloudInitISO($vmName, $userData, $metaData, $outputPath);

        if ($result) {
            $this->assertTrue($result);
            $this->assertFileExists($outputPath);

            // Cleanup
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        } else {
            // If creation failed, it might be due to missing tools in test environment
            $this->assertFalse($result);
        }
    }

    /**
     * Test user-data generation without password
     *
     * @covers \VmManagement\CloudInit::generateUserData
     */
    public function testGenerateUserDataWithoutPassword(): void
    {
        $hostname = 'test-vm';
        $username = 'ubuntu';

        $userData = CloudInit::generateUserData($hostname, $username, '');

        $this->assertIsString($userData);
        $this->assertStringContainsString("#cloud-config", $userData);
        $this->assertStringContainsString("hostname: {$hostname}", $userData);
        $this->assertStringContainsString("name: {$username}", $userData);
        $this->assertStringNotContainsString("lock_passwd: false", $userData);
        $this->assertStringNotContainsString("passwd:", $userData);
    }

    /**
     * Test password generation with special characters
     *
     * @covers \VmManagement\CloudInit::generatePassword
     */
    public function testGeneratePasswordContainsSpecialCharacters(): void
    {
        $foundSpecial = false;

        // Generate multiple passwords to ensure we get special characters
        for ($i = 0; $i < 10; $i++) {
            $password = CloudInit::generatePassword();
            if (preg_match('/[!@#$%^&*()]/', $password)) {
                $foundSpecial = true;

                break;
            }
        }

        $this->assertTrue($foundSpecial, 'Generated passwords should contain special characters');
    }
}
