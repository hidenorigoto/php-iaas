<?php

declare(strict_types=1);

namespace VmManagement;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use InvalidArgumentException;

/**
 * VMManager class for managing virtual machines using libvirt-php
 */
class VMManager
{
    private Logger $logger;
    
    /** @var array<string, int> */
    private const USER_VLAN_MAP = [
        'user1' => 100,
        'user2' => 101,
        'user3' => 102,
    ];

    private const DEFAULT_CPU = 2;
    private const DEFAULT_MEMORY = 2048;
    private const DEFAULT_DISK = 20;

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
        if (empty($params['name']) || !is_string($params['name'])) {
            throw new InvalidArgumentException('VM name is required and must be a string');
        }

        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $params['name'])) {
            throw new InvalidArgumentException('VM name can only contain alphanumeric characters, hyphens, and underscores');
        }

        if (strlen($params['name']) > 50) {
            throw new InvalidArgumentException('VM name must be 50 characters or less');
        }

        // Validate user
        if (empty($params['user']) || !is_string($params['user'])) {
            throw new InvalidArgumentException('User is required and must be a string');
        }

        if (!array_key_exists($params['user'], self::USER_VLAN_MAP)) {
            throw new InvalidArgumentException('User must be one of: user1, user2, user3');
        }

        // Validate CPU (optional)
        if (isset($params['cpu'])) {
            if (!is_int($params['cpu']) || $params['cpu'] < 1 || $params['cpu'] > 8) {
                throw new InvalidArgumentException('CPU must be an integer between 1 and 8');
            }
        }

        // Validate memory (optional)
        if (isset($params['memory'])) {
            if (!is_int($params['memory']) || $params['memory'] < 512 || $params['memory'] > 8192) {
                throw new InvalidArgumentException('Memory must be an integer between 512 and 8192 MB');
            }
        }

        // Validate disk (optional)
        if (isset($params['disk'])) {
            if (!is_int($params['disk']) || $params['disk'] < 10 || $params['disk'] > 100) {
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
}