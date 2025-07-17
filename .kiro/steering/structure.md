# Project Structure

## Directory Organization

```
├── public/           # Web entry point
│   └── index.php    # Application bootstrap
├── src/             # Application source code
│   └── VmManagement/ # PSR-4 namespace root
├── tests/           # Test suite
│   ├── Unit/        # Unit tests
│   ├── Integration/ # Integration tests (planned)
│   └── bootstrap.php # Test bootstrap
├── scripts/         # Utility scripts
└── .kiro/           # Kiro configuration
    └── steering/    # AI assistant guidance
```

## Namespace Convention

- **Root Namespace**: `VmManagement\`
- **Test Namespace**: `VmManagement\Tests\`
- **PSR-4 Autoloading**: Configured in composer.json

## Code Organization Patterns

### File Naming
- Classes: PascalCase (e.g., `VmManager.php`)
- Tests: ClassNameTest.php pattern
- Interfaces: Suffix with `Interface`
- Abstract classes: Prefix with `Abstract`

### Architecture Guidelines
- Use dependency injection for testability
- Separate concerns: VM operations, network management, API endpoints
- Follow SOLID principles
- Implement proper error handling and logging

## Configuration Files

- **composer.json**: Dependencies and scripts
- **phpunit.xml**: Test configuration
- **phpstan.neon**: Static analysis rules
- **.php-cs-fixer.php**: Code style rules
- **docker-compose.yml**: Development environment
- **.pre-commit-config.yaml**: Git hooks

## Development Standards

- All PHP files must use `declare(strict_types=1);`
- Classes must have proper docblocks
- Public methods require type hints and return types
- Use short array syntax `[]` instead of `array()`
- Follow PSR-12 coding standards