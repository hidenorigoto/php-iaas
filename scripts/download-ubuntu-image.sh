#!/bin/bash

# Script to download Ubuntu 22.04 cloud image for VM creation

IMAGES_DIR="/var/lib/libvirt/images"
IMAGE_NAME="ubuntu-22.04-server-cloudimg-amd64.img"
IMAGE_URL="https://cloud-images.ubuntu.com/jammy/current/jammy-server-cloudimg-amd64.img"

echo "Downloading Ubuntu 22.04 LTS cloud image..."

# Check if running with sufficient privileges
if [ "$EUID" -ne 0 ]; then
    echo "Please run this script with sudo"
    exit 1
fi

# Create images directory if it doesn't exist
if [ ! -d "$IMAGES_DIR" ]; then
    echo "Creating images directory: $IMAGES_DIR"
    mkdir -p "$IMAGES_DIR"
fi

# Download the image
echo "Downloading from: $IMAGE_URL"
echo "Saving to: $IMAGES_DIR/$IMAGE_NAME"

wget -O "$IMAGES_DIR/$IMAGE_NAME" "$IMAGE_URL"

if [ $? -eq 0 ]; then
    echo "Download completed successfully!"
    echo "Setting proper permissions..."
    chmod 644 "$IMAGES_DIR/$IMAGE_NAME"
    echo "Ubuntu image is ready at: $IMAGES_DIR/$IMAGE_NAME"
else
    echo "Error: Failed to download Ubuntu image"
    exit 1
fi
