FROM php:8.1-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libvirt-dev \
    pkg-config \
    python3 \
    python3-pip \
    genisoimage \
    cloud-image-utils \
    qemu-utils \
    wget \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Xdebug for code coverage
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install libvirt-php extension (skip for now, will mock in tests)
# RUN pecl install libvirt-php && docker-php-ext-enable libvirt

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install pre-commit
RUN pip3 install pre-commit --break-system-packages

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json ./
COPY composer.loc[k] ./

# Install PHP dependencies
RUN composer install --no-scripts --no-autoloader

# Copy application code
COPY . .

# Generate autoloader
RUN composer dump-autoload --optimize

# Install pre-commit hooks
RUN pre-commit install || true

# Expose port
EXPOSE 8080

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
