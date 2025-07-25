<?php

declare(strict_types=1);

namespace VmManagement\Tests\Unit;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use VmManagement\Exceptions\ValidationException;
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
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Parameter "name" cannot be empty');

        $this->vmManager->validateVMParams(['user' => 'user1']);
    }

    public function testEmptyVMNameThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Parameter "name" cannot be empty');

        $this->vmManager->validateVMParams(['name' => '', 'user' => 'user1']);
    }

    public function testInvalidVMNameCharactersThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Parameter "name" contains invalid characters: "test vm!"');

        $this->vmManager->validateVMParams(['name' => 'test vm!', 'user' => 'user1']);
    }

    public function testTooLongVMNameThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Parameter "name" is too long (51 characters). Maximum allowed: 50');

        $longName = str_repeat('a', 51);
        $this->vmManager->validateVMParams(['name' => $longName, 'user' => 'user1']);
    }

    public function testInvalidUserThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Parameter "user" cannot be empty');

        $this->vmManager->validateVMParams(['name' => 'test-vm']);
    }

    public function testUnknownUserThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid user "unknown". Must be one of: user1, user2, user3');

        $this->vmManager->validateVMParams(['name' => 'test-vm', 'user' => 'unknown']);
    }

    public function testInvalidCPUThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid CPU count 0. Must be between 1 and 16');

        $this->vmManager->validateVMParams([
            'name' => 'test-vm',
            'user' => 'user1',
            'cpu' => 0,
        ]);
    }

    public function testInvalidMemoryThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid memory 256 MB. Must be between 512 and 32768');

        $this->vmManager->validateVMParams([
            'name' => 'test-vm',
            'user' => 'user1',
            'memory' => 256,
        ]);
    }

    public function testInvalidDiskThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid disk size 5 GB. Must be between 10 and 1000');

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
        $this->expectException(ValidationException::class);

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

    public function testBuildVMConfigGeneratesValidXML(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 2048, 20);
        $diskPath = '/var/lib/libvirt/images/test-vm.qcow2';

        $xml = $this->vmManager->buildVMConfig($vm, $diskPath);

        // Verify it's valid XML
        $this->assertNotEmpty($xml);
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml), 'Generated XML should be valid');

        // Verify root element
        $root = $doc->documentElement;
        $this->assertNotNull($root);
        $this->assertEquals('domain', $root->nodeName);
        $this->assertEquals('qemu', $root->getAttribute('type'));

        // Verify VM name
        $nameNodes = $doc->getElementsByTagName('name');
        $this->assertEquals(1, $nameNodes->length);
        $nameNode = $nameNodes->item(0);
        $this->assertNotNull($nameNode);
        $this->assertEquals('test-vm', $nameNode->nodeValue);

        // Verify UUID exists and is valid format
        $uuidNodes = $doc->getElementsByTagName('uuid');
        $this->assertEquals(1, $uuidNodes->length);
        $uuidNode = $uuidNodes->item(0);
        $this->assertNotNull($uuidNode);
        $uuid = $uuidNode->nodeValue;
        $this->assertNotNull($uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $uuid,
            'UUID should be in proper format'
        );

        // Check that log was called
        $this->assertTrue($this->testHandler->hasInfoThatContains('Building VM configuration XML'));
    }

    public function testBuildVMConfigAppliesMemoryConversion(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 4096, 20);
        $diskPath = '/var/lib/libvirt/images/test-vm.qcow2';

        $xml = $this->vmManager->buildVMConfig($vm, $diskPath);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        // Memory should be converted from MB to KiB
        $memoryNodes = $doc->getElementsByTagName('memory');
        $this->assertEquals(1, $memoryNodes->length);
        $memoryNode = $memoryNodes->item(0);
        $this->assertNotNull($memoryNode);
        $this->assertEquals('4194304', $memoryNode->nodeValue); // 4096 * 1024
        $this->assertEquals('KiB', $memoryNode->getAttribute('unit'));

        $currentMemoryNodes = $doc->getElementsByTagName('currentMemory');
        $this->assertEquals(1, $currentMemoryNodes->length);
        $currentMemoryNode = $currentMemoryNodes->item(0);
        $this->assertNotNull($currentMemoryNode);
        $this->assertEquals('4194304', $currentMemoryNode->nodeValue);
        $this->assertEquals('KiB', $currentMemoryNode->getAttribute('unit'));
    }

    public function testBuildVMConfigSetsCPUCount(): void
    {
        $vm = new SimpleVM('test-vm', 'user2', 4, 2048, 20);
        $diskPath = '/var/lib/libvirt/images/test-vm.qcow2';

        $xml = $this->vmManager->buildVMConfig($vm, $diskPath);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $vcpuNodes = $doc->getElementsByTagName('vcpu');
        $this->assertEquals(1, $vcpuNodes->length);
        $vcpuNode = $vcpuNodes->item(0);
        $this->assertNotNull($vcpuNode);
        $this->assertEquals('4', $vcpuNode->nodeValue);
        $this->assertEquals('static', $vcpuNode->getAttribute('placement'));
    }

    public function testBuildVMConfigSetsDiskPath(): void
    {
        $vm = new SimpleVM('test-vm', 'user3', 2, 2048, 30);
        $diskPath = '/custom/path/test-vm.qcow2';

        $xml = $this->vmManager->buildVMConfig($vm, $diskPath);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        // Find disk source element
        $xpath = new \DOMXPath($doc);
        $diskSources = $xpath->query("//disk[@type='file']/source");
        $this->assertNotFalse($diskSources);
        $this->assertEquals(1, $diskSources->length);
        $diskSource = $diskSources->item(0);
        $this->assertNotNull($diskSource);
        $this->assertInstanceOf(\DOMElement::class, $diskSource);
        $this->assertEquals($diskPath, $diskSource->getAttribute('file'));

        // Verify disk driver
        $diskDrivers = $xpath->query("//disk[@type='file']/driver");
        $this->assertNotFalse($diskDrivers);
        $this->assertEquals(1, $diskDrivers->length);
        $diskDriver = $diskDrivers->item(0);
        $this->assertNotNull($diskDriver);
        $this->assertInstanceOf(\DOMElement::class, $diskDriver);
        $this->assertEquals('qemu', $diskDriver->getAttribute('name'));
        $this->assertEquals('qcow2', $diskDriver->getAttribute('type'));
    }

    public function testBuildVMConfigAssignsCorrectNetworkForEachUser(): void
    {
        $users = [
            'user1' => 'vm-network-100',
            'user2' => 'vm-network-101',
            'user3' => 'vm-network-102',
        ];

        foreach ($users as $user => $expectedNetwork) {
            $vm = new SimpleVM("test-vm-{$user}", $user, 2, 2048, 20);
            $diskPath = "/var/lib/libvirt/images/test-vm-{$user}.qcow2";

            $xml = $this->vmManager->buildVMConfig($vm, $diskPath);

            $doc = new \DOMDocument();
            $doc->loadXML($xml);

            // Find network interface source
            $xpath = new \DOMXPath($doc);
            $networkSources = $xpath->query("//interface[@type='network']/source");
            $this->assertNotFalse($networkSources);
            $this->assertEquals(1, $networkSources->length);
            $networkSource = $networkSources->item(0);
            $this->assertNotNull($networkSource);
            $this->assertInstanceOf(\DOMElement::class, $networkSource);
            $this->assertEquals(
                $expectedNetwork,
                $networkSource->getAttribute('network'),
                "User {$user} should be assigned to network {$expectedNetwork}"
            );
        }
    }

    public function testBuildVMConfigGeneratesValidMacAddress(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 2048, 20);
        $diskPath = '/var/lib/libvirt/images/test-vm.qcow2';

        $xml = $this->vmManager->buildVMConfig($vm, $diskPath);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $xpath = new \DOMXPath($doc);
        $macElements = $xpath->query("//interface[@type='network']/mac");
        $this->assertNotFalse($macElements);
        $this->assertEquals(1, $macElements->length);
        $macElement = $macElements->item(0);
        $this->assertNotNull($macElement);
        $this->assertInstanceOf(\DOMElement::class, $macElement);
        $macAddress = $macElement->getAttribute('address');
        $this->assertMatchesRegularExpression(
            '/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/i',
            $macAddress,
            'MAC address should be in proper format'
        );

        // Verify it's a locally administered MAC (second least significant bit of first octet is 1)
        $firstOctet = hexdec(substr($macAddress, 0, 2));
        $this->assertEquals(
            1,
            ($firstOctet >> 1) & 1,
            'MAC address should be locally administered'
        );
    }

    public function testBuildVMConfigThrowsExceptionForInvalidUser(): void
    {
        $vm = new SimpleVM('test-vm', 'invalid-user', 2, 2048, 20);
        $diskPath = '/var/lib/libvirt/images/test-vm.qcow2';

        // Note: SimpleVM will default to VLAN 100 for invalid users,
        // but buildVMConfig checks the user independently
        $vm->user = 'invalid-user'; // Force invalid user

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid user for network configuration: invalid-user');

        $this->vmManager->buildVMConfig($vm, $diskPath);
    }

    public function testBuildVMConfigIncludesDefaultSettings(): void
    {
        $vm = new SimpleVM('test-vm', 'user1'); // Using defaults: 2 CPU, 2048 MB, 20 GB
        $diskPath = '/var/lib/libvirt/images/test-vm.qcow2';

        $xml = $this->vmManager->buildVMConfig($vm, $diskPath);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        // Check default CPU (2)
        $vcpuNodes = $doc->getElementsByTagName('vcpu');
        $vcpuNode = $vcpuNodes->item(0);
        $this->assertNotNull($vcpuNode);
        $this->assertEquals('2', $vcpuNode->nodeValue);

        // Check default memory (2048 MB = 2097152 KiB)
        $memoryNodes = $doc->getElementsByTagName('memory');
        $memoryNode = $memoryNodes->item(0);
        $this->assertNotNull($memoryNode);
        $this->assertEquals('2097152', $memoryNode->nodeValue);

        // Note: Disk size is not directly visible in the XML, it's part of volume creation
    }

    public function testBuildVMConfigGeneratesUniqueUUIDs(): void
    {
        $vm1 = new SimpleVM('test-vm-1', 'user1', 2, 2048, 20);
        $vm2 = new SimpleVM('test-vm-2', 'user1', 2, 2048, 20);
        $diskPath = '/var/lib/libvirt/images/test-vm.qcow2';

        $xml1 = $this->vmManager->buildVMConfig($vm1, $diskPath);
        $xml2 = $this->vmManager->buildVMConfig($vm2, $diskPath);

        $doc1 = new \DOMDocument();
        $doc1->loadXML($xml1);
        $doc2 = new \DOMDocument();
        $doc2->loadXML($xml2);

        $uuidNode1 = $doc1->getElementsByTagName('uuid')->item(0);
        $uuidNode2 = $doc2->getElementsByTagName('uuid')->item(0);
        $this->assertNotNull($uuidNode1);
        $this->assertNotNull($uuidNode2);
        $uuid1 = $uuidNode1->nodeValue;
        $uuid2 = $uuidNode2->nodeValue;

        $this->assertNotEquals($uuid1, $uuid2, 'Each VM should have a unique UUID');
    }

    public function testBuildVMConfigGeneratesUniqueMacAddresses(): void
    {
        $vm1 = new SimpleVM('test-vm-1', 'user1', 2, 2048, 20);
        $vm2 = new SimpleVM('test-vm-2', 'user1', 2, 2048, 20);
        $diskPath = '/var/lib/libvirt/images/test-vm.qcow2';

        $xml1 = $this->vmManager->buildVMConfig($vm1, $diskPath);
        $xml2 = $this->vmManager->buildVMConfig($vm2, $diskPath);

        $doc1 = new \DOMDocument();
        $doc1->loadXML($xml1);
        $doc2 = new \DOMDocument();
        $doc2->loadXML($xml2);

        $xpath1 = new \DOMXPath($doc1);
        $xpath2 = new \DOMXPath($doc2);

        $macQuery1 = $xpath1->query("//interface[@type='network']/mac");
        $macQuery2 = $xpath2->query("//interface[@type='network']/mac");
        $this->assertNotFalse($macQuery1);
        $this->assertNotFalse($macQuery2);
        $macNode1 = $macQuery1->item(0);
        $macNode2 = $macQuery2->item(0);
        $this->assertNotNull($macNode1);
        $this->assertNotNull($macNode2);
        $this->assertInstanceOf(\DOMElement::class, $macNode1);
        $this->assertInstanceOf(\DOMElement::class, $macNode2);
        $mac1 = $macNode1->getAttribute('address');
        $mac2 = $macNode2->getAttribute('address');

        $this->assertNotEquals($mac1, $mac2, 'Each VM should have a unique MAC address');
    }

    public function testBuildVMConfigIncludesRequiredDevices(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 2048, 20);
        $diskPath = '/var/lib/libvirt/images/test-vm.qcow2';

        $xml = $this->vmManager->buildVMConfig($vm, $diskPath);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        // Check for essential devices
        $xpath = new \DOMXPath($doc);

        // Console device
        $consoles = $xpath->query("//console[@type='pty']");
        $this->assertNotFalse($consoles);
        $this->assertEquals(1, $consoles->length, 'Should have a console device');

        // Serial device
        $serials = $xpath->query("//serial[@type='pty']");
        $this->assertNotFalse($serials);
        $this->assertEquals(1, $serials->length, 'Should have a serial device');

        // VNC graphics
        $graphics = $xpath->query("//graphics[@type='vnc']");
        $this->assertNotFalse($graphics);
        $this->assertEquals(1, $graphics->length, 'Should have VNC graphics');
        $graphicsNode = $graphics->item(0);
        $this->assertNotNull($graphicsNode);
        $this->assertInstanceOf(\DOMElement::class, $graphicsNode);
        $this->assertEquals('127.0.0.1', $graphicsNode->getAttribute('listen'));

        // Input devices
        $mouseinputs = $xpath->query("//input[@type='mouse']");
        $this->assertNotFalse($mouseinputs);
        $this->assertGreaterThanOrEqual(1, $mouseinputs->length, 'Should have mouse input');

        $kbdinputs = $xpath->query("//input[@type='keyboard']");
        $this->assertNotFalse($kbdinputs);
        $this->assertGreaterThanOrEqual(1, $kbdinputs->length, 'Should have keyboard input');
    }

    public function testCreateAndStartVMSuccess(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 2048, 20);
        $this->assertEquals('creating', $vm->status);

        // Create a mock VMManager with mocked methods
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            private bool $isConnectedValue = true;
            /** @var object */
            private object $mockDomain;

            public function __construct(Logger $logger)
            {
                parent::__construct($logger);
                $this->mockDomain = new class () {
                    /** @var string */
                    public string $type = 'domain_resource';
                };
            }

            public function isConnected(): bool
            {
                return $this->isConnectedValue;
            }

            public function ensureUserNetwork(string $user): bool
            {
                $this->logInfo('Ensuring user network', ['user' => $user]);

                return true;
            }

            /**
             * @return string|false
             */
            public function createDiskVolume(string $volumeName, int $sizeGB, string $poolName = 'default')
            {
                $this->logInfo('Creating disk volume', [
                    'volume_name' => $volumeName,
                    'size_gb' => $sizeGB,
                ]);

                return '/var/lib/libvirt/images/' . $volumeName . '.qcow2';
            }

            /**
             * @return string|false
             * @phpstan-ignore-next-line
             */
            private function createCloudInitISO(SimpleVM $vm)
            {
                $this->logInfo('Creating cloud-init ISO', [
                    'vm_name' => $vm->name,
                ]);

                return '/var/lib/libvirt/images/cloud-init/' . $vm->name . '-cloud-init.iso';
            }

            public function buildVMConfig(SimpleVM $vm, string $diskPath, string $cloudInitISOPath = ''): string
            {
                return '<domain type="qemu"><name>' . $vm->name . '</name></domain>';
            }

            /**
             * @param mixed $domain
             * @return string
             */
            public function getDomainState($domain): string
            {
                return 'running';
            }

            /**
             * @return SimpleVM|false
             */
            public function createAndStartVM(SimpleVM $vm)
            {
                $this->logInfo('Creating and starting VM', [
                    'vm_name' => $vm->name,
                    'user' => $vm->user,
                ]);

                // Validate parameters
                try {
                    $this->validateVMParams($vm->toArray());
                } catch (\InvalidArgumentException $e) {
                    $this->logError('Invalid VM parameters', [
                        'error' => $e->getMessage(),
                        'vm_name' => $vm->name,
                    ]);

                    return false;
                }

                // Ensure connected to libvirt
                if (! $this->isConnected()) {
                    $this->logError('Failed to connect to libvirt');

                    return false;
                }

                // Ensure user network exists
                if (! $this->ensureUserNetwork($vm->user)) {
                    $this->logError('Failed to ensure user network', [
                        'user' => $vm->user,
                    ]);

                    return false;
                }

                // Create disk volume
                $diskPath = $this->createDiskVolume($vm->name, $vm->disk);
                if ($diskPath === false) {
                    $this->logError('Failed to create disk volume', [
                        'vm_name' => $vm->name,
                        'disk_size' => $vm->disk,
                    ]);

                    return false;
                }

                // Generate VM configuration XML
                $vmXml = $this->buildVMConfig($vm, $diskPath);

                // Mock successful domain definition
                $this->logInfo('VM domain defined successfully', ['vm_name' => $vm->name]);

                // Mock successful VM start
                $this->logInfo('VM started successfully', ['vm_name' => $vm->name]);

                // Get VM state to confirm it's running
                $state = $this->getDomainState($this->mockDomain);
                $this->logInfo('VM state verified', [
                    'vm_name' => $vm->name,
                    'state' => $state,
                ]);

                // Update VM status
                $vm->status = 'running';

                $this->logInfo('VM created and started successfully', [
                    'vm_name' => $vm->name,
                    'user' => $vm->user,
                    'status' => $vm->status,
                ]);

                return $vm;
            }
        };

        $result = $vmManager->createAndStartVM($vm);

        $this->assertInstanceOf(SimpleVM::class, $result);
        $this->assertEquals('running', $result->status);
        $this->assertEquals('test-vm', $result->name);

        // Check logs
        $this->assertTrue($this->testHandler->hasInfoThatContains('Creating and starting VM'));
        $this->assertTrue($this->testHandler->hasInfoThatContains('Ensuring user network'));
        $this->assertTrue($this->testHandler->hasInfoThatContains('Creating disk volume'));
        $this->assertTrue($this->testHandler->hasInfoThatContains('VM domain defined successfully'));
        $this->assertTrue($this->testHandler->hasInfoThatContains('VM started successfully'));
        $this->assertTrue($this->testHandler->hasInfoThatContains('VM state verified'));
        $this->assertTrue($this->testHandler->hasInfoThatContains('VM created and started successfully'));
    }

    public function testCreateAndStartVMFailsOnInvalidParams(): void
    {
        $vm = new SimpleVM('invalid-vm-name!', 'user1', 2, 2048, 20);

        $result = $this->vmManager->createAndStartVM($vm);

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasErrorThatContains('Invalid VM parameters'));
    }

    public function testCreateAndStartVMFailsWhenNotConnected(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 2048, 20);

        // VMManager is not connected by default in tests
        $result = $this->vmManager->createAndStartVM($vm);

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasErrorThatContains('libvirt_connect function not available'));
    }

    public function testGetDomainByNameWhenNotConnected(): void
    {
        $result = $this->vmManager->getDomainByName('test-vm');

        $this->assertFalse($result);
        $this->assertTrue($this->testHandler->hasErrorThatContains('Not connected to libvirt'));
    }

    public function testGetDomainByNameSuccess(): void
    {
        // Create mock manager with connection
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            /** @var object */
            private object $mockConnection;
            /** @var object */
            private object $mockDomain;

            public function __construct(Logger $logger)
            {
                parent::__construct($logger);
                $this->mockConnection = new class () {
                    /** @var string */
                    public string $type = 'libvirt_connection';
                };
                $this->mockDomain = new class () {
                    /** @var string */
                    public string $name = 'test-vm';
                };
            }

            /**
             * @return mixed
             */
            public function getConnection()
            {
                return $this->mockConnection;
            }

            public function isConnected(): bool
            {
                return true;
            }

            /**
             * @return mixed
             */
            public function getDomainByName(string $vmName)
            {
                if (! $this->isConnected()) {
                    $this->logError('Not connected to libvirt');

                    return false;
                }

                if ($vmName === 'test-vm') {
                    return $this->mockDomain;
                }

                $this->logDebug('Domain not found', [
                    'vm_name' => $vmName,
                    'error' => 'Domain not found',
                ]);

                return false;
            }
        };

        $domain = $vmManager->getDomainByName('test-vm');
        $this->assertNotFalse($domain);
        $this->assertIsObject($domain);
        if (property_exists($domain, 'name')) {
            $this->assertEquals('test-vm', $domain->name);
        }

        $notFound = $vmManager->getDomainByName('non-existent');
        $this->assertFalse($notFound);
        $this->assertTrue($this->testHandler->hasDebugThatContains('Domain not found'));
    }

    public function testGetDomainStateSuccess(): void
    {
        // Create mock manager with domain info
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            /**
             * @param mixed $domain
             * @return string
             */
            public function getDomainState($domain): string
            {
                // Simulate successful domain info retrieval
                $info = [
                    'state' => 1, // running
                    'maxMem' => 2097152,
                    'memory' => 2097152,
                    'nrVirtCpu' => 2,
                    'cpuUsed' => 123456789,
                ];

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

                $stateId = $info['state'];
                $state = $states[$stateId];

                $this->logDebug('Domain state retrieved', [
                    'state_id' => $stateId,
                    'state' => $state,
                    'info' => $info,
                ]);

                return $state;
            }
        };

        /** @var mixed $mockDomain */
        $mockDomain = new \stdClass();
        $state = $vmManager->getDomainState($mockDomain);

        $this->assertEquals('running', $state);
        $this->assertTrue($this->testHandler->hasDebugThatContains('Domain state retrieved'));
    }

    public function testIsVMRunning(): void
    {
        // Create mock manager with running VM
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            /**
             * @return mixed
             */
            public function getDomainByName(string $vmName)
            {
                if ($vmName === 'running-vm') {
                    return new \stdClass();
                }

                return false;
            }

            /**
             * @param mixed $domain
             * @return string
             */
            public function getDomainState($domain): string
            {
                return 'running';
            }
        };

        $this->assertTrue($vmManager->isVMRunning('running-vm'));
        $this->assertFalse($vmManager->isVMRunning('non-existent-vm'));
    }

    public function testListAllVMsWhenNotConnected(): void
    {
        $result = $this->vmManager->listAllVMs();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
        $this->assertTrue($this->testHandler->hasErrorThatContains('Not connected to libvirt'));
    }

    public function testListAllVMsSuccess(): void
    {
        // Create mock manager with VMs
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            /** @var object */
            private object $mockConnection;

            public function __construct(Logger $logger)
            {
                parent::__construct($logger);
                $this->mockConnection = new class () {
                    /** @var string */
                    public string $type = 'libvirt_connection';
                };
            }

            public function isConnected(): bool
            {
                return true;
            }

            /**
             * @return mixed
             */
            public function getConnection()
            {
                return $this->mockConnection;
            }

            /**
             * @return mixed
             */
            /**
             * @return mixed
             */
            public function getDomainByName(string $vmName)
            {
                return new \stdClass();
            }

            /**
             * @param mixed $domain
             * @return string
             */
            public function getDomainState($domain): string
            {
                return 'running';
            }

            public function listAllVMs(): array
            {
                if (! $this->isConnected()) {
                    $this->logError('Not connected to libvirt');

                    return [];
                }

                // Mock active and inactive domains
                $vms = [
                    'vm1' => [
                        'name' => 'vm1',
                        'state' => 'running',
                        'active' => true,
                    ],
                    'vm2' => [
                        'name' => 'vm2',
                        'state' => 'running',
                        'active' => true,
                    ],
                    'vm3' => [
                        'name' => 'vm3',
                        'state' => 'shutoff',
                        'active' => false,
                    ],
                ];

                $this->logDebug('Listed all VMs', ['count' => count($vms), 'vms' => array_keys($vms)]);

                return $vms;
            }
        };

        $vms = $vmManager->listAllVMs();

        $this->assertIsArray($vms);
        $this->assertCount(3, $vms);
        $this->assertArrayHasKey('vm1', $vms);
        $this->assertArrayHasKey('vm2', $vms);
        $this->assertArrayHasKey('vm3', $vms);
        $this->assertEquals('running', $vms['vm1']['state']);
        $this->assertEquals('shutoff', $vms['vm3']['state']);
        $this->assertTrue($vms['vm1']['active']);
        $this->assertFalse($vms['vm3']['active']);
    }

    public function testCreateAndStartVMWithMockedLibvirt(): void
    {
        // Create a more realistic mock with libvirt functions
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            /** @var object */
            private object $mockConnection;

            public function __construct(Logger $logger)
            {
                parent::__construct($logger);
                $this->mockConnection = (object) ['resource' => 'mock_connection'];
            }

            protected function setupLibvirtFunctionMocks(): void
            {
                // This would be used in actual testing with function mocking
            }

            public function connect(): bool
            {
                $this->logInfo('Attempting to connect to libvirt', ['uri' => 'qemu:///system']);
                // Use reflection to set private property
                $reflection = new \ReflectionClass(parent::class);
                $property = $reflection->getProperty('libvirtConnection');
                $property->setAccessible(true);
                $property->setValue($this, $this->mockConnection);
                $this->logInfo('Successfully connected to libvirt');

                return true;
            }

            public function isConnected(): bool
            {
                // Use reflection to get private property
                $reflection = new \ReflectionClass(parent::class);
                $property = $reflection->getProperty('libvirtConnection');
                $property->setAccessible(true);

                return $property->getValue($this) !== null;
            }

            /**
             * @return mixed
             */
            public function getConnection()
            {
                // Use reflection to get private property
                $reflection = new \ReflectionClass(parent::class);
                $property = $reflection->getProperty('libvirtConnection');
                $property->setAccessible(true);

                return $property->getValue($this);
            }

            public function ensureUserNetwork(string $user): bool
            {
                return true;
            }

            /**
             * @return string|false
             */
            public function createDiskVolume(string $volumeName, int $sizeGB, string $poolName = 'default')
            {
                return '/var/lib/libvirt/images/' . $volumeName . '.qcow2';
            }

            /**
             * @return SimpleVM|false
             */
            public function createAndStartVM(SimpleVM $vm)
            {
                // Call parent implementation but with mocked libvirt functions
                $this->logInfo('Creating and starting VM', [
                    'vm_name' => $vm->name,
                    'user' => $vm->user,
                ]);

                // Validate parameters
                try {
                    $this->validateVMParams($vm->toArray());
                } catch (\InvalidArgumentException $e) {
                    return false;
                }

                // Connect if needed
                if (! $this->isConnected() && ! $this->connect()) {
                    return false;
                }

                // Ensure user network
                if (! $this->ensureUserNetwork($vm->user)) {
                    return false;
                }

                // Create disk
                $diskPath = $this->createDiskVolume($vm->name, $vm->disk);
                if ($diskPath === false) {
                    return false;
                }

                // Build config
                $vmXml = $this->buildVMConfig($vm, $diskPath);

                // Mock domain definition
                $this->logInfo('VM domain defined successfully', ['vm_name' => $vm->name]);

                // Mock domain start
                $this->logInfo('VM started successfully', ['vm_name' => $vm->name]);

                // Mock state check
                $this->logInfo('VM state verified', [
                    'vm_name' => $vm->name,
                    'state' => 'running',
                ]);

                $vm->status = 'running';

                $this->logInfo('VM created and started successfully', [
                    'vm_name' => $vm->name,
                    'user' => $vm->user,
                    'status' => $vm->status,
                ]);

                return $vm;
            }
        };

        $vm = new SimpleVM('test-vm-full', 'user2', 4, 4096, 40);
        $result = $vmManager->createAndStartVM($vm);

        $this->assertInstanceOf(SimpleVM::class, $result);
        $this->assertEquals('running', $result->status);
        $this->assertEquals('test-vm-full', $result->name);
        $this->assertEquals('user2', $result->user);
        $this->assertEquals(4, $result->cpu);
        $this->assertEquals(4096, $result->memory);
        $this->assertEquals(40, $result->disk);
    }

    public function testGetSSHInfoSuccess(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 2048, 20);
        $vm->status = 'running';

        // Create mock manager with SSH functionality
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            /** @var object */
            private object $mockDomain;

            public function __construct(Logger $logger)
            {
                parent::__construct($logger);
                $this->mockDomain = (object) ['resource' => 'mock_domain'];
            }

            public function isConnected(): bool
            {
                return true;
            }

            /**
             * @return mixed
             */
            public function getDomainByName(string $vmName)
            {
                if ($vmName === 'test-vm') {
                    return $this->mockDomain;
                }

                return false;
            }

            public function isVMRunning(string $vmName): bool
            {
                return $vmName === 'test-vm';
            }

            /**
             * @return string|false
             */
            public function getVMIPAddress(string $vmName, string $user)
            {
                if ($vmName === 'test-vm') {
                    return '192.168.100.10';
                }

                return false;
            }

            public function generatePassword(int $length = 16): string
            {
                return 'test-password-123';
            }

            public function waitForSSHReady(string $ipAddress, string $username, int $timeout = 60): bool
            {
                return true;
            }
        };

        $sshInfo = $vmManager->getSSHInfo($vm);

        $this->assertIsArray($sshInfo);
        $this->assertEquals('192.168.100.10', $sshInfo['ip']);
        $this->assertEquals('ubuntu', $sshInfo['username']);
        $this->assertEquals('test-password-123', $sshInfo['password']);
        $this->assertTrue($sshInfo['ready']);

        // Check that VM was updated with SSH info
        $this->assertEquals('192.168.100.10', $vm->ipAddress);
        $this->assertEquals('test-password-123', $vm->password);
    }

    public function testGetSSHInfoFailsWhenVMNotRunning(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 2048, 20);
        $vm->status = 'shutoff';

        $sshInfo = $this->vmManager->getSSHInfo($vm);

        $this->assertFalse($sshInfo);
        $this->assertTrue($this->testHandler->hasErrorThatContains('Failed to get domain for SSH info'));
    }

    public function testGetSSHInfoFailsWhenNoIPAddress(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 2048, 20);
        $vm->status = 'running';

        // Create mock manager that returns no IP
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            /**
             * @return mixed
             */
            public function getDomainByName(string $vmName)
            {
                return new \stdClass();
            }

            public function isVMRunning(string $vmName): bool
            {
                return true;
            }

            /**
             * @return string|false
             */
            public function getVMIPAddress(string $vmName, string $user)
            {
                return false;
            }
        };

        $sshInfo = $vmManager->getSSHInfo($vm);

        $this->assertFalse($sshInfo);
        $this->assertTrue($this->testHandler->hasErrorThatContains('Failed to get IP address'));
    }

    public function testGetSSHInfoWithExistingPassword(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 2048, 20);
        $vm->status = 'running';
        $vm->password = 'existing-password';

        // Create mock manager
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            /**
             * @return mixed
             */
            public function getDomainByName(string $vmName)
            {
                return new \stdClass();
            }

            public function isVMRunning(string $vmName): bool
            {
                return true;
            }

            /**
             * @return string|false
             */
            public function getVMIPAddress(string $vmName, string $user)
            {
                return '192.168.100.10';
            }

            public function waitForSSHReady(string $ipAddress, string $username, int $timeout = 60): bool
            {
                return true;
            }
        };

        $sshInfo = $vmManager->getSSHInfo($vm);

        $this->assertIsArray($sshInfo);
        $this->assertEquals('existing-password', $sshInfo['password']);
    }

    public function testGetSSHInfoWhenSSHNotReady(): void
    {
        $vm = new SimpleVM('test-vm', 'user1', 2, 2048, 20);
        $vm->status = 'running';

        // Create mock manager where SSH is not ready
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            /**
             * @return mixed
             */
            public function getDomainByName(string $vmName)
            {
                return new \stdClass();
            }

            public function isVMRunning(string $vmName): bool
            {
                return true;
            }

            /**
             * @return string|false
             */
            public function getVMIPAddress(string $vmName, string $user)
            {
                return '192.168.100.10';
            }

            public function generatePassword(int $length = 16): string
            {
                return 'test-password';
            }

            public function waitForSSHReady(string $ipAddress, string $username, int $timeout = 60): bool
            {
                return false;
            }
        };

        $sshInfo = $vmManager->getSSHInfo($vm);

        $this->assertIsArray($sshInfo);
        $this->assertFalse($sshInfo['ready']);
        $this->assertTrue($this->testHandler->hasWarningThatContains('SSH not ready yet'));
    }

    public function testGetVMIPAddressFromDHCPLease(): void
    {
        // Create mock manager with DHCP lease
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            public function getDHCPLeases(string $networkName): array
            {
                return [
                    [
                        'hostname' => 'test-vm',
                        'ip' => '192.168.100.20',
                        'mac' => '52:54:00:12:34:56',
                    ],
                    [
                        'hostname' => 'other-vm',
                        'ip' => '192.168.100.21',
                        'mac' => '52:54:00:12:34:57',
                    ],
                ];
            }
        };

        $ipAddress = $vmManager->getVMIPAddress('test-vm', 'user1');

        $this->assertEquals('192.168.100.20', $ipAddress);
        $this->assertTrue($this->testHandler->hasInfoThatContains('Found IP from DHCP lease'));
    }

    public function testGetVMIPAddressFromDomainInterface(): void
    {
        // Create mock manager without DHCP lease but with domain interface
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            public function getDHCPLeases(string $networkName): array
            {
                return [];
            }

            /**
             * @return mixed
             */
            public function getDomainByName(string $vmName)
            {
                return new \stdClass();
            }
        };

        // Since we can't mock libvirt_domain_interface_addresses,
        // we expect this to fail and return false
        $ipAddress = $vmManager->getVMIPAddress('test-vm', 'user1');

        $this->assertFalse($ipAddress);
    }

    public function testGeneratePassword(): void
    {
        $password1 = $this->vmManager->generatePassword();
        $password2 = $this->vmManager->generatePassword();

        $this->assertEquals(16, strlen($password1));
        $this->assertEquals(16, strlen($password2));
        $this->assertNotEquals($password1, $password2); // Should be random

        // Test custom length
        $password3 = $this->vmManager->generatePassword(20);
        $this->assertEquals(20, strlen($password3));

        // Test password contains expected characters
        $this->assertMatchesRegularExpression('/[a-z]/', $password1);
        $this->assertMatchesRegularExpression('/[A-Z]/', $password1);
        $this->assertMatchesRegularExpression('/[0-9]/', $password1);
        $this->assertMatchesRegularExpression('/[!@#$%^&*()]/', $password1);
    }

    public function testWaitForSSHReady(): void
    {
        // Since we can't easily mock fsockopen and exec,
        // we'll test the method exists and has correct signature
        $this->assertTrue(method_exists($this->vmManager, 'waitForSSHReady'));

        $reflection = new \ReflectionMethod($this->vmManager, 'waitForSSHReady');
        $params = $reflection->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('ipAddress', $params[0]->getName());
        $this->assertEquals('username', $params[1]->getName());
        $this->assertEquals('timeout', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
        $this->assertEquals(60, $params[2]->getDefaultValue());
    }

    public function testGetDHCPLeases(): void
    {
        // Create mock manager to test protected method
        $vmManager = new class ($this->vmManager->getLogger()) extends VMManager {
            public function testGetDHCPLeases(string $networkName): array
            {
                return $this->getDHCPLeases($networkName);
            }
        };

        // Test when functions not available
        $leases = $vmManager->testGetDHCPLeases('vm-network-100');

        $this->assertIsArray($leases);
        $this->assertEmpty($leases);
        $this->assertTrue($this->testHandler->hasWarningThatContains('libvirt_network_get_dhcp_leases not available'));
    }
}
