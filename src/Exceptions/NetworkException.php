<?php

declare(strict_types=1);

namespace VmManagement\Exceptions;

/**
 * Exception thrown when network operations fail
 */
class NetworkException extends VMManagementException
{
    public const ERROR_NETWORK_DEFINE_FAILED = 3001;
    public const ERROR_NETWORK_START_FAILED = 3002;
    public const ERROR_NETWORK_NOT_FOUND = 3003;
    public const ERROR_NETWORK_STOP_FAILED = 3004;
    public const ERROR_INVALID_NETWORK_CONFIG = 3005;
    public const ERROR_DHCP_LEASE_FAILED = 3006;
    public const ERROR_IP_ADDRESS_NOT_FOUND = 3007;

    public static function networkDefineFailed(string $networkName, ?string $libvirtError = null): self
    {
        $message = sprintf('Failed to define network "%s"', $networkName);
        $context = ['network_name' => $networkName];

        if ($libvirtError) {
            $message .= sprintf(': %s', $libvirtError);
            $context['libvirt_error'] = $libvirtError;
        }

        return new self($message, self::ERROR_NETWORK_DEFINE_FAILED, null, $context);
    }

    public static function networkStartFailed(string $networkName, ?string $libvirtError = null): self
    {
        $message = sprintf('Failed to start network "%s"', $networkName);
        $context = ['network_name' => $networkName];

        if ($libvirtError) {
            $message .= sprintf(': %s', $libvirtError);
            $context['libvirt_error'] = $libvirtError;
        }

        return new self($message, self::ERROR_NETWORK_START_FAILED, null, $context);
    }

    public static function networkNotFound(string $networkName): self
    {
        return new self(
            sprintf('Network "%s" not found', $networkName),
            self::ERROR_NETWORK_NOT_FOUND,
            null,
            ['network_name' => $networkName]
        );
    }

    public static function invalidNetworkConfig(string $user, string $reason): self
    {
        return new self(
            sprintf('Invalid network configuration for user "%s": %s', $user, $reason),
            self::ERROR_INVALID_NETWORK_CONFIG,
            null,
            ['user' => $user, 'reason' => $reason]
        );
    }

    public static function dhcpLeaseFailed(string $networkName, ?string $libvirtError = null): self
    {
        $message = sprintf('Failed to retrieve DHCP leases for network "%s"', $networkName);
        $context = ['network_name' => $networkName];

        if ($libvirtError) {
            $message .= sprintf(': %s', $libvirtError);
            $context['libvirt_error'] = $libvirtError;
        }

        return new self($message, self::ERROR_DHCP_LEASE_FAILED, null, $context);
    }

    public static function ipAddressNotFound(string $vmName, string $networkName): self
    {
        return new self(
            sprintf('IP address not found for VM "%s" on network "%s"', $vmName, $networkName),
            self::ERROR_IP_ADDRESS_NOT_FOUND,
            null,
            ['vm_name' => $vmName, 'network_name' => $networkName]
        );
    }
}
