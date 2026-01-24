# Contributing to Movie Booking System

Thank you for considering contributing to the Movie Booking System! This document provides guidelines and instructions for contributing.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Code Standards](#code-standards)
- [Commit Guidelines](#commit-guidelines)
- [Pull Request Process](#pull-request-process)
- [Testing](#testing)

---

## Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Focus on the code, not the person
- Help others learn and grow

---

## Getting Started

### Prerequisites

- Python 3.11+
- Docker & Docker Compose
- Git

### Local Setup

```bash
# Clone the repository
git clone https://github.com/your-org/movie-booking.git
cd movie-booking

# Create virtual environment
python -m venv .venv
source .venv/bin/activate  # Linux/macOS
# or
.\.venv\Scripts\Activate.ps1  # Windows

# Install development dependencies
pip install black isort flake8 mypy pytest

# Start services
./scripts/run_local.sh up  # or .\scripts\run_local.ps1 up

# Run tests
./scripts/e2e_test.sh  # or .\scripts\e2e_test.ps1
```

---

## Development Workflow

### Branch Naming

```
feature/   - New features (feature/add-seat-selection)
fix/       - Bug fixes (fix/booking-race-condition)
docs/      - Documentation (docs/update-readme)
refactor/  - Code refactoring (refactor/payment-service)
test/      - Test additions (test/add-booking-tests)
```

### Workflow

1. Create a branch from `main`
2. Make your changes
3. Run linting and tests locally
4. Push and create a Pull Request
5. Address review feedback
6. Merge after approval

---

## Code Standards

### Python Style

We use **Black** for formatting and **isort** for import sorting.

```bash
# Format code
black services/

# Sort imports
isort services/ --profile black

# Check linting
flake8 services/ --max-line-length=120
```

### Pre-commit Checks

Before committing, ensure:

```bash
# All checks pass
black --check services/
isort --check-only services/ --profile black
flake8 services/ --max-line-length=120 --extend-ignore=E501,W503
```

### Code Organization

```
services/<service-name>/
├── app/
│   ├── __init__.py
│   ├── main.py          # FastAPI app and routes
│   ├── models.py        # SQLAlchemy models
│   ├── schemas.py       # Pydantic schemas
│   ├── crud.py          # Database operations
│   └── utils.py         # Helper functions
├── Dockerfile
├── requirements.txt
└── tests/
    └── test_main.py
```

---

## Commit Guidelines

We follow [Conventional Commits](https://www.conventionalcommits.org/).

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

| Type | Description |
|------|-------------|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation |
| `style` | Formatting (no code change) |
| `refactor` | Code restructuring |
| `test` | Adding tests |
| `chore` | Maintenance tasks |

### Examples

```bash
feat(booking): add seat locking mechanism

fix(payment): resolve race condition in concurrent payments

docs(readme): add quick start guide

test(auth): add unit tests for JWT validation
```

---

## Pull Request Process

### Before Submitting

- [ ] Code passes all lint checks
- [ ] Tests pass locally
- [ ] New features have tests
- [ ] Documentation is updated
- [ ] Commit messages follow guidelines

### PR Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
How was this tested?

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-reviewed my code
- [ ] Added necessary documentation
- [ ] Tests pass locally
```

### Review Process

1. Create PR against `main`
2. CI checks must pass
3. At least 1 approval required
4. Squash and merge

---

## Testing

### Unit Tests

```bash
# Run all tests
pytest services/

# Run specific service tests
pytest services/auth-service/tests/

# Run with coverage
pytest --cov=services services/
```

### Integration Tests

```bash
# Start services
./scripts/run_local.sh up

# Run e2e tests
./scripts/e2e_test.sh
```

### Load Tests

```bash
# Run k6 load test
k6 run k8s/testing/load-test.js

# Run Locust
locust -f k8s/testing/locustfile.py --host=http://localhost
```

---

## Questions?

- Check existing issues and discussions
- Create a new issue with the `question` label
- Reach out to maintainers

Thank you for contributing! 🎬
