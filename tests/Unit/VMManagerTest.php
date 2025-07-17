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
}