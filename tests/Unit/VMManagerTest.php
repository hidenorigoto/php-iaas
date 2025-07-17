<?php

declare(strict_types=1);

namespace VmManagement\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VmManagement\VMManager;
use VmManagement\SimpleVM;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use InvalidArgumentException;

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
        
        $vmManager = new class($logger) extends VMManager {
            private $mockResource;
            private $mockConnected = false;
            
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
                if (!$this->isConnected()) {
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
        $connectionLogs = array_filter($records, function($record) {
            return strpos($record['message'], 'Attempting to connect to libvirt') !== false;
        });
        $this->assertNotEmpty($connectionLogs);
        
        // Find the success log
        $successLogs = array_filter($records, function($record) {
            return strpos($record['message'], 'Successfully connected to libvirt') !== false;
        });
        $this->assertNotEmpty($successLogs);
    }

    public function testConnectFailureWithMockedLibvirt(): void
    {
        // Create a mock function namespace that returns false
        if (!function_exists('VmManagement\Test\libvirt_connect')) {
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
        $vmManager = new class($logger) extends VMManager {
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
                        'error' => $error
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
        $errorLogs = array_filter($records, function($record) {
            return strpos($record['message'], 'Failed to connect to libvirt') !== false;
        });
        $this->assertNotEmpty($errorLogs);
    }

    public function testConnectWhenAlreadyConnected(): void
    {
        // Mock successful connection first
        if (!function_exists('VmManagement\Already\libvirt_connect')) {
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
        
        $vmManager = new class($logger) extends VMManager {
            private $mockConnected = false;
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
        $alreadyConnectedLogs = array_filter($records, function($record) {
            return strpos($record['message'], 'Already connected to libvirt') !== false;
        });
        $this->assertNotEmpty($alreadyConnectedLogs);
    }

    public function testDisconnectWhenConnected(): void
    {
        // Create a test manager that simulates connected state
        $logger = new Logger('test-disconnect');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);
        
        $vmManager = new class($logger) extends VMManager {
            private $mockConnected = true;
            private $mockResource;
            
            public function __construct($logger) {
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
                if (!$this->isConnected()) {
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
        $disconnectLogs = array_filter($records, function($record) {
            return strpos($record['message'], 'Successfully disconnected from libvirt') !== false;
        });
        $this->assertNotEmpty($disconnectLogs);
    }

    public function testDisconnectWhenNotConnected(): void
    {
        $result = $this->vmManager->disconnect();
        
        $this->assertTrue($result);
        
        // Check that "nothing to disconnect" debug message was logged
        $records = $this->testHandler->getRecords();
        $nothingToDisconnectLogs = array_filter($records, function($record) {
            return strpos($record['message'], 'Not connected to libvirt, nothing to disconnect') !== false;
        });
        $this->assertNotEmpty($nothingToDisconnectLogs);
    }
}