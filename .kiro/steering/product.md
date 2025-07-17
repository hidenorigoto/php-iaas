# Product Overview

VM Management PHP is a minimal PHP application for creating and managing virtual machines using libvirt-php. The application provides:

- VM creation and lifecycle management through libvirt
- VLAN-based network isolation using OpenVSwitch (user1: VLAN 100, user2: VLAN 101, user3: VLAN 102)
- Simple web interface for VM operations
- SSH connection information retrieval
- RESTful API for VM management

## Target Use Case

Designed for environments where multiple users need isolated VM instances on the same physical host, with automatic network segmentation and easy SSH access provisioning.

## Key Features

- Integrated VM creation and startup
- User-specific VLAN assignment
- Automatic SSH credential generation
- Web-based management interface
- Docker-based development environment