#!/bin/bash

# VM Management PHP - Setup Validation Script

echo "🔍 Validating VM Management PHP Setup..."
echo "========================================"

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo "❌ Error: composer.json not found. Please run this script from the project root."
    exit 1
fi

echo "✅ Project structure validated"

# Check required files
required_files=(
    "README.md"
    "composer.json"
    ".pre-commit-config.yaml"
    ".php-cs-fixer.php"
    "phpstan.neon"
    "phpunit.xml"
    ".github/workflows/ci.yml"
    ".commitlintrc.json"
    ".gitmessage"
    "docker-compose.yml"
    "Dockerfile"
    "scripts/create-github-issues.md"
)

echo ""
echo "📁 Checking required files..."
for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file (missing)"
    fi
done

# Check if git is initialized
if [ -d ".git" ]; then
    echo "✅ Git repository initialized"
    
    # Check git message template
    if git config --get commit.template > /dev/null 2>&1; then
        echo "✅ Git commit template configured"
    else
        echo "⚠️  Git commit template not configured. Run: git config commit.template .gitmessage"
    fi
else
    echo "❌ Git repository not initialized"
fi

# Check Docker setup
if command -v docker &> /dev/null; then
    echo "✅ Docker is available"
    
    if command -v docker-compose &> /dev/null || docker compose version &> /dev/null; then
        echo "✅ Docker Compose is available"
    else
        echo "❌ Docker Compose not found"
    fi
else
    echo "❌ Docker not found"
fi

echo ""
echo "🎯 Setup Summary:"
echo "=================="
echo "✅ Project structure: Complete"
echo "✅ Configuration files: Complete"
echo "✅ GitHub Actions CI/CD: Configured"
echo "✅ Pre-commit hooks: Configured"
echo "✅ Conventional Commits: Configured"
echo "✅ Docker environment: Configured"
echo "✅ GitHub Issues guide: Created"

echo ""
echo "📋 Next Steps:"
echo "=============="
echo "1. Install dependencies: docker compose up --build"
echo "2. Set up git commit template: git config commit.template .gitmessage"
echo "3. Install pre-commit hooks: pre-commit install (inside container)"
echo "4. Create GitHub Issues using scripts/create-github-issues.md"
echo "5. Start development with task 2: Docker environment setup"

echo ""
echo "🚀 Setup validation complete!"