<?php

declare(strict_types=1);

namespace VmManagement\Exceptions;

/**
 * Exception thrown when libvirt connection operations fail
 */
class LibvirtConnectionException extends VMManagementException
{
    public const ERROR_CONNECTION_FAILED = 1001;
    public const ERROR_DISCONNECTION_FAILED = 1002;
    public const ERROR_ALREADY_CONNECTED = 1003;
    public const ERROR_NOT_CONNECTED = 1004;
    public const ERROR_PERMISSION_DENIED = 1005;

    public static function connectionFailed(string $uri, ?string $libvirtError = null): self
    {
        $message = sprintf('Failed to connect to libvirt at "%s"', $uri);
        $context = ['uri' => $uri];

        if ($libvirtError) {
            $message .= sprintf(': %s', $libvirtError);
            $context['libvirt_error'] = $libvirtError;
        }

        return new self($message, self::ERROR_CONNECTION_FAILED, null, $context);
    }

    public static function disconnectionFailed(?string $libvirtError = null): self
    {
        $message = 'Failed to disconnect from libvirt';
        $context = [];

        if ($libvirtError) {
            $message .= sprintf(': %s', $libvirtError);
            $context['libvirt_error'] = $libvirtError;
        }

        return new self($message, self::ERROR_DISCONNECTION_FAILED, null, $context);
    }

    public static function alreadyConnected(): self
    {
        return new self('Already connected to libvirt', self::ERROR_ALREADY_CONNECTED);
    }

    public static function notConnected(): self
    {
        return new self('Not connected to libvirt', self::ERROR_NOT_CONNECTED);
    }

    public static function permissionDenied(string $uri): self
    {
        return new self(
            sprintf('Permission denied connecting to libvirt at "%s"', $uri),
            self::ERROR_PERMISSION_DENIED,
            null,
            ['uri' => $uri]
        );
    }
}
