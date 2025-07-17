<?php

declare(strict_types=1);

namespace VmManagement\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VmManagement\SimpleVM;
use DateTime;

/**
 * Unit tests for SimpleVM class
 */
class SimpleVMTest extends TestCase
{
    public function testSimpleVMCanBeInstantiated(): void
    {
        $vm = new SimpleVM('test-vm', 'user1');
        
        $this->assertInstanceOf(SimpleVM::class, $vm);
        $this->assertEquals('test-vm', $vm->name);
        $this->assertEquals('user1', $vm->user);
    }

    public function testSimpleVMPropertiesCanBeSet(): void
    {
        $vm = new SimpleVM('test-vm', 'user2', 4, 4096, 40);
        
        $this->assertEquals('test-vm', $vm->name);
        $this->assertEquals('user2', $vm->user);
        $this->assertEquals(4, $vm->cpu);
        $this->assertEquals(4096, $vm->memory);
        $this->assertEquals(40, $vm->disk);
        $this->assertEquals(101, $vm->vlanId); // user2 should get VLAN 101
        $this->assertEquals('creating', $vm->status);
        $this->assertEquals('ubuntu', $vm->username);
        $this->assertEquals('', $vm->ipAddress);
        $this->assertEquals('', $vm->password);
        $this->assertInstanceOf(DateTime::class, $vm->createdAt);
    }

    public function testDefaultValuesAreSetCorrectly(): void
    {
        $vm = new SimpleVM('test-vm', 'user1');
        
        $this->assertEquals(2, $vm->cpu);
        $this->assertEquals(2048, $vm->memory);
        $this->assertEquals(20, $vm->disk);
        $this->assertEquals('creating', $vm->status);
        $this->assertEquals('ubuntu', $vm->username);
        $this->assertEquals('', $vm->ipAddress);
        $this->assertEquals('', $vm->password);
    }

    public function testVlanIdMappingForAllUsers(): void
    {
        $vm1 = new SimpleVM('test-vm1', 'user1');
        $vm2 = new SimpleVM('test-vm2', 'user2');
        $vm3 = new SimpleVM('test-vm3', 'user3');
        
        $this->assertEquals(100, $vm1->vlanId);
        $this->assertEquals(101, $vm2->vlanId);
        $this->assertEquals(102, $vm3->vlanId);
    }

    public function testVlanIdDefaultsTo100ForUnknownUser(): void
    {
        $vm = new SimpleVM('test-vm', 'unknown-user');
        
        $this->assertEquals(100, $vm->vlanId);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $vm = new SimpleVM('test-vm', 'user2', 4, 4096, 40);
        $vm->status = 'running';
        $vm->ipAddress = '192.168.101.10';
        $vm->password = 'test-password';
        
        $array = $vm->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('test-vm', $array['name']);
        $this->assertEquals('user2', $array['user']);
        $this->assertEquals(101, $array['vlan_id']);
        $this->assertEquals('running', $array['status']);
        $this->assertEquals(4, $array['cpu']);
        $this->assertEquals(4096, $array['memory']);
        $this->assertEquals(40, $array['disk']);
        $this->assertIsArray($array['ssh']);
        $this->assertEquals('192.168.101.10', $array['ssh']['ip']);
        $this->assertEquals('ubuntu', $array['ssh']['username']);
        $this->assertEquals('test-password', $array['ssh']['password']);
        $this->assertIsString($array['created_at']);
    }

    public function testPropertiesCanBeModifiedAfterInstantiation(): void
    {
        $vm = new SimpleVM('test-vm', 'user1');
        
        $vm->status = 'running';
        $vm->ipAddress = '192.168.100.10';
        $vm->password = 'generated-password';
        
        $this->assertEquals('running', $vm->status);
        $this->assertEquals('192.168.100.10', $vm->ipAddress);
        $this->assertEquals('generated-password', $vm->password);
    }
}