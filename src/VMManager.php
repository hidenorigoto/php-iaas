<?php

declare(strict_types=1);

namespace VmManagement;

use InvalidArgumentException;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * VMManager class for managing virtual machines using libvirt-php
 */
class VMManager
{
    private Logger $logger;

    /** @var resource|null */
    private $libvirtConnection = null;

    /** @var array<string, int> */
    private const USER_VLAN_MAP = [
        'user1' => 100,
        'user2' => 101,
        'user3' => 102,
    ];

    private const DEFAULT_CPU = 2;
    private const DEFAULT_MEMORY = 2048;
    private const DEFAULT_DISK = 20;
    private const LIBVIRT_URI = 'qemu:///system';
    private const DEFAULT_STORAGE_POOL = 'default';
    private const DEFAULT_DISK_PATH = '/var/lib/libvirt/images/';

    /**
     * Constructor for VMManager
     *
     * @param Logger|null $logger Optional logger instance
     */
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger ?? $this->createDefaultLogger();
        $this->logger->info('VMManager initialized');
    }

    /**
     * Create default logger instance
     *
     * @return Logger
     */
    private function createDefaultLogger(): Logger
    {
        $logger = new Logger('vm-management');

        // Add rotating file handler for persistent logging
        $fileHandler = new RotatingFileHandler(
            __DIR__ . '/../logs/vm-management.log',
            0,
            Logger::INFO
        );
        $logger->pushHandler($fileHandler);

        // Add stream handler for development
        $streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
        $logger->pushHandler($streamHandler);

        return $logger;
    }

    /**
     * Validate VM creation parameters
     *
     * @param array<string, mixed> $params VM parameters
     * @throws InvalidArgumentException
     */
    public function validateVMParams(array $params): void
    {
        // Validate VM name
        if (empty($params['name']) || ! is_string($params['name'])) {
            throw new InvalidArgumentException('VM name is required and must be a string');
        }

        if (! preg_match('/^[a-zA-Z0-9\-_]+$/', $params['name'])) {
            throw new InvalidArgumentException('VM name can only contain alphanumeric characters, hyphens, and underscores');
        }

        if (strlen($params['name']) > 50) {
            throw new InvalidArgumentException('VM name must be 50 characters or less');
        }

        // Validate user
        if (empty($params['user']) || ! is_string($params['user'])) {
            throw new InvalidArgumentException('User is required and must be a string');
        }

        if (! array_key_exists($params['user'], self::USER_VLAN_MAP)) {
            throw new InvalidArgumentException('User must be one of: user1, user2, user3');
        }

        // Validate CPU (optional)
        if (isset($params['cpu'])) {
            if (! is_int($params['cpu']) || $params['cpu'] < 1 || $params['cpu'] > 8) {
                throw new InvalidArgumentException('CPU must be an integer between 1 and 8');
            }
        }

        // Validate memory (optional)
        if (isset($params['memory'])) {
            if (! is_int($params['memory']) || $params['memory'] < 512 || $params['memory'] > 8192) {
                throw new InvalidArgumentException('Memory must be an integer between 512 and 8192 MB');
            }
        }

        // Validate disk (optional)
        if (isset($params['disk'])) {
            if (! is_int($params['disk']) || $params['disk'] < 10 || $params['disk'] > 100) {
                throw new InvalidArgumentException('Disk must be an integer between 10 and 100 GB');
            }
        }

        $this->logger->info('VM parameters validated successfully', $params);
    }

    /**
     * Create a SimpleVM instance from parameters
     *
     * @param array<string, mixed> $params VM parameters
     * @return SimpleVM
     * @throws InvalidArgumentException
     */
    public function createVMInstance(array $params): SimpleVM
    {
        $this->validateVMParams($params);

        $vm = new SimpleVM(
            $params['name'],
            $params['user'],
            $params['cpu'] ?? self::DEFAULT_CPU,
            $params['memory'] ?? self::DEFAULT_MEMORY,
            $params['disk'] ?? self::DEFAULT_DISK
        );

        $this->logger->info('SimpleVM instance created', [
            'name' => $vm->name,
            'user' => $vm->user,
            'vlan_id' => $vm->vlanId,
            'cpu' => $vm->cpu,
            'memory' => $vm->memory,
            'disk' => $vm->disk,
        ]);

        return $vm;
    }

    /**
     * Get logger instance
     *
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * Log an error message
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     */
    public function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Info message
     * @param array<string, mixed> $context Additional context
     */
    public function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * Log a debug message
     *
     * @param string $message Debug message
     * @param array<string, mixed> $context Additional context
     */
    public function logDebug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * Connect to libvirt daemon
     *
     * @return bool True if connection successful, false otherwise
     */
    public function connect(): bool
    {
        if ($this->isConnected()) {
            $this->logger->debug('Already connected to libvirt');

            return true;
        }

        $this->logger->info('Attempting to connect to libvirt', ['uri' => self::LIBVIRT_URI]);

        // Check if libvirt_connect function exists (for testing compatibility)
        if (! function_exists('libvirt_connect')) {
            $this->logger->error('libvirt_connect function not available');

            return false;
        }

        $connection = libvirt_connect(self::LIBVIRT_URI);

        if ($connection === false) {
            $error = $this->getLastLibvirtError();
            $this->logger->error('Failed to connect to libvirt', [
                'uri' => self::LIBVIRT_URI,
                'error' => $error,
            ]);

            return false;
        }

        $this->libvirtConnection = $connection;
        $this->logger->info('Successfully connected to libvirt');

        return true;
    }

    /**
     * Check if connected to libvirt daemon
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool
    {
        return $this->libvirtConnection !== null && is_resource($this->libvirtConnection);
    }

    /**
     * Disconnect from libvirt daemon
     *
     * @return bool True if disconnection successful, false otherwise
     */
    public function disconnect(): bool
    {
        if (! $this->isConnected()) {
            $this->logger->debug('Not connected to libvirt, nothing to disconnect');

            return true;
        }

        $this->logger->info('Disconnecting from libvirt');

        // Check if libvirt_connect_close function exists (for testing compatibility)
        if (function_exists('libvirt_connect_close')) {
            $result = libvirt_connect_close($this->libvirtConnection);

            if ($result === false) {
                $error = $this->getLastLibvirtError();
                $this->logger->error('Failed to disconnect from libvirt', ['error' => $error]);
                $this->libvirtConnection = null;

                return false;
            }
        }

        $this->libvirtConnection = null;
        $this->logger->info('Successfully disconnected from libvirt');

        return true;
    }

    /**
     * Get the last libvirt error
     *
     * @return string Error message or empty string if no error
     */
    private function getLastLibvirtError(): string
    {
        if (function_exists('libvirt_get_last_error')) {
            $error = libvirt_get_last_error();

            return is_string($error) ? $error : 'Unknown libvirt error';
        }

        return 'libvirt_get_last_error function not available';
    }

    /**
     * Get the current libvirt connection resource
     *
     * @return resource|null The libvirt connection resource or null if not connected
     */
    public function getConnection()
    {
        return $this->libvirtConnection;
    }

    /**
     * Get storage pool by name
     *
     * @param string $poolName Storage pool name
     * @return resource|false Storage pool resource or false on failure
     */
    public function getStoragePool(string $poolName = self::DEFAULT_STORAGE_POOL)
    {
        if (! $this->isConnected()) {
            $this->logger->error('Not connected to libvirt');

            return false;
        }

        $this->logger->info('Looking up storage pool', ['pool_name' => $poolName]);

        // Check if libvirt_storagepool_lookup_by_name function exists
        if (! function_exists('libvirt_storagepool_lookup_by_name')) {
            $this->logger->error('libvirt_storagepool_lookup_by_name function not available');

            return false;
        }

        $pool = libvirt_storagepool_lookup_by_name($this->libvirtConnection, $poolName);

        if ($pool === false) {
            $error = $this->getLastLibvirtError();
            $this->logger->error('Failed to lookup storage pool', [
                'pool_name' => $poolName,
                'error' => $error,
            ]);

            return false;
        }

        $this->logger->info('Successfully found storage pool', ['pool_name' => $poolName]);

        return $pool;
    }

    /**
     * Create disk volume in storage pool
     *
     * @param string $volumeName Volume name
     * @param int $sizeGB Volume size in GB
     * @param string $poolName Storage pool name
     * @return string|false Volume path on success, false on failure
     */
    public function createDiskVolume(string $volumeName, int $sizeGB, string $poolName = self::DEFAULT_STORAGE_POOL)
    {
        $this->logger->info('Creating disk volume', [
            'volume_name' => $volumeName,
            'size_gb' => $sizeGB,
            'pool_name' => $poolName,
        ]);

        // Get storage pool
        $pool = $this->getStoragePool($poolName);
        if ($pool === false) {
            return false;
        }

        // Generate volume XML configuration
        $volumeXml = $this->generateVolumeXml($volumeName, $sizeGB);

        // Check if libvirt_storagevolume_create_xml function exists
        if (! function_exists('libvirt_storagevolume_create_xml')) {
            $this->logger->error('libvirt_storagevolume_create_xml function not available');

            return false;
        }

        $volume = libvirt_storagevolume_create_xml($pool, $volumeXml);

        if ($volume === false) {
            $error = $this->getLastLibvirtError();
            $this->logger->error('Failed to create disk volume', [
                'volume_name' => $volumeName,
                'error' => $error,
            ]);

            return false;
        }

        // Get volume path
        $volumePath = $this->getVolumePath($volume);

        if ($volumePath === false) {
            $this->logger->error('Failed to get volume path', ['volume_name' => $volumeName]);

            return false;
        }

        $this->logger->info('Successfully created disk volume', [
            'volume_name' => $volumeName,
            'volume_path' => $volumePath,
            'size_gb' => $sizeGB,
        ]);

        return $volumePath;
    }

    /**
     * Generate XML configuration for storage volume
     *
     * @param string $volumeName Volume name
     * @param int $sizeGB Volume size in GB
     * @return string XML configuration
     */
    private function generateVolumeXml(string $volumeName, int $sizeGB): string
    {
        $sizeBytes = $sizeGB * 1024 * 1024 * 1024; // Convert GB to bytes

        $xml = <<<XML
            <volume type='file'>
              <name>{$volumeName}.qcow2</name>
              <key>{$volumeName}.qcow2</key>
              <source>
              </source>
              <capacity unit='bytes'>{$sizeBytes}</capacity>
              <allocation unit='bytes'>0</allocation>
              <target>
                <path>{$this->getVolumeTargetPath($volumeName)}</path>
                <format type='qcow2'/>
                <permissions>
                  <mode>0644</mode>
                  <owner>0</owner>
                  <group>0</group>
                </permissions>
              </target>
            </volume>
            XML;

        $this->logger->debug('Generated volume XML', [
            'volume_name' => $volumeName,
            'xml' => $xml,
        ]);

        return $xml;
    }

    /**
     * Get target path for volume
     *
     * @param string $volumeName Volume name
     * @return string Target path
     */
    private function getVolumeTargetPath(string $volumeName): string
    {
        return self::DEFAULT_DISK_PATH . $volumeName . '.qcow2';
    }

    /**
     * Get volume path from volume resource
     *
     * @param resource $volume Volume resource
     * @return string|false Volume path or false on failure
     */
    private function getVolumePath($volume)
    {
        // Check if libvirt_storagevolume_get_path function exists
        if (! function_exists('libvirt_storagevolume_get_path')) {
            $this->logger->error('libvirt_storagevolume_get_path function not available');

            return false;
        }

        $path = libvirt_storagevolume_get_path($volume);

        if ($path === false) {
            $error = $this->getLastLibvirtError();
            $this->logger->error('Failed to get volume path', ['error' => $error]);

            return false;
        }

        return $path;
    }

    /**
     * Create qcow2 disk image using qemu-img
     *
     * @param string $imagePath Path for the new image
     * @param int $sizeGB Image size in GB
     * @param string|null $baseImage Base image path for backing file
     * @return bool True on success, false on failure
     */
    public function createQcow2Image(string $imagePath, int $sizeGB, ?string $baseImage = null): bool
    {
        $this->logger->info('Creating qcow2 disk image', [
            'image_path' => $imagePath,
            'size_gb' => $sizeGB,
            'base_image' => $baseImage,
        ]);

        // Build qemu-img command
        $command = 'qemu-img create -f qcow2';

        if ($baseImage !== null && $this->fileExists($baseImage)) {
            $command .= " -b " . escapeshellarg($baseImage);
            $this->logger->debug('Using base image', ['base_image' => $baseImage]);
        }

        $command .= ' ' . escapeshellarg($imagePath) . ' ' . $sizeGB . 'G';

        $this->logger->debug('Executing qemu-img command', ['command' => $command]);

        // Execute command
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $this->logger->error('Failed to create qcow2 image', [
                'command' => $command,
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
            ]);

            return false;
        }

        $this->logger->info('Successfully created qcow2 image', [
            'image_path' => $imagePath,
            'size_gb' => $sizeGB,
        ]);

        return true;
    }

    /**
     * Copy base image to create new VM disk
     *
     * @param string $baseImagePath Source base image path
     * @param string $targetImagePath Target image path
     * @return bool True on success, false on failure
     */
    public function copyBaseImage(string $baseImagePath, string $targetImagePath): bool
    {
        $this->logger->info('Copying base image', [
            'base_image' => $baseImagePath,
            'target_image' => $targetImagePath,
        ]);

        // Check if base image exists
        if (! $this->fileExists($baseImagePath)) {
            $this->logger->error('Base image does not exist', ['base_image' => $baseImagePath]);

            return false;
        }

        // Use cp command to copy the image
        $command = 'cp ' . escapeshellarg($baseImagePath) . ' ' . escapeshellarg($targetImagePath);

        $this->logger->debug('Executing copy command', ['command' => $command]);

        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $this->logger->error('Failed to copy base image', [
                'command' => $command,
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
            ]);

            return false;
        }

        $this->logger->info('Successfully copied base image', [
            'base_image' => $baseImagePath,
            'target_image' => $targetImagePath,
        ]);

        return true;
    }

    /**
     * Check if file exists
     *
     * @param string $filePath File path to check
     * @return bool True if file exists, false otherwise
     */
    private function fileExists(string $filePath): bool
    {
        return file_exists($filePath);
    }

    /**
     * Create and start network for a specific user
     *
     * @param string $user User name (user1, user2, user3)
     * @return bool True on success, false on failure
     */
    public function createUserNetwork(string $user): bool
    {
        if (! array_key_exists($user, self::USER_VLAN_MAP)) {
            $this->logger->error('Invalid user for network creation', ['user' => $user]);

            return false;
        }

        if (! $this->isConnected()) {
            $this->logger->error('Not connected to libvirt');

            return false;
        }

        $vlanId = self::USER_VLAN_MAP[$user];
        $networkName = "vm-network-{$vlanId}";

        $this->logger->info('Creating user network', [
            'user' => $user,
            'vlan_id' => $vlanId,
            'network_name' => $networkName,
        ]);

        // Generate network XML
        $networkXml = $this->generateNetworkXml($vlanId);

        // Check if libvirt_network_define_xml function exists
        if (! function_exists('libvirt_network_define_xml')) {
            $this->logger->error('libvirt_network_define_xml function not available');

            return false;
        }

        // Define the network
        $network = libvirt_network_define_xml($this->libvirtConnection, $networkXml);

        if ($network === false) {
            $error = $this->getLastLibvirtError();
            $this->logger->error('Failed to define network', [
                'network_name' => $networkName,
                'error' => $error,
            ]);

            return false;
        }

        // Start the network
        if (! $this->startNetwork($network, $networkName)) {
            return false;
        }

        $this->logger->info('Successfully created and started user network', [
            'user' => $user,
            'network_name' => $networkName,
            'vlan_id' => $vlanId,
        ]);

        return true;
    }

    /**
     * Start a libvirt network
     *
     * @param resource $network Network resource
     * @param string $networkName Network name for logging
     * @return bool True on success, false on failure
     */
    private function startNetwork($network, string $networkName): bool
    {
        // Check if libvirt_network_create function exists
        if (! function_exists('libvirt_network_create')) {
            $this->logger->error('libvirt_network_create function not available');

            return false;
        }

        $result = libvirt_network_create($network);

        if ($result === false) {
            $error = $this->getLastLibvirtError();
            $this->logger->error('Failed to start network', [
                'network_name' => $networkName,
                'error' => $error,
            ]);

            return false;
        }

        $this->logger->info('Successfully started network', ['network_name' => $networkName]);

        return true;
    }

    /**
     * Generate XML configuration for user network
     *
     * @param int $vlanId VLAN ID (100, 101, 102)
     * @return string Network XML configuration
     */
    public function generateNetworkXml(int $vlanId): string
    {
        $networkName = "vm-network-{$vlanId}";
        $bridgeName = "virbr{$vlanId}";
        $ipAddress = "192.168.{$vlanId}.1";
        $netmask = "255.255.255.0";
        $dhcpStart = "192.168.{$vlanId}.10";
        $dhcpEnd = "192.168.{$vlanId}.100";

        $xml = <<<XML
            <network>
              <name>{$networkName}</name>
              <bridge name='{$bridgeName}'/>
              <forward mode='nat'/>
              <ip address='{$ipAddress}' netmask='{$netmask}'>
                <dhcp>
                  <range start='{$dhcpStart}' end='{$dhcpEnd}'/>
                </dhcp>
              </ip>
            </network>
            XML;

        $this->logger->debug('Generated network XML', [
            'vlan_id' => $vlanId,
            'network_name' => $networkName,
            'xml' => $xml,
        ]);

        return $xml;
    }

    /**
     * Get IP range for a specific user
     *
     * @param string $user User name (user1, user2, user3)
     * @return array<string, string|int>|false IP range information or false on error
     */
    public function getUserIPRange(string $user)
    {
        if (! array_key_exists($user, self::USER_VLAN_MAP)) {
            $this->logger->error('Invalid user for IP range lookup', ['user' => $user]);

            return false;
        }

        $vlanId = self::USER_VLAN_MAP[$user];

        $ipRange = [
            'network' => "192.168.{$vlanId}.0/24",
            'gateway' => "192.168.{$vlanId}.1",
            'dhcp_start' => "192.168.{$vlanId}.10",
            'dhcp_end' => "192.168.{$vlanId}.100",
            'vlan_id' => $vlanId,
        ];

        $this->logger->debug('Retrieved IP range for user', [
            'user' => $user,
            'ip_range' => $ipRange,
        ]);

        return $ipRange;
    }

    /**
     * Check if network exists for a user
     *
     * @param string $user User name (user1, user2, user3)
     * @return bool True if network exists, false otherwise
     */
    public function networkExists(string $user): bool
    {
        if (! array_key_exists($user, self::USER_VLAN_MAP)) {
            return false;
        }

        if (! $this->isConnected()) {
            return false;
        }

        $vlanId = self::USER_VLAN_MAP[$user];
        $networkName = "vm-network-{$vlanId}";

        // Check if libvirt_network_lookup_by_name function exists
        if (! function_exists('libvirt_network_lookup_by_name')) {
            $this->logger->error('libvirt_network_lookup_by_name function not available');

            return false;
        }

        $network = libvirt_network_lookup_by_name($this->libvirtConnection, $networkName);

        if ($network === false) {
            $this->logger->debug('Network does not exist', [
                'user' => $user,
                'network_name' => $networkName,
            ]);

            return false;
        }

        $this->logger->debug('Network exists', [
            'user' => $user,
            'network_name' => $networkName,
        ]);

        return true;
    }

    /**
     * Ensure network exists for user, create if it doesn't
     *
     * @param string $user User name (user1, user2, user3)
     * @return bool True on success, false on failure
     */
    public function ensureUserNetwork(string $user): bool
    {
        if ($this->networkExists($user)) {
            $this->logger->debug('Network already exists for user', ['user' => $user]);

            return true;
        }

        $this->logger->info('Network does not exist, creating for user', ['user' => $user]);

        return $this->createUserNetwork($user);
    }

    /**
     * Get network name for a user
     *
     * @param string $user User name (user1, user2, user3)
     * @return string|false Network name or false on error
     */
    public function getNetworkName(string $user)
    {
        if (! array_key_exists($user, self::USER_VLAN_MAP)) {
            return false;
        }

        $vlanId = self::USER_VLAN_MAP[$user];

        return "vm-network-{$vlanId}";
    }

    /**
     * Destructor - ensure libvirt connection is closed
     */
    public function __destruct()
    {
        if ($this->isConnected()) {
            $this->disconnect();
        }
    }
}
