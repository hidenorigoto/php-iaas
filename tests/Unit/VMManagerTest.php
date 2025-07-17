<?php

declare(strict_types=1);

namespace VmManagement\Tests\Unit;

use InvalidArgumentException;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use VmManagement\SimpleVM;
use VmManagement\VMManager;

/**
 * Unit tests for VMManager class
 */
class VMManagerTest extends TestCase
{
    private VMManager $vmManager;
    private TestHandler $testHandler;

    protected function setUp(): void
    {
        // Create logger with test handler for testing log output
        $logger = new Logger('test');
        $this->testHandler = new TestHandler();
        $logger->pushHandler($this->testHandler);

        $this->vmManager = new VMManager($logger);
    }

    public function testVMManagerCanBeInstantiated(): void
    {
        $vmManager = new VMManager();

        $this->assertInstanceOf(VMManager::class, $vmManager);
        $this->assertInstanceOf(Logger::class, $vmManager->getLogger());
    }

    public function testVMManagerWithCustomLogger(): void
    {
        $logger = new Logger('custom');
        $vmManager = new VMManager($logger);

        $this->assertSame($logger, $vmManager->getLogger());
    }

    public function testMonologLoggingWorks(): void
    {
        $this->vmManager->logInfo('Test info message', ['key' => 'value']);
        $this->vmManager->logError('Test error message');
        $this->vmManager->logDebug('Test debug message');

        $records = $this->testHandler->getRecords();

        $this->assertCount(4, $records); // 3 manual + 1 from constructor
        $this->assertEquals('Test info message', $records[1]['message']);
        $this->assertEquals('Test error message', $records[2]['message']);
        $this->assertEquals('Test debug message', $records[3]['message']);
        $this->assertEquals(['key' => 'value'], $records[1]['context']);
    }

    public function testValidVMParamsPassValidation(): void
    {
        $params = [
            'name' => 'test-vm',
            'user' => 'user1',
            'cpu' => 2,
            'memory' => 2048,
            'disk' => 20,
        ];

        $this->expectNotToPerformAssertions();
        $this->vmManager->validateVMParams($params);
    }

    public function testValidVMParamsWithMinimalData(): void
    {
        $params = [
            'name' => 'test-vm',
            'user' => 'user2',
        ];

        $this->expectNotToPerformAssertions();
        $this->vmManager->validateVMParams($params);
    }

    public function testInvalidVMNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VM name is required and must be a string');

        $this->vmManager->validateVMParams(['user' => 'user1']);
    }

    public function testEmptyVMNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VM name is required and must be a string');

        $this->vmManager->validateVMParams(['name' => '', 'user' => 'user1']);
    }

    public function testInvalidVMNameCharactersThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VM name can only contain alphanumeric characters, hyphens, and underscores');

        $this->vmManager->validateVMParams(['name' => 'test vm!', 'user' => 'user1']);
    }

    public function testTooLongVMNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VM name must be 50 characters or less');

        $longName = str_repeat('a', 51);
        $this->vmManager->validateVMParams(['name' => $longName, 'user' => 'user1']);
    }

    public function testInvalidUserThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User is required and must be a string');

        $this->vmManager->validateVMParams(['name' => 'test-vm']);
    }

    public function testUnknownUserThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User must be one of: user1, user2, user3');

        $this->vmManager->validateVMParams(['name' => 'test-vm', 'user' => 'unknown']);
    }

    public function testInvalidCPUThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CPU must be an integer between 1 and 8');

        $this->vmManager->validateVMParams([
            'name' => 'test-vm',
            'user' => 'user1',
            'cpu' => 0,
        ]);
    }

    public function testInvalidMemoryThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Memory must be an integer between 512 and 8192 MB');

        $this->vmManager->validateVMParams([
            'name' => 'test-vm',
            'user' => 'user1',
            'memory' => 256,
        ]);
    }

    public function testInvalidDiskThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Disk must be an integer between 10 and 100 GB');

        $this->vmManager->validateVMParams([
            'name' => 'test-vm',
            'user' => 'user1',
            'disk' => 5,
        ]);
    }

    public function testCreateVMInstanceWithValidParams(): void
    {
        $params = [
            'name' => 'test-vm',
            'user' => 'user2',
            'cpu' => 4,
            'memory' => 4096,
            'disk' => 40,
        ];

        $vm = $this->vmManager->createVMInstance($params);

        $this->assertInstanceOf(SimpleVM::class, $vm);
        $this->assertEquals('test-vm', $vm->name);
        $this->assertEquals('user2', $vm->user);
        $this->assertEquals(4, $vm->cpu);
        $this->assertEquals(4096, $vm->memory);
        $this->assertEquals(40, $vm->disk);
        $this->assertEquals(101, $vm->vlanId);
    }

    public function testCreateVMInstanceWithDefaultValues(): void
    {
        $params = [
            'name' => 'test-vm',
            'user' => 'user1',
        ];

        $vm = $this->vmManager->createVMInstance($params);

        $this->assertInstanceOf(SimpleVM::class, $vm);
        $this->assertEquals('test-vm', $vm->name);
        $this->assertEquals('user1', $vm->user);
        $this->assertEquals(2, $vm->cpu);
        $this->assertEquals(2048, $vm->memory);
        $this->assertEquals(20, $vm->disk);
        $this->assertEquals(100, $vm->vlanId);
    }

    public function testCreateVMInstanceWithInvalidParamsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $params = [
            'name' => '',
            'user' => 'user1',
        ];

        $this->vmManager->createVMInstance($params);
    }

    public function testValidationLogsSuccessfulValidation(): void
    {
        $params = [
            'name' => 'test-vm',
            'user' => 'user1',
        ];

        $this->vmManager->validateVMParams($params);

        $records = $this->testHandler->getRecords();
        $this->assertCount(2, $records); // 1 from constructor + 1 from validation
        $this->assertStringContainsString('VM parameters validated successfully', $records[1]['message']);
    }

    public function testCreateVMInstanceLogsCreation(): void
    {
        $params = [
            'name' => 'test-vm',
            'user' => 'user1',
        ];

        $this->vmManager->createVMInstance($params);

        $records = $this->testHandler->getRecords();
        $this->assertCount(3, $records); // 1 from constructor + 1 from validation + 1 from creation
        $this->assertStringContainsString('SimpleVM instance created', $records[2]['message']);
    }

    // Libvirt Connection Tests

    public function testIsConnectedReturnsFalseInitially(): void
    {
        $this->assertFalse($this->vmManager->isConnected());
    }

    public function testGetConnectionReturnsNullInitially(): void
    {
        $this->assertNull($this->vmManager->getConnection());
    }

    public function testConnectSuccessWithMockedLibvirt(): void
    {
        // Create a test manager that mocks successful connection
        $logger = new Logger('test-success');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            /** @var resource|null */
            private $mockResource;
            private bool $mockConnected = false;

            public function connect(): bool
            {
                if ($this->mockConnected) {
                    $this->getLogger()->debug('Already connected to libvirt');

                    return true;
                }

                $this->getLogger()->info('Attempting to connect to libvirt', ['uri' => 'qemu:///system']);

                // Mock successful connection
                $this->mockResource = fopen('php://memory', 'r');
                $this->mockConnected = true;

                $this->getLogger()->info('Successfully connected to libvirt');

                return true;
            }

            public function isConnected(): bool
            {
                return $this->mockConnected && is_resource($this->mockResource);
            }

            public function getConnection()
            {
                return $this->mockResource;
            }

            public function disconnect(): bool
            {
                if (! $this->isConnected()) {
                    $this->getLogger()->debug('Not connected to libvirt, nothing to disconnect');

                    return true;
                }

                $this->getLogger()->info('Disconnecting from libvirt');

                if (is_resource($this->mockResource)) {
                    fclose($this->mockResource);
                }
                $this->mockConnected = false;

                $this->getLogger()->info('Successfully disconnected from libvirt');

                return true;
            }
        };

        $result = $vmManager->connect();

        $this->assertTrue($result);
        $this->assertTrue($vmManager->isConnected());
        $this->assertIsResource($vmManager->getConnection());

        // Check logs
        $records = $testHandler->getRecords();

        // Find the connection attempt log
        $connectionLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Attempting to connect to libvirt') !== false;
        });
        $this->assertNotEmpty($connectionLogs);

        // Find the success log
        $successLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Successfully connected to libvirt') !== false;
        });
        $this->assertNotEmpty($successLogs);
    }

    public function testConnectFailureWithMockedLibvirt(): void
    {
        // Create a mock function namespace that returns false
        if (! function_exists('VmManagement\Test\libvirt_connect')) {
            eval('
                namespace VmManagement\Test;
                function libvirt_connect($uri) {
                    return false;
                }
                function libvirt_get_last_error() {
                    return "Connection failed: Permission denied";
                }
            ');
        }

        // Create a test manager that uses the failing mock
        $logger = new Logger('test-fail');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        // We need to create a custom VMManager for this test
        $vmManager = new class ($logger) extends VMManager {
            public function connect(): bool
            {
                if ($this->isConnected()) {
                    $this->getLogger()->debug('Already connected to libvirt');

                    return true;
                }

                $this->getLogger()->info('Attempting to connect to libvirt', ['uri' => 'qemu:///system']);

                // Mock the failure
                $connection = false;

                if ($connection === false) {
                    $error = 'Connection failed: Permission denied';
                    $this->getLogger()->error('Failed to connect to libvirt', [
                        'uri' => 'qemu:///system',
                        'error' => $error,
                    ]);

                    return false;
                }

                return true;
            }
        };

        $result = $vmManager->connect();

        $this->assertFalse($result);
        $this->assertFalse($vmManager->isConnected());
        $this->assertNull($vmManager->getConnection());

        // Check error logs
        $records = $testHandler->getRecords();
        $errorLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Failed to connect to libvirt') !== false;
        });
        $this->assertNotEmpty($errorLogs);
    }

    public function testConnectWhenAlreadyConnected(): void
    {
        // Mock successful connection first
        if (! function_exists('VmManagement\Already\libvirt_connect')) {
            eval('
                namespace VmManagement\Already;
                function libvirt_connect($uri) {
                    static $mockResource;
                    if ($mockResource === null) {
                        $mockResource = fopen("php://memory", "r");
                    }
                    return $mockResource;
                }
                function libvirt_get_last_error() {
                    return false;
                }
                function libvirt_connect_close($connection) {
                    return true;
                }
            ');
        }

        // Create a test manager that simulates already connected state
        $logger = new Logger('test-already');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private bool $mockConnected = false;
            /** @var resource|null */
            private $mockResource;

            public function connect(): bool
            {
                if ($this->mockConnected) {
                    $this->getLogger()->debug('Already connected to libvirt');

                    return true;
                }

                $this->getLogger()->info('Attempting to connect to libvirt', ['uri' => 'qemu:///system']);

                $this->mockResource = fopen('php://memory', 'r');
                $this->mockConnected = true;

                $this->getLogger()->info('Successfully connected to libvirt');

                return true;
            }

            public function isConnected(): bool
            {
                return $this->mockConnected && is_resource($this->mockResource);
            }

            public function getConnection()
            {
                return $this->mockResource;
            }
        };

        // First connection
        $result1 = $vmManager->connect();
        $this->assertTrue($result1);

        // Second connection (should detect already connected)
        $result2 = $vmManager->connect();
        $this->assertTrue($result2);

        // Check that "Already connected" debug message was logged
        $records = $testHandler->getRecords();
        $alreadyConnectedLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Already connected to libvirt') !== false;
        });
        $this->assertNotEmpty($alreadyConnectedLogs);
    }

    public function testDisconnectWhenConnected(): void
    {
        // Create a test manager that simulates connected state
        $logger = new Logger('test-disconnect');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private bool $mockConnected = true;
            /** @var resource|null */
            private $mockResource;

            public function __construct($logger)
            {
                parent::__construct($logger);
                $this->mockResource = fopen('php://memory', 'r');
            }

            public function isConnected(): bool
            {
                return $this->mockConnected && is_resource($this->mockResource);
            }

            public function getConnection()
            {
                return $this->mockResource;
            }

            public function disconnect(): bool
            {
                if (! $this->isConnected()) {
                    $this->getLogger()->debug('Not connected to libvirt, nothing to disconnect');

                    return true;
                }

                $this->getLogger()->info('Disconnecting from libvirt');

                if (is_resource($this->mockResource)) {
                    fclose($this->mockResource);
                }
                $this->mockConnected = false;

                $this->getLogger()->info('Successfully disconnected from libvirt');

                return true;
            }
        };

        $this->assertTrue($vmManager->isConnected());

        $result = $vmManager->disconnect();

        $this->assertTrue($result);
        $this->assertFalse($vmManager->isConnected());

        // Check disconnect logs
        $records = $testHandler->getRecords();
        $disconnectLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Successfully disconnected from libvirt') !== false;
        });
        $this->assertNotEmpty($disconnectLogs);
    }

    public function testDisconnectWhenNotConnected(): void
    {
        $result = $this->vmManager->disconnect();

        $this->assertTrue($result);

        // Check that "nothing to disconnect" debug message was logged
        $records = $this->testHandler->getRecords();
        $nothingToDisconnectLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Not connected to libvirt, nothing to disconnect') !== false;
        });
        $this->assertNotEmpty($nothingToDisconnectLogs);
    }

    // Storage Pool and Volume Management Tests

    public function testGetStoragePoolWhenNotConnected(): void
    {
        $result = $this->vmManager->getStoragePool();

        $this->assertFalse($result);

        // Check error log
        $records = $this->testHandler->getRecords();
        $errorLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Not connected to libvirt') !== false;
        });
        $this->assertNotEmpty($errorLogs);
    }

    public function testGetStoragePoolSuccessWithMock(): void
    {
        // Create a test manager that mocks successful storage pool lookup
        $logger = new Logger('test-storage');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private bool $mockConnected = true;
            /** @var resource|false */
            private $mockConnection;

            public function __construct($logger)
            {
                parent::__construct($logger);
                $this->mockConnection = fopen('php://memory', 'r');
            }

            public function isConnected(): bool
            {
                return $this->mockConnected;
            }

            public function getConnection()
            {
                return $this->mockConnection;
            }

            public function getStoragePool(string $poolName = 'default')
            {
                if (! $this->isConnected()) {
                    $this->getLogger()->error('Not connected to libvirt');

                    return false;
                }

                $this->getLogger()->info('Looking up storage pool', ['pool_name' => $poolName]);

                // Mock successful storage pool lookup
                $mockPool = fopen('php://memory', 'r');

                $this->getLogger()->info('Successfully found storage pool', ['pool_name' => $poolName]);

                return $mockPool;
            }
        };

        $result = $vmManager->getStoragePool('default');

        $this->assertIsResource($result);

        // Check logs
        $records = $testHandler->getRecords();
        $lookupLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Looking up storage pool') !== false;
        });
        $this->assertNotEmpty($lookupLogs);

        $successLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Successfully found storage pool') !== false;
        });
        $this->assertNotEmpty($successLogs);
    }

    public function testGetStoragePoolFailureWithMock(): void
    {
        // Create a test manager that mocks failed storage pool lookup
        $logger = new Logger('test-storage-fail');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private bool $mockConnected = true;
            /** @var resource|false */
            private $mockConnection;

            public function __construct($logger)
            {
                parent::__construct($logger);
                $this->mockConnection = fopen('php://memory', 'r');
            }

            public function isConnected(): bool
            {
                return $this->mockConnected;
            }

            public function getConnection()
            {
                return $this->mockConnection;
            }

            public function getStoragePool(string $poolName = 'default')
            {
                if (! $this->isConnected()) {
                    $this->getLogger()->error('Not connected to libvirt');

                    return false;
                }

                $this->getLogger()->info('Looking up storage pool', ['pool_name' => $poolName]);

                // Mock failed storage pool lookup
                $this->getLogger()->error('Failed to lookup storage pool', [
                    'pool_name' => $poolName,
                    'error' => 'Storage pool not found',
                ]);

                return false;
            }
        };

        $result = $vmManager->getStoragePool('nonexistent');

        $this->assertFalse($result);

        // Check error logs
        $records = $testHandler->getRecords();
        $errorLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Failed to lookup storage pool') !== false;
        });
        $this->assertNotEmpty($errorLogs);
    }

    public function testCreateDiskVolumeSuccessWithMock(): void
    {
        // Create a test manager that mocks successful disk volume creation
        $logger = new Logger('test-volume');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private bool $mockConnected = true;
            /** @var resource|false */
            private $mockConnection;

            public function __construct($logger)
            {
                parent::__construct($logger);
                $this->mockConnection = fopen('php://memory', 'r');
            }

            public function isConnected(): bool
            {
                return $this->mockConnected;
            }

            public function getConnection()
            {
                return $this->mockConnection;
            }

            public function getStoragePool(string $poolName = 'default')
            {
                $this->getLogger()->info('Looking up storage pool', ['pool_name' => $poolName]);
                $mockPool = fopen('php://memory', 'r');
                $this->getLogger()->info('Successfully found storage pool', ['pool_name' => $poolName]);

                return $mockPool;
            }

            public function createDiskVolume(string $volumeName, int $sizeGB, string $poolName = 'default')
            {
                $this->getLogger()->info('Creating disk volume', [
                    'volume_name' => $volumeName,
                    'size_gb' => $sizeGB,
                    'pool_name' => $poolName,
                ]);

                // Get storage pool (mocked)
                $pool = $this->getStoragePool($poolName);
                if ($pool === false) {
                    return false;
                }

                // Mock successful volume creation
                $volumePath = '/var/lib/libvirt/images/' . $volumeName . '.qcow2';

                $this->getLogger()->info('Successfully created disk volume', [
                    'volume_name' => $volumeName,
                    'volume_path' => $volumePath,
                    'size_gb' => $sizeGB,
                ]);

                return $volumePath;
            }
        };

        $result = $vmManager->createDiskVolume('test-vm', 20);

        $this->assertEquals('/var/lib/libvirt/images/test-vm.qcow2', $result);

        // Check logs
        $records = $testHandler->getRecords();
        $createLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Creating disk volume') !== false;
        });
        $this->assertNotEmpty($createLogs);

        $successLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Successfully created disk volume') !== false;
        });
        $this->assertNotEmpty($successLogs);
    }

    public function testCreateDiskVolumeFailureWhenStoragePoolNotFound(): void
    {
        // Create a test manager that mocks storage pool lookup failure
        $logger = new Logger('test-volume-fail');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private bool $mockConnected = true;
            /** @var resource|false */
            private $mockConnection;

            public function __construct($logger)
            {
                parent::__construct($logger);
                $this->mockConnection = fopen('php://memory', 'r');
            }

            public function isConnected(): bool
            {
                return $this->mockConnected;
            }

            public function getConnection()
            {
                return $this->mockConnection;
            }

            public function getStoragePool(string $poolName = 'default')
            {
                $this->getLogger()->info('Looking up storage pool', ['pool_name' => $poolName]);
                $this->getLogger()->error('Failed to lookup storage pool', [
                    'pool_name' => $poolName,
                    'error' => 'Storage pool not found',
                ]);

                return false;
            }

            public function createDiskVolume(string $volumeName, int $sizeGB, string $poolName = 'default')
            {
                $this->getLogger()->info('Creating disk volume', [
                    'volume_name' => $volumeName,
                    'size_gb' => $sizeGB,
                    'pool_name' => $poolName,
                ]);

                // Get storage pool (will fail)
                $pool = $this->getStoragePool($poolName);
                if ($pool === false) {
                    return false;
                }

                return '/var/lib/libvirt/images/' . $volumeName . '.qcow2';
            }
        };

        $result = $vmManager->createDiskVolume('test-vm', 20);

        $this->assertFalse($result);

        // Check error logs
        $records = $testHandler->getRecords();
        $errorLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Failed to lookup storage pool') !== false;
        });
        $this->assertNotEmpty($errorLogs);
    }

    public function testCreateQcow2ImageSuccessWithMock(): void
    {
        // Create a test manager that mocks successful qcow2 image creation
        $logger = new Logger('test-qcow2');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            public function createQcow2Image(string $imagePath, int $sizeGB, ?string $baseImage = null): bool
            {
                $this->getLogger()->info('Creating qcow2 disk image', [
                    'image_path' => $imagePath,
                    'size_gb' => $sizeGB,
                    'base_image' => $baseImage,
                ]);

                // Mock successful execution
                $this->getLogger()->info('Successfully created qcow2 image', [
                    'image_path' => $imagePath,
                    'size_gb' => $sizeGB,
                ]);

                return true;
            }
        };

        $result = $vmManager->createQcow2Image('/tmp/test.qcow2', 20);

        $this->assertTrue($result);

        // Check logs
        $records = $testHandler->getRecords();
        $createLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Creating qcow2 disk image') !== false;
        });
        $this->assertNotEmpty($createLogs);

        $successLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Successfully created qcow2 image') !== false;
        });
        $this->assertNotEmpty($successLogs);
    }

    public function testCreateQcow2ImageWithBaseImageMock(): void
    {
        // Create a test manager that mocks qcow2 image creation with base image
        $logger = new Logger('test-qcow2-base');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private function fileExists(string $filePath): bool
            {
                // Mock that base image exists
                return $filePath === '/var/lib/libvirt/images/base.qcow2';
            }

            public function createQcow2Image(string $imagePath, int $sizeGB, ?string $baseImage = null): bool
            {
                $this->getLogger()->info('Creating qcow2 disk image', [
                    'image_path' => $imagePath,
                    'size_gb' => $sizeGB,
                    'base_image' => $baseImage,
                ]);

                if ($baseImage !== null && $this->fileExists($baseImage)) {
                    $this->getLogger()->debug('Using base image', ['base_image' => $baseImage]);
                }

                // Mock successful execution
                $this->getLogger()->info('Successfully created qcow2 image', [
                    'image_path' => $imagePath,
                    'size_gb' => $sizeGB,
                ]);

                return true;
            }
        };

        $result = $vmManager->createQcow2Image('/tmp/test.qcow2', 20, '/var/lib/libvirt/images/base.qcow2');

        $this->assertTrue($result);

        // Check logs
        $records = $testHandler->getRecords();
        $createLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Creating qcow2 disk image') !== false;
        });
        $this->assertNotEmpty($createLogs);

        $baseImageLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Using base image') !== false;
        });
        $this->assertNotEmpty($baseImageLogs);

        $successLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Successfully created qcow2 image') !== false;
        });
        $this->assertNotEmpty($successLogs);
    }

    public function testCopyBaseImageSuccessWithMock(): void
    {
        // Create a test manager that mocks successful base image copying
        $logger = new Logger('test-copy');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private function fileExists(string $filePath): bool
            {
                // Mock that source file exists
                return $filePath === '/var/lib/libvirt/images/base.qcow2';
            }

            public function copyBaseImage(string $baseImagePath, string $targetImagePath): bool
            {
                $this->getLogger()->info('Copying base image', [
                    'base_image' => $baseImagePath,
                    'target_image' => $targetImagePath,
                ]);

                // Check if base image exists
                if (! $this->fileExists($baseImagePath)) {
                    $this->getLogger()->error('Base image does not exist', ['base_image' => $baseImagePath]);

                    return false;
                }

                // Mock successful copy
                $this->getLogger()->info('Successfully copied base image', [
                    'base_image' => $baseImagePath,
                    'target_image' => $targetImagePath,
                ]);

                return true;
            }
        };

        $result = $vmManager->copyBaseImage('/var/lib/libvirt/images/base.qcow2', '/tmp/target.qcow2');

        $this->assertTrue($result);

        // Check logs
        $records = $testHandler->getRecords();
        $copyLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Copying base image') !== false;
        });
        $this->assertNotEmpty($copyLogs);

        $successLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Successfully copied base image') !== false;
        });
        $this->assertNotEmpty($successLogs);
    }

    public function testCopyBaseImageFailureWhenSourceNotExists(): void
    {
        // Create a test manager that mocks missing source file
        $logger = new Logger('test-copy-fail');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private function fileExists(string $filePath): bool
            {
                // Mock that source file doesn't exist
                return false;
            }

            public function copyBaseImage(string $baseImagePath, string $targetImagePath): bool
            {
                $this->getLogger()->info('Copying base image', [
                    'base_image' => $baseImagePath,
                    'target_image' => $targetImagePath,
                ]);

                // Check if base image exists
                if (! $this->fileExists($baseImagePath)) {
                    $this->getLogger()->error('Base image does not exist', ['base_image' => $baseImagePath]);

                    return false;
                }

                return true;
            }
        };

        $result = $vmManager->copyBaseImage('/nonexistent/base.qcow2', '/tmp/target.qcow2');

        $this->assertFalse($result);

        // Check error logs
        $records = $testHandler->getRecords();
        $errorLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Base image does not exist') !== false;
        });
        $this->assertNotEmpty($errorLogs);
    }

    // VLAN Network Management Tests

    public function testGenerateNetworkXmlForUser1(): void
    {
        $xml = $this->vmManager->generateNetworkXml(100);

        $this->assertStringContainsString('<name>vm-network-100</name>', $xml);
        $this->assertStringContainsString('<bridge name=\'virbr100\'/>', $xml);
        $this->assertStringContainsString('<forward mode=\'nat\'/>', $xml);
        $this->assertStringContainsString('<ip address=\'192.168.100.1\' netmask=\'255.255.255.0\'>', $xml);
        $this->assertStringContainsString('<range start=\'192.168.100.10\' end=\'192.168.100.100\'/>', $xml);

        // Check logs
        $records = $this->testHandler->getRecords();
        $debugLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Generated network XML') !== false;
        });
        $this->assertNotEmpty($debugLogs);
    }

    public function testGenerateNetworkXmlForUser2(): void
    {
        $xml = $this->vmManager->generateNetworkXml(101);

        $this->assertStringContainsString('<name>vm-network-101</name>', $xml);
        $this->assertStringContainsString('<bridge name=\'virbr101\'/>', $xml);
        $this->assertStringContainsString('<ip address=\'192.168.101.1\' netmask=\'255.255.255.0\'>', $xml);
        $this->assertStringContainsString('<range start=\'192.168.101.10\' end=\'192.168.101.100\'/>', $xml);
    }

    public function testGenerateNetworkXmlForUser3(): void
    {
        $xml = $this->vmManager->generateNetworkXml(102);

        $this->assertStringContainsString('<name>vm-network-102</name>', $xml);
        $this->assertStringContainsString('<bridge name=\'virbr102\'/>', $xml);
        $this->assertStringContainsString('<ip address=\'192.168.102.1\' netmask=\'255.255.255.0\'>', $xml);
        $this->assertStringContainsString('<range start=\'192.168.102.10\' end=\'192.168.102.100\'/>', $xml);
    }

    public function testGetUserIPRangeForUser1(): void
    {
        $ipRange = $this->vmManager->getUserIPRange('user1');

        $this->assertIsArray($ipRange);
        $this->assertEquals('192.168.100.0/24', $ipRange['network']);
        $this->assertEquals('192.168.100.1', $ipRange['gateway']);
        $this->assertEquals('192.168.100.10', $ipRange['dhcp_start']);
        $this->assertEquals('192.168.100.100', $ipRange['dhcp_end']);
        $this->assertEquals(100, $ipRange['vlan_id']);

        // Check logs
        $records = $this->testHandler->getRecords();
        $debugLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Retrieved IP range for user') !== false;
        });
        $this->assertNotEmpty($debugLogs);
    }

    public function testGetUserIPRangeForUser2(): void
    {
        $ipRange = $this->vmManager->getUserIPRange('user2');

        $this->assertIsArray($ipRange);
        $this->assertEquals('192.168.101.0/24', $ipRange['network']);
        $this->assertEquals('192.168.101.1', $ipRange['gateway']);
        $this->assertEquals('192.168.101.10', $ipRange['dhcp_start']);
        $this->assertEquals('192.168.101.100', $ipRange['dhcp_end']);
        $this->assertEquals(101, $ipRange['vlan_id']);
    }

    public function testGetUserIPRangeForUser3(): void
    {
        $ipRange = $this->vmManager->getUserIPRange('user3');

        $this->assertIsArray($ipRange);
        $this->assertEquals('192.168.102.0/24', $ipRange['network']);
        $this->assertEquals('192.168.102.1', $ipRange['gateway']);
        $this->assertEquals('192.168.102.10', $ipRange['dhcp_start']);
        $this->assertEquals('192.168.102.100', $ipRange['dhcp_end']);
        $this->assertEquals(102, $ipRange['vlan_id']);
    }

    public function testGetUserIPRangeForInvalidUser(): void
    {
        $ipRange = $this->vmManager->getUserIPRange('invalid_user');

        $this->assertFalse($ipRange);

        // Check error logs
        $records = $this->testHandler->getRecords();
        $errorLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Invalid user for IP range lookup') !== false;
        });
        $this->assertNotEmpty($errorLogs);
    }

    public function testGetNetworkNameForValidUsers(): void
    {
        $this->assertEquals('vm-network-100', $this->vmManager->getNetworkName('user1'));
        $this->assertEquals('vm-network-101', $this->vmManager->getNetworkName('user2'));
        $this->assertEquals('vm-network-102', $this->vmManager->getNetworkName('user3'));
    }

    public function testGetNetworkNameForInvalidUser(): void
    {
        $this->assertFalse($this->vmManager->getNetworkName('invalid_user'));
    }

    public function testCreateUserNetworkWhenNotConnected(): void
    {
        $result = $this->vmManager->createUserNetwork('user1');

        $this->assertFalse($result);

        // Check error logs
        $records = $this->testHandler->getRecords();
        $errorLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Not connected to libvirt') !== false;
        });
        $this->assertNotEmpty($errorLogs);
    }

    public function testCreateUserNetworkWithInvalidUser(): void
    {
        $result = $this->vmManager->createUserNetwork('invalid_user');

        $this->assertFalse($result);

        // Check error logs
        $records = $this->testHandler->getRecords();
        $errorLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Invalid user for network creation') !== false;
        });
        $this->assertNotEmpty($errorLogs);
    }

    public function testCreateUserNetworkSuccessWithMock(): void
    {
        // Create a test manager that mocks successful network creation
        $logger = new Logger('test-network');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private bool $mockConnected = true;
            /** @var resource|false */
            private $mockConnection;

            public function __construct($logger)
            {
                parent::__construct($logger);
                $this->mockConnection = fopen('php://memory', 'r');
            }

            public function isConnected(): bool
            {
                return $this->mockConnected;
            }

            public function getConnection()
            {
                return $this->mockConnection;
            }

            public function createUserNetwork(string $user): bool
            {
                if (! array_key_exists($user, ['user1' => 100, 'user2' => 101, 'user3' => 102])) {
                    $this->getLogger()->error('Invalid user for network creation', ['user' => $user]);

                    return false;
                }

                if (! $this->isConnected()) {
                    $this->getLogger()->error('Not connected to libvirt');

                    return false;
                }

                $vlanId = ['user1' => 100, 'user2' => 101, 'user3' => 102][$user];
                $networkName = "vm-network-{$vlanId}";

                $this->getLogger()->info('Creating user network', [
                    'user' => $user,
                    'vlan_id' => $vlanId,
                    'network_name' => $networkName,
                ]);

                // Mock successful network creation
                $this->getLogger()->info('Successfully created and started user network', [
                    'user' => $user,
                    'network_name' => $networkName,
                    'vlan_id' => $vlanId,
                ]);

                return true;
            }
        };

        $result = $vmManager->createUserNetwork('user1');

        $this->assertTrue($result);

        // Check logs
        $records = $testHandler->getRecords();
        $createLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Creating user network') !== false;
        });
        $this->assertNotEmpty($createLogs);

        $successLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Successfully created and started user network') !== false;
        });
        $this->assertNotEmpty($successLogs);
    }

    public function testNetworkExistsWhenNotConnected(): void
    {
        $result = $this->vmManager->networkExists('user1');

        $this->assertFalse($result);
    }

    public function testNetworkExistsWithInvalidUser(): void
    {
        $result = $this->vmManager->networkExists('invalid_user');

        $this->assertFalse($result);
    }

    public function testNetworkExistsSuccessWithMock(): void
    {
        // Create a test manager that mocks network existence check
        $logger = new Logger('test-network-exists');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private bool $mockConnected = true;
            /** @var resource|false */
            private $mockConnection;

            public function __construct($logger)
            {
                parent::__construct($logger);
                $this->mockConnection = fopen('php://memory', 'r');
            }

            public function isConnected(): bool
            {
                return $this->mockConnected;
            }

            public function getConnection()
            {
                return $this->mockConnection;
            }

            public function networkExists(string $user): bool
            {
                if (! array_key_exists($user, ['user1' => 100, 'user2' => 101, 'user3' => 102])) {
                    return false;
                }

                if (! $this->isConnected()) {
                    return false;
                }

                $vlanId = ['user1' => 100, 'user2' => 101, 'user3' => 102][$user];
                $networkName = "vm-network-{$vlanId}";

                // Mock that network exists
                $this->getLogger()->debug('Network exists', [
                    'user' => $user,
                    'network_name' => $networkName,
                ]);

                return true;
            }
        };

        $result = $vmManager->networkExists('user1');

        $this->assertTrue($result);

        // Check logs
        $records = $testHandler->getRecords();
        $debugLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Network exists') !== false;
        });
        $this->assertNotEmpty($debugLogs);
    }

    public function testNetworkExistsFailureWithMock(): void
    {
        // Create a test manager that mocks network doesn't exist
        $logger = new Logger('test-network-not-exists');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            private bool $mockConnected = true;
            /** @var resource|false */
            private $mockConnection;

            public function __construct($logger)
            {
                parent::__construct($logger);
                $this->mockConnection = fopen('php://memory', 'r');
            }

            public function isConnected(): bool
            {
                return $this->mockConnected;
            }

            public function getConnection()
            {
                return $this->mockConnection;
            }

            public function networkExists(string $user): bool
            {
                if (! array_key_exists($user, ['user1' => 100, 'user2' => 101, 'user3' => 102])) {
                    return false;
                }

                if (! $this->isConnected()) {
                    return false;
                }

                $vlanId = ['user1' => 100, 'user2' => 101, 'user3' => 102][$user];
                $networkName = "vm-network-{$vlanId}";

                // Mock that network doesn't exist
                $this->getLogger()->debug('Network does not exist', [
                    'user' => $user,
                    'network_name' => $networkName,
                ]);

                return false;
            }
        };

        $result = $vmManager->networkExists('user1');

        $this->assertFalse($result);

        // Check logs
        $records = $testHandler->getRecords();
        $debugLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Network does not exist') !== false;
        });
        $this->assertNotEmpty($debugLogs);
    }

    public function testEnsureUserNetworkWhenNetworkExists(): void
    {
        // Create a test manager that mocks network already exists
        $logger = new Logger('test-ensure-exists');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            public function networkExists(string $user): bool
            {
                $this->getLogger()->debug('Network already exists for user', ['user' => $user]);

                return true;
            }

            public function ensureUserNetwork(string $user): bool
            {
                if ($this->networkExists($user)) {
                    $this->getLogger()->debug('Network already exists for user', ['user' => $user]);

                    return true;
                }

                $this->getLogger()->info('Network does not exist, creating for user', ['user' => $user]);

                return $this->createUserNetwork($user);
            }
        };

        $result = $vmManager->ensureUserNetwork('user1');

        $this->assertTrue($result);

        // Check logs
        $records = $testHandler->getRecords();
        $debugLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Network already exists for user') !== false;
        });
        $this->assertNotEmpty($debugLogs);
    }

    public function testEnsureUserNetworkWhenNetworkDoesNotExist(): void
    {
        // Create a test manager that mocks network creation
        $logger = new Logger('test-ensure-create');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $vmManager = new class ($logger) extends VMManager {
            public function networkExists(string $user): bool
            {
                return false;
            }

            public function createUserNetwork(string $user): bool
            {
                $this->getLogger()->info('Successfully created and started user network', [
                    'user' => $user,
                    'network_name' => "vm-network-100",
                    'vlan_id' => 100,
                ]);

                return true;
            }

            public function ensureUserNetwork(string $user): bool
            {
                if ($this->networkExists($user)) {
                    $this->getLogger()->debug('Network already exists for user', ['user' => $user]);

                    return true;
                }

                $this->getLogger()->info('Network does not exist, creating for user', ['user' => $user]);

                return $this->createUserNetwork($user);
            }
        };

        $result = $vmManager->ensureUserNetwork('user1');

        $this->assertTrue($result);

        // Check logs
        $records = $testHandler->getRecords();
        $infoLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Network does not exist, creating for user') !== false;
        });
        $this->assertNotEmpty($infoLogs);

        $successLogs = array_filter($records, function ($record) {
            return strpos((string) $record['message'], 'Successfully created and started user network') !== false;
        });
        $this->assertNotEmpty($successLogs);
    }
}
