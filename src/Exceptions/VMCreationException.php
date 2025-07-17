<?php

declare(strict_types=1);

namespace VmManagement\Exceptions;

/**
 * Exception thrown when VM creation, startup, or management operations fail
 */
class VMCreationException extends VMManagementException
{
    public const ERROR_DOMAIN_DEFINE_FAILED = 2001;
    public const ERROR_DOMAIN_START_FAILED = 2002;
    public const ERROR_DOMAIN_NOT_FOUND = 2003;
    public const ERROR_DOMAIN_STOP_FAILED = 2004;
    public const ERROR_STORAGE_POOL_NOT_FOUND = 2005;
    public const ERROR_VOLUME_CREATE_FAILED = 2006;
    public const ERROR_DISK_IMAGE_FAILED = 2007;
    public const ERROR_XML_GENERATION_FAILED = 2008;
    public const ERROR_VM_ALREADY_EXISTS = 2009;
    public const ERROR_SSH_INFO_FAILED = 2010;

    public static function domainDefineFailed(string $vmName, ?string $libvirtError = null): self
    {
        $message = sprintf('Failed to define VM domain "%s"', $vmName);
        $context = ['vm_name' => $vmName];

        if ($libvirtError) {
            $message .= sprintf(': %s', $libvirtError);
            $context['libvirt_error'] = $libvirtError;
        }

        return new self($message, self::ERROR_DOMAIN_DEFINE_FAILED, null, $context);
    }

    public static function domainStartFailed(string $vmName, ?string $libvirtError = null): self
    {
        $message = sprintf('Failed to start VM "%s"', $vmName);
        $context = ['vm_name' => $vmName];

        if ($libvirtError) {
            $message .= sprintf(': %s', $libvirtError);
            $context['libvirt_error'] = $libvirtError;
        }

        return new self($message, self::ERROR_DOMAIN_START_FAILED, null, $context);
    }

    public static function domainNotFound(string $vmName): self
    {
        return new self(
            sprintf('VM domain "%s" not found', $vmName),
            self::ERROR_DOMAIN_NOT_FOUND,
            null,
            ['vm_name' => $vmName]
        );
    }

    public static function storagePoolNotFound(string $poolName): self
    {
        return new self(
            sprintf('Storage pool "%s" not found', $poolName),
            self::ERROR_STORAGE_POOL_NOT_FOUND,
            null,
            ['pool_name' => $poolName]
        );
    }

    public static function volumeCreateFailed(string $volumeName, ?string $libvirtError = null): self
    {
        $message = sprintf('Failed to create storage volume "%s"', $volumeName);
        $context = ['volume_name' => $volumeName];

        if ($libvirtError) {
            $message .= sprintf(': %s', $libvirtError);
            $context['libvirt_error'] = $libvirtError;
        }

        return new self($message, self::ERROR_VOLUME_CREATE_FAILED, null, $context);
    }

    public static function diskImageFailed(string $imagePath, string $error): self
    {
        return new self(
            sprintf('Failed to create disk image "%s": %s', $imagePath, $error),
            self::ERROR_DISK_IMAGE_FAILED,
            null,
            ['image_path' => $imagePath, 'error' => $error]
        );
    }

    public static function xmlGenerationFailed(string $vmName, string $error): self
    {
        return new self(
            sprintf('Failed to generate XML configuration for VM "%s": %s', $vmName, $error),
            self::ERROR_XML_GENERATION_FAILED,
            null,
            ['vm_name' => $vmName, 'error' => $error]
        );
    }

    public static function vmAlreadyExists(string $vmName): self
    {
        return new self(
            sprintf('VM "%s" already exists', $vmName),
            self::ERROR_VM_ALREADY_EXISTS,
            null,
            ['vm_name' => $vmName]
        );
    }

    public static function sshInfoFailed(string $vmName, string $reason): self
    {
        return new self(
            sprintf('Failed to get SSH info for VM "%s": %s', $vmName, $reason),
            self::ERROR_SSH_INFO_FAILED,
            null,
            ['vm_name' => $vmName, 'reason' => $reason]
        );
    }
}
