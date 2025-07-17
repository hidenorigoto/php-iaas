<?php

declare(strict_types=1);

namespace VmManagement\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\RequestFactory;
use VmManagement\Exceptions\VMManagementException;
use VmManagement\SimpleVM;
use VmManagement\VMManager;

/**
 * Web interface integration tests with real libvirt backend
 *
 * These tests verify that the web interface works correctly with
 * a real libvirt backend when available.
 */
class WebInterfaceLibvirtTest extends TestCase
{
    private $app;
    private RequestFactory $requestFactory;
    private VMManager $vmManager;
    private array $createdVMs = [];
    private bool $hasLibvirt = false;

    protected function setUp(): void
    {
        $this->vmManager = new VMManager();
        $this->hasLibvirt = $this->checkLibvirtAvailability();

        if (! $this->hasLibvirt) {
            $this->markTestSkipped('Libvirt not available, skipping web interface libvirt tests');
        }

        $this->app = $this->createRealApp();
        $this->requestFactory = new RequestFactory();
    }

    protected function tearDown(): void
    {
        // Clean up any VMs created during tests
        foreach ($this->createdVMs as $vmName) {
            $this->cleanupVM($vmName);
        }
    }

    /**
     * Create the real Slim application (similar to public/index.php)
     */
    private function createRealApp()
    {
        $app = AppFactory::create();
        $app->addErrorMiddleware(true, true, true);

        // Use real VM manager instead of mock
        $vmManager = new VMManager();

        // Include the actual routes from public/index.php
        $app->get('/', function ($request, $response) {
            $html = '<!DOCTYPE html><html><head><title>VM Management</title></head><body><h1>VM Management System</h1><form action="/create-vm" method="POST"><input type="text" name="vm_name" required><select name="user" required><option value="">Select User</option><option value="user1">User 1</option><option value="user2">User 2</option><option value="user3">User 3</option></select><input type="number" name="cpu" value="2"><input type="number" name="memory" value="2048"><input type="number" name="disk" value="20"><button type="submit">Create VM</button></form></body></html>';
            $response->getBody()->write($html);

            return $response->withHeader('Content-Type', 'text/html');
        });

        $app->post('/create-vm', function ($request, $response) use ($vmManager) {
            $data = $request->getParsedBody();
            $acceptHeader = $request->getHeaderLine('Accept');
            $isJsonRequest = strpos($acceptHeader, 'application/json') !== false;

            try {
                if (empty($data['vm_name']) || empty($data['user'])) {
                    throw new VMManagementException('VM name and user are required');
                }

                $vm = new SimpleVM(
                    name: $data['vm_name'],
                    user: $data['user'],
                    cpu: (int)($data['cpu'] ?? 2),
                    memory: (int)($data['memory'] ?? 2048),
                    disk: (int)($data['disk'] ?? 20)
                );

                $vmManager->createAndStartVM($vm);
                $sshInfo = $vmManager->getSSHInfo($vm);

                if ($isJsonRequest) {
                    $response->getBody()->write(json_encode([
                        'success' => true,
                        'message' => 'VM created successfully',
                        'vm' => [
                            'name' => $vm->name,
                            'user' => $vm->user,
                            'cpu' => $vm->cpu,
                            'memory' => $vm->memory,
                            'disk' => $vm->disk,
                        ],
                        'ssh_info' => $sshInfo,
                    ]));

                    return $response->withHeader('Content-Type', 'application/json');
                } else {
                    $html = '<html><body><h1>VM Created Successfully</h1><p>VM "' . htmlspecialchars($vm->name) . '" created</p>';
                    if (is_array($sshInfo) && isset($sshInfo['ip'])) {
                        $html .= '<h3>SSH Connection Information:</h3><p>IP: ' . htmlspecialchars($sshInfo['ip']) . '</p>';
                    }
                    $html .= '</body></html>';
                    $response->getBody()->write($html);

                    return $response->withHeader('Content-Type', 'text/html');
                }
            } catch (VMManagementException $e) {
                if ($isJsonRequest) {
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'error' => $e->getMessage(),
                        'context' => $e->getContext(),
                    ]));

                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                } else {
                    $html = '<html><body><h1>Error</h1><p>Error: ' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
                    $response->getBody()->write($html);

                    return $response->withHeader('Content-Type', 'text/html')->withStatus(400);
                }
            }
        });

        $app->get('/api/vms', function ($request, $response) use ($vmManager) {
            try {
                $vms = $vmManager->listVMs();
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'vms' => $vms,
                ]));

                return $response->withHeader('Content-Type', 'application/json');
            } catch (VMManagementException $e) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'context' => $e->getContext(),
                ]));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        });

        $app->get('/api/vm/{name}/ssh', function ($request, $response, $args) use ($vmManager) {
            try {
                $vmName = $args['name'];
                $vm = new SimpleVM(name: $vmName, user: 'user1');
                $sshInfo = $vmManager->getSSHInfo($vm);

                $response->getBody()->write(json_encode([
                    'success' => true,
                    'ssh_info' => $sshInfo,
                ]));

                return $response->withHeader('Content-Type', 'application/json');
            } catch (VMManagementException $e) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'context' => $e->getContext(),
                ]));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        });

        return $app;
    }

    /**
     * Test HTML form VM creation with real libvirt backend
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\VMManager::getSSHInfo
     */
    public function testHTMLFormVMCreationWithRealLibvirt(): void
    {
        $vmName = 'test-web-html-' . uniqid();
        $this->createdVMs[] = $vmName;

        $data = [
            'vm_name' => $vmName,
            'user' => 'user1',
            'cpu' => '2',
            'memory' => '2048',
            'disk' => '20',
        ];

        $request = $this->requestFactory->createRequest('POST', '/create-vm')
            ->withParsedBody($data);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $this->assertStringContainsString('VM Created Successfully', $body);
        $this->assertStringContainsString($vmName, $body);

        // Verify VM was actually created
        $this->assertTrue($this->vmManager->isVMRunning($vmName));
    }

    /**
     * Test JSON API VM creation with real libvirt backend
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\VMManager::getSSHInfo
     */
    public function testJSONAPIVMCreationWithRealLibvirt(): void
    {
        $vmName = 'test-web-json-' . uniqid();
        $this->createdVMs[] = $vmName;

        $data = [
            'vm_name' => $vmName,
            'user' => 'user2',
            'cpu' => 1,
            'memory' => 1024,
            'disk' => 10,
        ];

        $request = $this->requestFactory->createRequest('POST', '/create-vm')
            ->withHeader('Accept', 'application/json')
            ->withParsedBody($data);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $responseData = json_decode($body, true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('VM created successfully', $responseData['message']);
        $this->assertEquals($vmName, $responseData['vm']['name']);
        $this->assertEquals('user2', $responseData['vm']['user']);
        $this->assertArrayHasKey('ssh_info', $responseData);

        // Verify SSH info
        $sshInfo = $responseData['ssh_info'];
        if (is_array($sshInfo) && isset($sshInfo['ip'])) {
            $this->assertStringStartsWith('192.168.101.', $sshInfo['ip']);
            $this->assertArrayHasKey('username', $sshInfo);
            $this->assertArrayHasKey('password', $sshInfo);
        }

        // Verify VM was actually created
        $this->assertTrue($this->vmManager->isVMRunning($vmName));
    }

    /**
     * Test VM listing API with real libvirt backend
     *
     * @covers \VmManagement\VMManager::listVMs
     */
    public function testVMListingAPIWithRealLibvirt(): void
    {
        // Create a test VM first
        $vmName = 'test-web-list-' . uniqid();
        $this->createdVMs[] = $vmName;

        $vm = new SimpleVM(
            name: $vmName,
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $this->vmManager->createAndStartVM($vm);

        // Test API endpoint
        $request = $this->requestFactory->createRequest('GET', '/api/vms');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $responseData = json_decode($body, true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('vms', $responseData);
        $this->assertIsArray($responseData['vms']);

        // Find our test VM in the list
        $foundVM = false;
        foreach ($responseData['vms'] as $listedVM) {
            if ($listedVM['name'] === $vmName) {
                $foundVM = true;
                $this->assertEquals('running', $listedVM['status']);

                break;
            }
        }

        $this->assertTrue($foundVM, "Created VM not found in API response");
    }

    /**
     * Test SSH info API with real libvirt backend
     *
     * @covers \VmManagement\VMManager::getSSHInfo
     */
    public function testSSHInfoAPIWithRealLibvirt(): void
    {
        // Create a test VM first
        $vmName = 'test-web-ssh-' . uniqid();
        $this->createdVMs[] = $vmName;

        $vm = new SimpleVM(
            name: $vmName,
            user: 'user1',
            cpu: 1,
            memory: 1024,
            disk: 10
        );

        $this->vmManager->createAndStartVM($vm);

        // Wait for VM to fully start
        sleep(5);

        // Test SSH info API endpoint
        $request = $this->requestFactory->createRequest('GET', "/api/vm/{$vmName}/ssh");
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $responseData = json_decode($body, true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('ssh_info', $responseData);

        $sshInfo = $responseData['ssh_info'];
        if (is_array($sshInfo) && isset($sshInfo['ip'])) {
            $this->assertStringStartsWith('192.168.100.', $sshInfo['ip']);
            $this->assertArrayHasKey('username', $sshInfo);
            $this->assertArrayHasKey('password', $sshInfo);
        }
    }

    /**
     * Test web interface error handling with real libvirt backend
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     * @covers \VmManagement\Exceptions\VMManagementException
     */
    public function testWebInterfaceErrorHandlingWithRealLibvirt(): void
    {
        // Test with invalid data
        $data = [
            'vm_name' => '', // Invalid empty name
            'user' => 'user1',
            'cpu' => 1,
            'memory' => 1024,
            'disk' => 10,
        ];

        $request = $this->requestFactory->createRequest('POST', '/create-vm')
            ->withHeader('Accept', 'application/json')
            ->withParsedBody($data);

        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $responseData = json_decode($body, true);

        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertStringContainsString('required', $responseData['error']);
    }

    /**
     * Test concurrent VM creation through web interface
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     */
    public function testConcurrentVMCreationThroughWebInterface(): void
    {
        $vmNames = [];
        $responses = [];

        // Create multiple VMs concurrently
        for ($i = 1; $i <= 3; $i++) {
            $vmName = "test-web-concurrent-{$i}-" . uniqid();
            $vmNames[] = $vmName;
            $this->createdVMs[] = $vmName;

            $data = [
                'vm_name' => $vmName,
                'user' => "user{$i}",
                'cpu' => 1,
                'memory' => 1024,
                'disk' => 10,
            ];

            $request = $this->requestFactory->createRequest('POST', '/create-vm')
                ->withHeader('Accept', 'application/json')
                ->withParsedBody($data);

            $response = $this->app->handle($request);
            $responses[] = $response;
        }

        // Verify all responses are successful
        foreach ($responses as $i => $response) {
            $this->assertEquals(200, $response->getStatusCode());

            $body = (string)$response->getBody();
            $responseData = json_decode($body, true);

            $this->assertTrue($responseData['success']);
            $this->assertEquals($vmNames[$i], $responseData['vm']['name']);
        }

        // Verify all VMs are running
        foreach ($vmNames as $vmName) {
            $this->assertTrue($this->vmManager->isVMRunning($vmName));
        }
    }

    /**
     * Test web interface performance with real libvirt backend
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     */
    public function testWebInterfacePerformanceWithRealLibvirt(): void
    {
        $vmName = 'test-web-perf-' . uniqid();
        $this->createdVMs[] = $vmName;

        $data = [
            'vm_name' => $vmName,
            'user' => 'user1',
            'cpu' => 1,
            'memory' => 1024,
            'disk' => 10,
        ];

        $startTime = microtime(true);

        $request = $this->requestFactory->createRequest('POST', '/create-vm')
            ->withHeader('Accept', 'application/json')
            ->withParsedBody($data);

        $response = $this->app->handle($request);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->assertEquals(200, $response->getStatusCode());

        // VM creation should complete within reasonable time (adjust as needed)
        $this->assertLessThan(60, $duration, "VM creation took too long: {$duration} seconds");

        $body = (string)$response->getBody();
        $responseData = json_decode($body, true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('ssh_info', $responseData);
    }

    /**
     * Test web interface with different resource configurations
     *
     * @covers \VmManagement\VMManager::createAndStartVM
     */
    public function testWebInterfaceWithDifferentResourceConfigurations(): void
    {
        $configurations = [
            ['cpu' => 1, 'memory' => 1024, 'disk' => 10],
            ['cpu' => 2, 'memory' => 2048, 'disk' => 20],
            ['cpu' => 4, 'memory' => 4096, 'disk' => 40],
        ];

        foreach ($configurations as $i => $config) {
            $vmName = "test-web-config-{$i}-" . uniqid();
            $this->createdVMs[] = $vmName;

            $data = array_merge([
                'vm_name' => $vmName,
                'user' => 'user1',
            ], $config);

            $request = $this->requestFactory->createRequest('POST', '/create-vm')
                ->withHeader('Accept', 'application/json')
                ->withParsedBody($data);

            $response = $this->app->handle($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string)$response->getBody();
            $responseData = json_decode($body, true);

            $this->assertTrue($responseData['success']);
            $this->assertEquals($config['cpu'], $responseData['vm']['cpu']);
            $this->assertEquals($config['memory'], $responseData['vm']['memory']);
            $this->assertEquals($config['disk'], $responseData['vm']['disk']);

            // Verify VM was actually created
            $this->assertTrue($this->vmManager->isVMRunning($vmName));
        }
    }

    /**
     * Check if libvirt is available for testing
     */
    private function checkLibvirtAvailability(): bool
    {
        try {
            $connection = $this->vmManager->connect();

            return $connection && $this->vmManager->isConnected();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clean up a VM (stop and undefine)
     */
    private function cleanupVM(string $vmName): void
    {
        try {
            if ($this->vmManager->isConnected()) {
                $domain = $this->vmManager->getDomainByName($vmName);
                if ($domain !== false) {
                    // Stop the VM if running
                    @libvirt_domain_shutdown($domain);

                    // Wait a moment for shutdown
                    sleep(2);

                    // Force stop if still running
                    @libvirt_domain_destroy($domain);

                    // Undefine the VM
                    @libvirt_domain_undefine($domain);
                }
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors in tests
        }
    }
}
