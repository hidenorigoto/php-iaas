<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use VmManagement\VMManager;
use VmManagement\SimpleVM;
use VmManagement\Exceptions\VMManagementException;

// Create Slim app
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

// Create VM manager instance
$vmManager = new VMManager();

// Routes
$app->get('/', function (Request $request, Response $response) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>VM Management</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .container { max-width: 600px; margin: 0 auto; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input, select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
            button { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background-color: #0056b3; }
            .error { color: red; margin-top: 10px; }
            .success { color: green; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>VM Management System</h1>
            <form action="/create-vm" method="POST">
                <div class="form-group">
                    <label for="vm_name">VM Name:</label>
                    <input type="text" id="vm_name" name="vm_name" required>
                </div>
                <div class="form-group">
                    <label for="user">User:</label>
                    <select id="user" name="user" required>
                        <option value="">Select User</option>
                        <option value="user1">User 1 (VLAN 100)</option>
                        <option value="user2">User 2 (VLAN 101)</option>
                        <option value="user3">User 3 (VLAN 102)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cpu">CPU Cores:</label>
                    <input type="number" id="cpu" name="cpu" value="2" min="1" max="8">
                </div>
                <div class="form-group">
                    <label for="memory">Memory (MB):</label>
                    <input type="number" id="memory" name="memory" value="2048" min="512" max="8192">
                </div>
                <div class="form-group">
                    <label for="disk">Disk Size (GB):</label>
                    <input type="number" id="disk" name="disk" value="20" min="10" max="100">
                </div>
                <button type="submit">Create VM</button>
            </form>
        </div>
    </body>
    </html>';

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

$app->post('/create-vm', function (Request $request, Response $response) use ($vmManager) {
    $data = $request->getParsedBody();
    $acceptHeader = $request->getHeaderLine('Accept');
    $isJsonRequest = strpos($acceptHeader, 'application/json') !== false;

    try {
        // Validate input
        if (empty($data['vm_name']) || empty($data['user'])) {
            throw new VMManagementException('VM name and user are required');
        }

        // Create VM object
        $vm = new SimpleVM(
            name: $data['vm_name'],
            user: $data['user'],
            cpu: (int)($data['cpu'] ?? 2),
            memory: (int)($data['memory'] ?? 2048),
            disk: (int)($data['disk'] ?? 20)
        );

        // Create and start VM
        $vmManager->createAndStartVM($vm);

        // Get SSH information
        $sshInfo = $vmManager->getSSHInfo($vm);

        // Handle case when getSSHInfo returns false
        if ($sshInfo === false) {
            $sshInfo = ['error' => 'VM is initializing. Cloud-init is configuring the system. This may take up to 2 minutes. Please refresh the page in a moment.'];
        }

        if ($isJsonRequest) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'VM created successfully',
                'vm' => [
                    'name' => $vm->name,
                    'user' => $vm->user,
                    'cpu' => $vm->cpu,
                    'memory' => $vm->memory,
                    'disk' => $vm->disk
                ],
                'ssh_info' => $sshInfo
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            // HTML response
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <title>VM Created Successfully</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 40px; }
                    .container { max-width: 600px; margin: 0 auto; }
                    .success { color: green; margin-bottom: 20px; }
                    .info-box { background-color: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 20px; }
                    .ssh-info { background-color: #e9ecef; padding: 15px; border-radius: 4px; font-family: monospace; }
                    a { color: #007bff; text-decoration: none; }
                    a:hover { text-decoration: underline; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>VM Created Successfully</h1>
                    <div class="success">
                        <strong>VM "' . htmlspecialchars($vm->name) . '" has been created and started!</strong>
                    </div>
                    <div class="info-box">
                        <h3>VM Information:</h3>
                        <p><strong>Name:</strong> ' . htmlspecialchars($vm->name) . '</p>
                        <p><strong>User:</strong> ' . htmlspecialchars($vm->user) . '</p>
                        <p><strong>CPU:</strong> ' . $vm->cpu . ' cores</p>
                        <p><strong>Memory:</strong> ' . $vm->memory . ' MB</p>
                        <p><strong>Disk:</strong> ' . $vm->disk . ' GB</p>
                    </div>';

            if (isset($sshInfo['error'])) {
                $html .= '
                    <div class="info-box">
                        <h3>SSH Information:</h3>
                        <p style="color: orange;">Warning: ' . htmlspecialchars($sshInfo['error']) . '</p>
                    </div>';
            } else {
                $html .= '
                    <div class="info-box">
                        <h3>SSH Connection Information:</h3>
                        <div class="ssh-info">
                            <p><strong>IP Address:</strong> ' . htmlspecialchars($sshInfo['ip']) . '</p>
                            <p><strong>Username:</strong> ' . htmlspecialchars($sshInfo['username']) . '</p>
                            <p><strong>Password:</strong> ' . htmlspecialchars($sshInfo['password']) . '</p>
                            <p><strong>SSH Ready:</strong> ' . ($sshInfo['ready'] ? 'Yes' : 'No') . '</p>
                        </div>
                        <p><strong>SSH Command:</strong></p>
                        <div class="ssh-info">
                            ssh ' . htmlspecialchars($sshInfo['username']) . '@' . htmlspecialchars($sshInfo['ip']) . '
                        </div>
                    </div>';
            }

            $html .= '
                    <p><a href="/">← Create Another VM</a></p>
                </div>
            </body>
            </html>';

            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'text/html');
        }

    } catch (VMManagementException $e) {
        if ($isJsonRequest) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        } else {
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <title>Error Creating VM</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 40px; }
                    .container { max-width: 600px; margin: 0 auto; }
                    .error { color: red; margin-bottom: 20px; }
                    a { color: #007bff; text-decoration: none; }
                    a:hover { text-decoration: underline; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>Error Creating VM</h1>
                    <div class="error">
                        <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
                    </div>
                    <p><a href="/">← Back to Form</a></p>
                </div>
            </body>
            </html>';

            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'text/html')->withStatus(400);
        }
    }
});

// API endpoint to get VM list
$app->get('/api/vms', function (Request $request, Response $response) use ($vmManager) {
    try {
        $vms = $vmManager->listVMs();

        $response->getBody()->write(json_encode([
            'success' => true,
            'vms' => $vms
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (VMManagementException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'context' => $e->getContext()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

// API endpoint to get SSH info for a VM
$app->get('/api/vm/{name}/ssh', function (Request $request, Response $response, $args) use ($vmManager) {
    try {
        $vmName = $args['name'];

        // For this endpoint, we need to find the VM by name
        // This is a simplified implementation
        $vm = new SimpleVM(name: $vmName, user: 'user1'); // Default user, should be improved

        $sshInfo = $vmManager->getSSHInfo($vm);

        $response->getBody()->write(json_encode([
            'success' => true,
            'ssh_info' => $sshInfo
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (VMManagementException $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'context' => $e->getContext()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->run();
