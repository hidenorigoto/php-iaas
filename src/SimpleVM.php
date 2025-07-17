<?php

declare(strict_types=1);

namespace VmManagement;

use DateTime;

/**
 * SimpleVM data class for holding VM information and SSH details
 */
class SimpleVM
{
    public string $name;
    public string $user;
    public int $vlanId;
    public string $status;
    public int $cpu;
    public int $memory;
    public int $disk;
    public string $ipAddress;
    public string $username;
    public string $password;
    public DateTime $createdAt;

    /**
     * Constructor for SimpleVM
     *
     * @param string $name VM name
     * @param string $user User (user1, user2, user3)
     * @param int $cpu CPU cores (default: 2)
     * @param int $memory Memory in MB (default: 2048)
     * @param int $disk Disk size in GB (default: 20)
     */
    public function __construct(
        string $name,
        string $user,
        int $cpu = 2,
        int $memory = 2048,
        int $disk = 20
    ) {
        $this->name = $name;
        $this->user = $user;
        $this->cpu = $cpu;
        $this->memory = $memory;
        $this->disk = $disk;
        $this->status = 'creating';
        $this->ipAddress = '';
        $this->username = 'ubuntu';
        $this->password = '';
        $this->createdAt = new DateTime();

        // Set VLAN ID based on user
        $this->vlanId = $this->getVlanIdForUser($user);
    }

    /**
     * Get VLAN ID for a specific user
     *
     * @param string $user User name
     * @return int VLAN ID
     */
    private function getVlanIdForUser(string $user): int
    {
        $userVlanMap = [
            'user1' => 100,
            'user2' => 101,
            'user3' => 102,
        ];

        return $userVlanMap[$user] ?? 100;
    }

    /**
     * Convert VM to array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'user' => $this->user,
            'vlan_id' => $this->vlanId,
            'status' => $this->status,
            'cpu' => $this->cpu,
            'memory' => $this->memory,
            'disk' => $this->disk,
            'ssh' => [
                'ip' => $this->ipAddress,
                'username' => $this->username,
                'password' => $this->password,
            ],
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
