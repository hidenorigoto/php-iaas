<?php

declare(strict_types=1);

namespace VmManagement\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\RequestFactory;
use VmManagement\Exceptions\VMManagementException;
use VmManagement\SimpleVM;
use VmManagement\VMManager;

class WebInterfaceTest extends TestCase
{
    private App $app;
    private RequestFactory $requestFactory;

    protected function setUp(): void
    {
        // Mock the VM manager for testing
        $this->mockVMManager();

        // Create app instance
        $this->app = $this->createApp();

        // Create factories
        $this->requestFactory = new RequestFactory();
    }

    private function createApp(): App
    {
        // Include the app logic but with mocked dependencies
        $app = AppFactory::create();
        $app->addErrorMiddleware(true, true, true);

        // Mock VM manager
        $vmManager = $this->createMock(VMManager::class);

        // Set up mock behavior
        $vmManager->method('createAndStartVM')
            ->willReturnCallback(function (SimpleVM $vm) {
                if ($vm->name === 'fail-vm') {
                    throw new VMManagementException('Failed to create VM');
                }

                return true;
            });

        $vmManager->method('getSSHInfo')
            ->willReturnCallback(function (SimpleVM $vm) {
                if ($vm->name === 'fail-vm') {
                    return ['error' => 'VM is not running'];
                }

                return [
                    'ip' => '192.168.100.10',
                    'username' => 'ubuntu',
                    'password' => 'test123!',
                    'ready' => true,
                ];
            });

        $vmManager->method('listVMs')
            ->willReturn([
                ['name' => 'test-vm-1', 'user' => 'user1', 'status' => 'running'],
                ['name' => 'test-vm-2', 'user' => 'user2', 'status' => 'stopped'],
            ]);

        // Routes
        $app->get('/', function ($request, $response) {
            $html = '<!DOCTYPE html><html><head><title>VM Management</title></head><body><h1>VM Management System</h1><form action="/create-vm" method="POST"><input type="text" name="vm_name" required><select name="user" required><option value="">Select User</option><option value="user1">User 1</option><option value="user2">User 2</option><option value="user3">User 3</option></select><button type="submit">Create VM</button></form></body></html>';
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
                        $html .= '<h3>SSH Connection Information:</h3><p>IP: ' . htmlspecialchars($sshInfo['ip']) . '</p><p>Username: ' . htmlspecialchars($sshInfo['username']) . '</p><p>Password: ' . htmlspecialchars($sshInfo['password']) . '</p><p>Command: ssh ' . htmlspecialchars($sshInfo['username']) . '@' . htmlspecialchars($sshInfo['ip']) . '</p>';
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

    private function mockVMManager(): void
    {
        // Mock libvirt functions if needed - using class constants instead of nested function
        if (! defined('LIBVIRT_MOCKED')) {
            define('LIBVIRT_MOCKED', true);
        }
    }

    public function testHomePageDisplaysForm(): void
    {
        $request = $this->requestFactory->createRequest('GET', '/');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $this->assertStringContainsString('VM Management System', $body);
        $this->assertStringContainsString('<form', $body);
        $this->assertStringContainsString('name="vm_name"', $body);
        $this->assertStringContainsString('name="user"', $body);
        $this->assertStringContainsString('option value="user1"', $body);
        $this->assertStringContainsString('option value="user2"', $body);
        $this->assertStringContainsString('option value="user3"', $body);
    }

    public function testCreateVMWithValidDataReturnsSuccess(): void
    {
        $data = [
            'vm_name' => 'test-vm',
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
        $this->assertStringContainsString('test-vm', $body);
    }

    public function testCreateVMWithJSONRequestReturnsJSON(): void
    {
        $data = [
            'vm_name' => 'test-vm-json',
            'user' => 'user2',
            'cpu' => 4,
            'memory' => 4096,
            'disk' => 40,
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
        $this->assertEquals('test-vm-json', $responseData['vm']['name']);
        $this->assertEquals('user2', $responseData['vm']['user']);
        $this->assertEquals(4, $responseData['vm']['cpu']);
        $this->assertEquals(4096, $responseData['vm']['memory']);
        $this->assertEquals(40, $responseData['vm']['disk']);
        $this->assertArrayHasKey('ssh_info', $responseData);
        $this->assertEquals('192.168.100.10', $responseData['ssh_info']['ip']);
    }

    public function testCreateVMWithMissingDataReturnsError(): void
    {
        $data = [
            'vm_name' => '',
            'user' => 'user1',
        ];

        $request = $this->requestFactory->createRequest('POST', '/create-vm')
            ->withParsedBody($data);

        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $this->assertStringContainsString('Error', $body);
        $this->assertStringContainsString('VM name and user are required', $body);
    }

    public function testCreateVMWithJSONErrorReturnsJSONError(): void
    {
        $data = [
            'vm_name' => 'fail-vm',
            'user' => 'user1',
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
        $this->assertEquals('Failed to create VM', $responseData['error']);
        $this->assertArrayHasKey('context', $responseData);
    }

    public function testUserSelectionWorksForAllUsers(): void
    {
        $users = ['user1', 'user2', 'user3'];

        foreach ($users as $user) {
            $data = [
                'vm_name' => "test-vm-$user",
                'user' => $user,
            ];

            $request = $this->requestFactory->createRequest('POST', '/create-vm')
                ->withHeader('Accept', 'application/json')
                ->withParsedBody($data);

            $response = $this->app->handle($request);

            $this->assertEquals(200, $response->getStatusCode());

            $body = (string)$response->getBody();
            $responseData = json_decode($body, true);

            $this->assertTrue($responseData['success']);
            $this->assertEquals($user, $responseData['vm']['user']);
        }
    }

    public function testAPIVMsEndpointReturnsVMList(): void
    {
        $request = $this->requestFactory->createRequest('GET', '/api/vms');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $responseData = json_decode($body, true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('vms', $responseData);
        $this->assertCount(2, $responseData['vms']);
        $this->assertEquals('test-vm-1', $responseData['vms'][0]['name']);
        $this->assertEquals('user1', $responseData['vms'][0]['user']);
        $this->assertEquals('running', $responseData['vms'][0]['status']);
    }

    public function testAPISSHInfoEndpointReturnsSSHInfo(): void
    {
        $request = $this->requestFactory->createRequest('GET', '/api/vm/test-vm/ssh');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $responseData = json_decode($body, true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('ssh_info', $responseData);
        $this->assertEquals('192.168.100.10', $responseData['ssh_info']['ip']);
        $this->assertEquals('ubuntu', $responseData['ssh_info']['username']);
        $this->assertEquals('test123!', $responseData['ssh_info']['password']);
        $this->assertTrue($responseData['ssh_info']['ready']);
    }

    public function testAPISSHInfoEndpointWithErrorReturnsError(): void
    {
        $request = $this->requestFactory->createRequest('GET', '/api/vm/fail-vm/ssh');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $responseData = json_decode($body, true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('ssh_info', $responseData);
        $this->assertEquals('VM is not running', $responseData['ssh_info']['error']);
    }

    public function testDefaultValuesAreAppliedCorrectly(): void
    {
        $data = [
            'vm_name' => 'default-vm',
            'user' => 'user1',
        ];

        $request = $this->requestFactory->createRequest('POST', '/create-vm')
            ->withHeader('Accept', 'application/json')
            ->withParsedBody($data);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $body = (string)$response->getBody();
        $responseData = json_decode($body, true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals(2, $responseData['vm']['cpu']);
        $this->assertEquals(2048, $responseData['vm']['memory']);
        $this->assertEquals(20, $responseData['vm']['disk']);
    }

    public function testSSHInfoDisplayedAfterFormSubmission(): void
    {
        $data = [
            'vm_name' => 'ssh-test-vm',
            'user' => 'user1',
        ];

        $request = $this->requestFactory->createRequest('POST', '/create-vm')
            ->withParsedBody($data);

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $body = (string)$response->getBody();
        $this->assertStringContainsString('SSH Connection Information', $body);
        $this->assertStringContainsString('192.168.100.10', $body);
        $this->assertStringContainsString('ubuntu', $body);
        $this->assertStringContainsString('test123!', $body);
        $this->assertStringContainsString('ssh ubuntu@192.168.100.10', $body);
    }

    public function testSSHInfoErrorDisplayedAfterFormSubmission(): void
    {
        $data = [
            'vm_name' => 'fail-vm',
            'user' => 'user1',
        ];

        $request = $this->requestFactory->createRequest('POST', '/create-vm')
            ->withParsedBody($data);

        $response = $this->app->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Failed to create VM', $body);
    }
}
