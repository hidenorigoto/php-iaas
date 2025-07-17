<?php

declare(strict_types=1);

namespace VmManagement;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use VmManagement\Exceptions\ValidationException;

// Define libvirt constants if not already defined
if (! defined('VIR_DOMAIN_INTERFACE_ADDRESSES_SRC_LEASE')) {
    define('VIR_DOMAIN_INTERFACE_ADDRESSES_SRC_LEASE', 1);
}

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
    private const UBUNTU_BASE_IMAGE = '/var/lib/libvirt/images/ubuntu-22.04-server-cloudimg-amd64.img';
    private const CLOUD_INIT_PATH = '/var/lib/libvirt/images/cloud-init/';

    /**
     * Constructor for VMManager
     *
     * @param Logger|null $logger Optional logger instance
     */
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger ?? $this->createDefaultLogger();
        $this->logger->info('VMManager initialized', [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'libvirt_uri' => self::LIBVIRT_URI,
            'user_vlan_mapping' => self::USER_VLAN_MAP,
        ]);
    }

    /**
     * Create default logger instance
     *
     * @return Logger
     */
    private function createDefaultLogger(): Logger
    {
        $logger = new Logger('vm-management');

        // Add processor for unique request ID and system context
        $logger->pushProcessor(function ($record) {
            $record->extra['request_id'] = uniqid();
            $record->extra['process_id'] = getmypid();
            $record->extra['memory_usage'] = memory_get_usage(true);
            $record->extra['peak_memory'] = memory_get_peak_usage(true);

            return $record;
        });

        // Ensure log directory exists
        $logDir = __DIR__ . '/../logs';
        if (! is_dir($logDir)) {
            mkdir($logDir, 0o755, true);
        }

        // Add rotating file handler for persistent logging
        $fileHandler = new RotatingFileHandler(
            $logDir . '/vm-management.log',
            30, // Keep 30 days of logs
            Logger::INFO
        );
        $logger->pushHandler($fileHandler);

        // Add error-specific log file
        $errorHandler = new RotatingFileHandler(
            $logDir . '/vm-management-errors.log',
            30,
            Logger::ERROR
        );
        $logger->pushHandler($errorHandler);

        // Add stream handler for development
        $streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
        $logger->pushHandler($streamHandler);

        return $logger;
    }

    /**
     * Validate VM creation parameters
     *
     * @param array<string, mixed> $params VM parameters
     * @throws ValidationException
     */
    public function validateVMParams(array $params): void
    {
        // Validate VM name
        if (empty($params['name']) || ! is_string($params['name'])) {
            throw ValidationException::emptyParameter('name');
        }

        if (! preg_match('/^[a-zA-Z0-9\-_]+$/', $params['name'])) {
            throw ValidationException::invalidCharacters('name', $params['name']);
        }

        if (strlen($params['name']) > 50) {
            throw ValidationException::parameterTooLong('name', strlen($params['name']), 50);
        }

        // Validate user
        if (empty($params['user']) || ! is_string($params['user'])) {
            throw ValidationException::emptyParameter('user');
        }

        if (! array_key_exists($params['user'], self::USER_VLAN_MAP)) {
            throw ValidationException::invalidUser($params['user']);
        }

        // Validate CPU (optional)
        if (isset($params['cpu'])) {
            if (! is_int($params['cpu']) || $params['cpu'] < 1 || $params['cpu'] > 16) {
                throw ValidationException::invalidCPU($params['cpu']);
            }
        }

        // Validate memory (optional)
        if (isset($params['memory'])) {
            if (! is_int($params['memory']) || $params['memory'] < 512 || $params['memory'] > 32768) {
                throw ValidationException::invalidMemory($params['memory']);
            }
        }

        // Validate disk (optional)
        if (isset($params['disk'])) {
            if (! is_int($params['disk']) || $params['disk'] < 10 || $params['disk'] > 1000) {
                throw ValidationException::invalidDisk($params['disk']);
            }
        }

        $this->logger->debug('VM parameters validated successfully', $params);
    }

    /**
     * Create a SimpleVM instance from parameters
     *
     * @param array<string, mixed> $params VM parameters
     * @return SimpleVM
     * @throws ValidationException
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
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
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
     * Get the last libvirt error message
     *
     * @return string|null
     */
    private function getLibvirtError(): ?string
    {
        if (function_exists('libvirt_get_last_error')) {
            $error = libvirt_get_last_error();
            $this->logger->debug('Retrieved libvirt error', ['error' => $error]);

            return $error;
        }
        $this->logger->debug('libvirt_get_last_error function not available');

        return null;
    }

    /**
     * Log operation with timing and context
     *
     * @param string $operation Operation name
     * @param array<string, mixed> $context Additional context
     * @param callable $callback Operation to execute
     * @return mixed
     */
    /*
    private function logOperation(string $operation, array $context, callable $callback): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->logger->info("Starting operation: {$operation}", $context);

        try {
            $result = $callback();

            $duration = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage(true) - $startMemory;

            $this->logger->info("Operation completed: {$operation}", [
                'duration_seconds' => round($duration, 4),
                'memory_used_bytes' => $memoryUsed,
                'success' => true,
            ] + $context);

            return $result;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage(true) - $startMemory;

            $this->logger->error("Operation failed: {$operation}", [
                'duration_seconds' => round($duration, 4),
                'memory_used_bytes' => $memoryUsed,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'success' => false,
            ] + $context);

            throw $e;
        }
    }
    */

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
                $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
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
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
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

        // Use qemu-img to create disk with Ubuntu base image
        $volumePath = $this->getVolumeTargetPath($volumeName);

        // Check if Ubuntu base image exists
        if (! file_exists(self::UBUNTU_BASE_IMAGE)) {
            $this->logger->warning('Ubuntu base image not found, creating empty disk', [
                'base_image' => self::UBUNTU_BASE_IMAGE,
            ]);

            // Fallback to creating empty disk
            if (! $this->createQcow2Image($volumePath, $sizeGB)) {
                return false;
            }
        } else {
            // Create disk with Ubuntu base image as backing file
            if (! $this->createQcow2ImageWithBacking($volumePath, $sizeGB, self::UBUNTU_BASE_IMAGE)) {
                return false;
            }
        }

        $this->logger->info('Successfully created disk volume', [
            'volume_name' => $volumeName,
            'volume_path' => $volumePath,
            'size_gb' => $sizeGB,
            'base_image' => file_exists(self::UBUNTU_BASE_IMAGE) ? self::UBUNTU_BASE_IMAGE : 'none',
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
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
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
     * Create qcow2 image with backing file
     *
     * @param string $imagePath Path for the new image
     * @param int $sizeGB Size in GB
     * @param string $backingFile Path to the backing file
     * @return bool True on success, false on failure
     */
    public function createQcow2ImageWithBacking(string $imagePath, int $sizeGB, string $backingFile): bool
    {
        $this->logger->info('Creating qcow2 image with backing file', [
            'image_path' => $imagePath,
            'size_gb' => $sizeGB,
            'backing_file' => $backingFile,
        ]);

        // Create qemu-img command with backing file
        $command = 'qemu-img create -f qcow2 -F qcow2';
        $command .= ' -b ' . escapeshellarg($backingFile);
        $command .= ' ' . escapeshellarg($imagePath) . ' ' . $sizeGB . 'G';

        $this->logger->debug('Executing qemu-img command', ['command' => $command]);

        // Execute command
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $this->logger->error('Failed to create qcow2 image with backing', [
                'command' => $command,
                'return_code' => $returnCode,
                'output' => implode("\n", $output),
            ]);

            return false;
        }

        $this->logger->info('Successfully created qcow2 image with backing', [
            'image_path' => $imagePath,
            'backing_file' => $backingFile,
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
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
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
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
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
     * Build VM configuration XML
     *
     * @param SimpleVM $vm VM instance with configuration
     * @param string $diskPath Path to the disk volume
     * @return string Domain XML configuration
     */
    public function buildVMConfig(SimpleVM $vm, string $diskPath, string $cloudInitISOPath = ''): string
    {
        $this->logger->info('Building VM configuration XML', [
            'vm_name' => $vm->name,
            'user' => $vm->user,
            'disk_path' => $diskPath,
            'cloud_init_iso' => $cloudInitISOPath,
        ]);

        // Generate unique UUID for the VM
        $uuid = $this->generateUUID();

        // Convert memory from MB to KiB
        $memoryKiB = $vm->memory * 1024;

        // Get network name for the user
        $networkName = $this->getNetworkName($vm->user);
        if ($networkName === false) {
            throw new \RuntimeException("Invalid user for network configuration: {$vm->user}");
        }

        // Generate MAC address
        $macAddress = $this->generateMacAddress();

        // Build the domain XML
        $xml = <<<XML
                        <domain type='qemu'>
                          <name>{$vm->name}</name>
                          <uuid>{$uuid}</uuid>
                          <memory unit='KiB'>{$memoryKiB}</memory>
                          <currentMemory unit='KiB'>{$memoryKiB}</currentMemory>
                          <vcpu placement='static'>{$vm->cpu}</vcpu>
                          <os>
                            <type arch='x86_64' machine='pc-i440fx-2.12'>hvm</type>
                            <boot dev='hd'/>
                          </os>
                          <features>
                            <acpi/>
                            <apic/>
                            <vmport state='off'/>
                          </features>
                          <cpu mode='host-model' check='partial'>
                            <model fallback='allow'/>
                          </cpu>
                          <clock offset='utc'>
                            <timer name='rtc' tickpolicy='catchup'/>
                            <timer name='pit' tickpolicy='delay'/>
                            <timer name='hpet' present='no'/>
                          </clock>
                          <on_poweroff>destroy</on_poweroff>
                          <on_reboot>restart</on_reboot>
                          <on_crash>destroy</on_crash>
                          <pm>
                            <suspend-to-mem enabled='no'/>
                            <suspend-to-disk enabled='no'/>
                          </pm>
                          <devices>
                            <emulator>/usr/bin/qemu-system-x86_64</emulator>
                            <disk type='file' device='disk'>
                              <driver name='qemu' type='qcow2'/>
                              <source file='{$diskPath}'/>
                              <target dev='vda' bus='virtio'/>
                              <address type='pci' domain='0x0000' bus='0x00' slot='0x04' function='0x0'/>
                            </disk>
            XML;

        // Add cloud-init CD-ROM if path is provided
        if (! empty($cloudInitISOPath)) {
            $xml .= <<<XML

                                <disk type='file' device='cdrom'>
                                  <driver name='qemu' type='raw'/>
                                  <source file='{$cloudInitISOPath}'/>
                                  <target dev='hdc' bus='ide'/>
                                  <readonly/>
                                  <address type='drive' controller='0' bus='1' target='0' unit='0'/>
                                </disk>
                XML;
        }

        $xml .= <<<XML
                <controller type='usb' index='0' model='ich9-ehci1'>
                  <address type='pci' domain='0x0000' bus='0x00' slot='0x05' function='0x7'/>
                </controller>
                <controller type='pci' index='0' model='pci-root'/>
                <controller type='ide' index='0'>
                  <address type='pci' domain='0x0000' bus='0x00' slot='0x01' function='0x1'/>
                </controller>
                <interface type='network'>
                  <mac address='{$macAddress}'/>
                  <source network='{$networkName}'/>
                  <model type='virtio'/>
                  <address type='pci' domain='0x0000' bus='0x00' slot='0x03' function='0x0'/>
                </interface>
                <serial type='pty'>
                  <target type='isa-serial' port='0'>
                    <model name='isa-serial'/>
                  </target>
                </serial>
                <console type='pty'>
                  <target type='serial' port='0'/>
                </console>
                <input type='mouse' bus='ps2'/>
                <input type='keyboard' bus='ps2'/>
                <graphics type='vnc' port='-1' autoport='yes' listen='127.0.0.1'>
                  <listen type='address' address='127.0.0.1'/>
                </graphics>
                <video>
                  <model type='cirrus' vram='16384' heads='1' primary='yes'/>
                  <address type='pci' domain='0x0000' bus='0x00' slot='0x02' function='0x0'/>
                </video>
                <memballoon model='virtio'>
                  <address type='pci' domain='0x0000' bus='0x00' slot='0x06' function='0x0'/>
                </memballoon>
              </devices>
            </domain>
            XML;

        $this->logger->debug('Generated VM configuration XML', [
            'vm_name' => $vm->name,
            'uuid' => $uuid,
            'memory_kib' => $memoryKiB,
            'network' => $networkName,
            'mac_address' => $macAddress,
        ]);

        return trim($xml);
    }

    /**
     * Create cloud-init ISO for VM
     *
     * @param SimpleVM $vm VM instance
     * @return string|false Path to cloud-init ISO on success, false on failure
     */
    private function createCloudInitISO(SimpleVM $vm)
    {
        $this->logger->info('Creating cloud-init ISO', [
            'vm_name' => $vm->name,
        ]);

        // Ensure cloud-init directory exists
        if (! is_dir(self::CLOUD_INIT_PATH)) {
            if (! mkdir(self::CLOUD_INIT_PATH, 0o755, true)) {
                $this->logger->error('Failed to create cloud-init directory', [
                    'path' => self::CLOUD_INIT_PATH,
                ]);

                return false;
            }
        }

        // Generate password for VM
        $password = CloudInit::generatePassword();
        $vm->setSSHInfo('', $password);

        // Generate cloud-init configurations
        $userData = CloudInit::generateUserData($vm->name, $vm->username, $password);
        $metaData = CloudInit::generateMetaData($vm->name, $vm->name);

        // Create cloud-init ISO
        $isoPath = self::CLOUD_INIT_PATH . $vm->name . '-cloud-init.iso';
        if (! CloudInit::createCloudInitISO($vm->name, $userData, $metaData, $isoPath)) {
            $this->logger->error('Failed to create cloud-init ISO file', [
                'iso_path' => $isoPath,
            ]);

            return false;
        }

        $this->logger->info('Successfully created cloud-init ISO', [
            'iso_path' => $isoPath,
        ]);

        return $isoPath;
    }

    /**
     * Generate a unique UUID
     *
     * @return string UUID in format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     */
    private function generateUUID(): string
    {
        $data = random_bytes(16);

        // Set version (4) and variant bits
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant 10

        $timeLow = unpack('N', substr($data, 0, 4));
        $timeMid = unpack('n', substr($data, 4, 2));
        $timeHiVersion = unpack('n', substr($data, 6, 2));
        $clockSeq = unpack('n', substr($data, 8, 2));
        $nodeHigh = unpack('N', substr($data, 10, 4));
        $nodeLow = unpack('n', substr($data, 14, 2));

        if ($timeLow === false || $timeMid === false || $timeHiVersion === false ||
            $clockSeq === false || $nodeHigh === false || $nodeLow === false) {
            // Fallback to a simpler UUID generation if unpack fails
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff)
            );
        }

        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            $timeLow[1],
            $timeMid[1],
            $timeHiVersion[1],
            $clockSeq[1],
            $nodeHigh[1] << 16 | $nodeLow[1]
        );
    }

    /**
     * Generate a unique MAC address
     *
     * @return string MAC address in format XX:XX:XX:XX:XX:XX
     */
    private function generateMacAddress(): string
    {
        // Use locally administered MAC address (second least significant bit of first octet set to 1)
        // First octet: 52 (01010010 in binary) indicates locally administered
        $mac = [0x52];

        // Generate 5 random octets
        for ($i = 1; $i < 6; $i++) {
            $mac[] = random_int(0x00, 0xff);
        }

        return implode(':', array_map(function ($octet) {
            return sprintf('%02x', $octet);
        }, $mac));
    }

    /**
     * Create and start a virtual machine
     *
     * @param SimpleVM $vm VM instance to create and start
     * @return SimpleVM|false Updated VM instance on success, false on failure
     */
    public function createAndStartVM(SimpleVM $vm)
    {
        $this->logger->info('Creating and starting VM', [
            'vm_name' => $vm->name,
            'user' => $vm->user,
        ]);

        // Validate parameters
        try {
            $this->validateVMParams($vm->toArray());
        } catch (ValidationException $e) {
            $this->logger->error('Invalid VM parameters', [
                'error' => $e->getMessage(),
                'vm_name' => $vm->name,
                'context' => $e->getContext(),
            ]);

            return false;
        }

        // Ensure connected to libvirt
        if (! $this->isConnected() && ! $this->connect()) {
            $this->logger->error('Failed to connect to libvirt');

            return false;
        }

        // Ensure user network exists
        if (! $this->ensureUserNetwork($vm->user)) {
            $this->logger->error('Failed to ensure user network', [
                'user' => $vm->user,
            ]);

            return false;
        }

        // Create disk volume
        $diskPath = $this->createDiskVolume($vm->name, $vm->disk);
        if ($diskPath === false) {
            $this->logger->error('Failed to create disk volume', [
                'vm_name' => $vm->name,
                'disk_size' => $vm->disk,
            ]);

            return false;
        }

        // Create cloud-init ISO
        $cloudInitISOPath = $this->createCloudInitISO($vm);
        if ($cloudInitISOPath === false) {
            $this->logger->error('Failed to create cloud-init ISO', [
                'vm_name' => $vm->name,
            ]);

            return false;
        }

        // Generate VM configuration XML
        $vmXml = $this->buildVMConfig($vm, $diskPath, $cloudInitISOPath);

        // Define the domain
        if (! function_exists('libvirt_domain_define_xml')) {
            $this->logger->error('libvirt_domain_define_xml function not available');

            return false;
        }

        if ($this->libvirtConnection === null) {
            $this->logger->error('libvirt connection is null');

            return false;
        }

        $domain = libvirt_domain_define_xml($this->libvirtConnection, $vmXml);
        if ($domain === false) {
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
            $this->logger->error('Failed to define VM domain', [
                'vm_name' => $vm->name,
                'error' => $error,
            ]);

            return false;
        }

        $this->logger->info('VM domain defined successfully', ['vm_name' => $vm->name]);

        // Start the VM
        if (! function_exists('libvirt_domain_create')) {
            $this->logger->error('libvirt_domain_create function not available');

            return false;
        }

        $result = libvirt_domain_create($domain);
        if ($result === false) {
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
            $this->logger->error('Failed to start VM', [
                'vm_name' => $vm->name,
                'error' => $error,
            ]);

            return false;
        }

        $this->logger->info('VM started successfully', ['vm_name' => $vm->name]);

        // Get VM state to confirm it's running
        $state = $this->getDomainState($domain);
        if ($state === false) {
            $this->logger->warning('Could not verify VM state', ['vm_name' => $vm->name]);
        } else {
            $this->logger->info('VM state verified', [
                'vm_name' => $vm->name,
                'state' => $state,
            ]);
        }

        // Update VM status
        $vm->status = 'running';

        $this->logger->info('VM created and started successfully', [
            'vm_name' => $vm->name,
            'user' => $vm->user,
            'status' => $vm->status,
        ]);

        return $vm;
    }

    /**
     * Get domain resource by VM name
     *
     * @param string $vmName VM name
     * @return resource|false Domain resource or false on failure
     */
    public function getDomainByName(string $vmName)
    {
        if (! $this->isConnected()) {
            $this->logger->error('Not connected to libvirt');

            return false;
        }

        if (! function_exists('libvirt_domain_lookup_by_name')) {
            $this->logger->error('libvirt_domain_lookup_by_name function not available');

            return false;
        }

        $domain = libvirt_domain_lookup_by_name($this->libvirtConnection, $vmName);
        if ($domain === false) {
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
            $this->logger->debug('Domain not found', [
                'vm_name' => $vmName,
                'error' => $error,
            ]);

            return false;
        }

        return $domain;
    }

    /**
     * Get domain state from domain resource
     *
     * @param resource $domain Domain resource
     * @return string|false State string or false on failure
     */
    public function getDomainState($domain)
    {
        if (! function_exists('libvirt_domain_get_info')) {
            $this->logger->error('libvirt_domain_get_info function not available');

            return false;
        }

        $info = libvirt_domain_get_info($domain);
        if (! is_array($info)) {
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
            $this->logger->error('Failed to get domain info', ['error' => $error]);

            return false;
        }

        // Domain states according to libvirt
        $states = [
            0 => 'nostate',
            1 => 'running',
            2 => 'blocked',
            3 => 'paused',
            4 => 'shutdown',
            5 => 'shutoff',
            6 => 'crashed',
            7 => 'pmsuspended',
        ];

        $stateId = $info['state'] ?? -1;
        $state = $states[$stateId] ?? 'unknown';

        $this->logger->debug('Domain state retrieved', [
            'state_id' => $stateId,
            'state' => $state,
            'info' => $info,
        ]);

        return $state;
    }

    /**
     * Check if VM is running
     *
     * @param string $vmName VM name
     * @return bool True if running, false otherwise
     */
    public function isVMRunning(string $vmName): bool
    {
        $domain = $this->getDomainByName($vmName);
        if ($domain === false) {
            return false;
        }

        $state = $this->getDomainState($domain);

        return $state === 'running';
    }

    /**
     * List all VMs (equivalent to virsh list --all)
     *
     * @return array<string, array<string, mixed>> Array of VM information keyed by name
     */
    public function listAllVMs(): array
    {
        if (! $this->isConnected()) {
            $this->logger->error('Not connected to libvirt');

            return [];
        }

        if (! function_exists('libvirt_list_domains') || ! function_exists('libvirt_list_inactive_domains')) {
            $this->logger->error('libvirt domain listing functions not available');

            return [];
        }

        $vms = [];

        // Get active domains
        $activeDomains = libvirt_list_domains($this->libvirtConnection);
        if ($activeDomains !== false) {
            foreach ($activeDomains as $domainName) {
                $domain = $this->getDomainByName($domainName);
                if ($domain !== false) {
                    $state = $this->getDomainState($domain);
                    $vms[$domainName] = [
                        'name' => $domainName,
                        'state' => $state ?: 'unknown',
                        'active' => true,
                    ];
                }
            }
        }

        // Get inactive domains
        $inactiveDomains = libvirt_list_inactive_domains($this->libvirtConnection);
        if ($inactiveDomains !== false) {
            foreach ($inactiveDomains as $domainName) {
                $vms[$domainName] = [
                    'name' => $domainName,
                    'state' => 'shutoff',
                    'active' => false,
                ];
            }
        }

        $this->logger->debug('Listed all VMs', ['count' => count($vms), 'vms' => array_keys($vms)]);

        return $vms;
    }

    /**
     * Alias for listAllVMs() to match web interface expectations
     *
     * @return array<int, array<string, mixed>>
     */
    public function listVMs(): array
    {
        $allVMs = $this->listAllVMs();

        // Convert to indexed array with simplified format
        $vms = [];
        foreach ($allVMs as $vm) {
            $vms[] = [
                'name' => $vm['name'],
                'user' => 'unknown', // We don't track user in VM metadata yet
                'status' => $vm['state'],
            ];
        }

        return $vms;
    }

    /**
     * Get SSH connection information for a VM
     *
     * @param SimpleVM $vm VM instance
     * @return array|false SSH info array or false on failure
     */
    public function getSSHInfo(SimpleVM $vm)
    {
        $this->logger->info('Getting SSH info for VM', ['vm_name' => $vm->name]);

        // Get domain
        $domain = $this->getDomainByName($vm->name);
        if ($domain === false) {
            $this->logger->error('Failed to get domain for SSH info', ['vm_name' => $vm->name]);

            return false;
        }

        // Check if VM is running
        if (! $this->isVMRunning($vm->name)) {
            $this->logger->error('VM is not running', ['vm_name' => $vm->name]);

            return false;
        }

        // Get IP address
        $ipAddress = $this->getVMIPAddress($vm->name, $vm->user);
        if ($ipAddress === false) {
            $this->logger->error('Failed to get IP address', ['vm_name' => $vm->name]);

            return false;
        }

        // Generate password if not set
        if (empty($vm->password)) {
            $password = $this->generatePassword();
            $vm->setSSHInfo($ipAddress, $password);
        } else {
            $password = $vm->password;
        }

        // Wait for SSH to be ready
        $sshReady = $this->waitForSSHReady($ipAddress, $vm->username);
        if (! $sshReady) {
            $this->logger->warning('SSH not ready yet', [
                'vm_name' => $vm->name,
                'ip' => $ipAddress,
            ]);
        }

        $sshInfo = [
            'ip' => $ipAddress,
            'username' => $vm->username,
            'password' => $password,
            'ready' => $sshReady,
        ];

        $this->logger->info('SSH info retrieved', [
            'vm_name' => $vm->name,
            'ip' => $ipAddress,
            'ready' => $sshReady,
        ]);

        return $sshInfo;
    }

    /**
     * Get VM IP address from DHCP leases or domain info
     *
     * @param string $vmName VM name
     * @param string $user User name (for network identification)
     * @return string|false IP address or false on failure
     */
    public function getVMIPAddress(string $vmName, string $user)
    {
        $this->logger->debug('Getting IP address for VM', [
            'vm_name' => $vmName,
            'user' => $user,
        ]);

        // First try to get from DHCP leases
        $networkName = $this->getNetworkName($user);
        if ($networkName === false) {
            $this->logger->error('Invalid user for network lookup', ['user' => $user]);

            return false;
        }
        $leases = $this->getDHCPLeases($networkName);

        foreach ($leases as $lease) {
            if (isset($lease['hostname']) && $lease['hostname'] === $vmName) {
                $this->logger->info('Found IP from DHCP lease', [
                    'vm_name' => $vmName,
                    'ip' => $lease['ip'],
                ]);

                return $lease['ip'];
            }
        }

        // If not found in leases, try domain network info
        if (! function_exists('libvirt_domain_interface_addresses')) {
            $this->logger->warning('libvirt_domain_interface_addresses not available');

            return false;
        }

        $domain = $this->getDomainByName($vmName);
        if ($domain === false) {
            return false;
        }

        $interfaces = @libvirt_domain_interface_addresses($domain, VIR_DOMAIN_INTERFACE_ADDRESSES_SRC_LEASE);
        if ($interfaces === false) {
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
            $this->logger->error('Failed to get domain interfaces', [
                'vm_name' => $vmName,
                'error' => $error,
            ]);

            return false;
        }

        // Look for the first valid IPv4 address
        foreach ($interfaces as $interface) {
            if (isset($interface['addrs'])) {
                foreach ($interface['addrs'] as $addr) {
                    if ($addr['type'] === 0) { // IPv4
                        $this->logger->info('Found IP from domain interface', [
                            'vm_name' => $vmName,
                            'ip' => $addr['addr'],
                        ]);

                        return $addr['addr'];
                    }
                }
            }
        }

        $this->logger->error('No IP address found for VM', ['vm_name' => $vmName]);

        return false;
    }

    /**
     * Get DHCP leases for a network
     *
     * @param string $networkName Network name
     * @return array Array of lease information
     */
    protected function getDHCPLeases(string $networkName): array
    {
        if (! function_exists('libvirt_network_get_dhcp_leases')) {
            $this->logger->warning('libvirt_network_get_dhcp_leases not available');

            return [];
        }

        if (! function_exists('libvirt_network_get')) {
            $this->logger->warning('libvirt_network_get not available');

            return [];
        }

        $network = @libvirt_network_get($this->libvirtConnection, $networkName);
        if ($network === false) {
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
            $this->logger->error('Failed to get network', [
                'network_name' => $networkName,
                'error' => $error,
            ]);

            return [];
        }

        $leases = @libvirt_network_get_dhcp_leases($network);
        if ($leases === false) {
            $error = $this->getLibvirtError() ?? 'Unknown libvirt error';
            $this->logger->error('Failed to get DHCP leases', [
                'network_name' => $networkName,
                'error' => $error,
            ]);

            return [];
        }

        $this->logger->debug('Retrieved DHCP leases', [
            'network_name' => $networkName,
            'lease_count' => count($leases),
        ]);

        return $leases;
    }

    /**
     * Generate a secure random password
     *
     * @param int $length Password length
     * @return string Generated password
     */
    public function generatePassword(int $length = 16): string
    {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()';
        $allChars = $lowercase . $uppercase . $numbers . $symbols;

        // Ensure password contains at least one character from each category
        $password = '';
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        // Fill the rest with random characters
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password to avoid predictable patterns
        $password = str_shuffle($password);

        $this->logger->debug('Generated password', ['length' => $length]);

        return $password;
    }

    /**
     * Wait for SSH service to be ready
     *
     * @param string $ipAddress IP address
     * @param string $username SSH username
     * @param int $timeout Timeout in seconds
     * @return bool True if SSH is ready, false otherwise
     */
    public function waitForSSHReady(string $ipAddress, string $username, int $timeout = 60): bool
    {
        $this->logger->info('Waiting for SSH to be ready', [
            'ip' => $ipAddress,
            'username' => $username,
            'timeout' => $timeout,
        ]);

        $startTime = time();
        while ((time() - $startTime) < $timeout) {
            // Try to connect to SSH port
            $connection = @fsockopen($ipAddress, 22, $errno, $errstr, 5);
            if ($connection !== false) {
                fclose($connection);
                $this->logger->info('SSH port is open', ['ip' => $ipAddress]);

                // Additional check with SSH command
                $command = sprintf(
                    'ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -o PasswordAuthentication=no %s@%s exit 2>&1',
                    escapeshellarg($username),
                    escapeshellarg($ipAddress)
                );

                exec($command, $output, $returnCode);

                // Return code 255 means connection refused or network error
                // Other codes mean SSH is responding (even if auth fails)
                if ($returnCode !== 255) {
                    $this->logger->info('SSH service is responding', [
                        'ip' => $ipAddress,
                        'return_code' => $returnCode,
                    ]);

                    return true;
                }
            }

            $this->logger->debug('SSH not ready yet, retrying...', [
                'ip' => $ipAddress,
                'elapsed' => time() - $startTime,
            ]);

            sleep(2);
        }

        $this->logger->warning('SSH readiness check timed out', [
            'ip' => $ipAddress,
            'timeout' => $timeout,
        ]);

        return false;
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
