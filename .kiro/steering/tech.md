# Technology Stack

## Core Technologies

- **PHP**: 8.1+ (strict typing enabled)
- **Framework**: Slim Framework 4.x for REST API
- **VM Management**: libvirt-php extension
- **Networking**: OpenVSwitch for VLAN management
- **Logging**: Monolog

## Development Tools

- **Testing**: PHPUnit 10.x with Mockery
- **Code Quality**: PHP-CS-Fixer (PSR-12 + PHP 8.1 migration rules)
- **Static Analysis**: PHPStan (level 8)
- **Pre-commit**: Automated code quality checks
- **Containerization**: Docker with docker-compose

## Build System

### Docker Commands

```bash
# Development environment
docker-compose up -d       # Start development stack
docker-compose down        # Stop development stack

# Application access
curl http://localhost:8080 # Test application endpoint
```

### Composer Scripts (via Docker)

```bash
# Testing
docker-compose exec php-app composer test                 # Run PHPUnit tests
docker-compose exec php-app composer test-coverage       # Generate coverage report

# Code Quality
docker-compose exec php-app composer cs-check           # Check code style (dry-run)
docker-compose exec php-app composer cs-fix            # Fix code style issues
docker-compose exec php-app composer analyze           # Run PHPStan static analysis

# Pre-commit checks
docker-compose exec php-app composer pre-commit        # Run all quality checks

# Shell access
docker-compose exec php-app bash                       # Access container shell
```

### Development Workflow

1. Use feature branches for development
2. Pre-commit hooks enforce code quality
3. Conventional Commits for commit messages
4. All code must pass PHPStan level 8
5. PSR-12 coding standards enforced