<?php

declare(strict_types=1);

namespace VmManagement;

/**
 * CloudInit helper class for generating cloud-init configuration
 */
class CloudInit
{
    /**
     * Generate cloud-init user-data configuration
     *
     * @param string $hostname Hostname for the VM
     * @param string $username Default username
     * @param string $password Password for the default user
     * @return string User-data YAML content
     */
    public static function generateUserData(string $hostname, string $username = 'ubuntu', string $password = ''): string
    {
        $hashedPassword = '';
        if (! empty($password)) {
            // Generate password hash for cloud-init
            // In production, use a proper password hashing method
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        }

        $userData = <<<YAML
            #cloud-config
            hostname: {$hostname}
            manage_etc_hosts: true

            users:
              - name: {$username}
                sudo: ['ALL=(ALL) NOPASSWD:ALL']
                groups: sudo
                shell: /bin/bash
            YAML;

        if (! empty($hashedPassword)) {
            $userData .= <<<YAML

                    lock_passwd: false
                    passwd: {$hashedPassword}
                YAML;
        }

        $userData .= <<<YAML


            # Disable password authentication for root
            disable_root: true

            # Update apt database on first boot
            package_update: true
            package_upgrade: false

            # Install minimal packages
            packages:
              - qemu-guest-agent
              - openssh-server

            # Configure SSH
            ssh_pwauth: true
            ssh_authorized_keys: []

            # Run commands on first boot
            runcmd:
              - systemctl start qemu-guest-agent
              - systemctl enable qemu-guest-agent

            # Power state change settings
            power_state:
              mode: reboot
              condition: false

            # Final message
            final_message: "The system is finally up, after \$UPTIME seconds"
            YAML;

        return $userData;
    }

    /**
     * Generate cloud-init meta-data configuration
     *
     * @param string $instanceId Instance ID for the VM
     * @param string $hostname Hostname for the VM
     * @return string Meta-data content
     */
    public static function generateMetaData(string $instanceId, string $hostname): string
    {
        return <<<YAML
            instance-id: {$instanceId}
            local-hostname: {$hostname}
            YAML;
    }

    /**
     * Create cloud-init ISO image
     *
     * @param string $vmName VM name
     * @param string $userData User-data content
     * @param string $metaData Meta-data content
     * @param string $outputPath Path to save the ISO
     * @return bool True on success, false on failure
     */
    public static function createCloudInitISO(
        string $vmName,
        string $userData,
        string $metaData,
        string $outputPath
    ): bool {
        // Create temporary directory for cloud-init files
        $tempDir = sys_get_temp_dir() . '/cloud-init-' . $vmName;
        if (! mkdir($tempDir, 0o755, true) && ! is_dir($tempDir)) {
            return false;
        }

        // Write user-data and meta-data files
        $userDataFile = $tempDir . '/user-data';
        $metaDataFile = $tempDir . '/meta-data';

        if (file_put_contents($userDataFile, $userData) === false) {
            self::cleanup($tempDir);

            return false;
        }

        if (file_put_contents($metaDataFile, $metaData) === false) {
            self::cleanup($tempDir);

            return false;
        }

        // Create ISO using genisoimage or mkisofs
        $command = sprintf(
            'genisoimage -output %s -volid cidata -joliet -rock %s %s 2>&1',
            escapeshellarg($outputPath),
            escapeshellarg($userDataFile),
            escapeshellarg($metaDataFile)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        // Cleanup temporary files
        self::cleanup($tempDir);

        return $returnCode === 0;
    }

    /**
     * Generate a random password
     *
     * @param int $length Password length
     * @return string Generated password
     */
    public static function generatePassword(int $length = 16): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        $charactersLength = strlen($characters);

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $password;
    }

    /**
     * Clean up temporary directory
     *
     * @param string $dir Directory to clean up
     */
    private static function cleanup(string $dir): void
    {
        if (is_dir($dir)) {
            $scanResult = scandir($dir);
            if ($scanResult === false) {
                return;
            }
            $files = array_diff($scanResult, ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? self::cleanup($path) : unlink($path);
            }
            rmdir($dir);
        }
    }
}
