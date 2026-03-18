# Base API Template

[![CI](https://github.com/ilachev/base-api-template/actions/workflows/ci.yml/badge.svg)](https://github.com/ilachev/base-api-template/actions/workflows/ci.yml)

A pure PHP backend template with Protocol Buffers for endpoint contracts, generated endpoint handlers, protobuf-driven runtime routing, and a small RoadRunner-based runtime core.

Current target state for this repository: a production-ready set of reusable backend blocks on pure PHP, without framework coupling. The repository keeps runtime primitives and optional integrations, but it should not feel like a heavy framework.

The default runtime stays focused on reusable blocks. The only built-in endpoint example is a neutral health check that demonstrates the protobuf-first endpoint flow.

## Features

- Protocol Buffer based endpoint contracts
- Generated server-side endpoint handlers from protobuf services
- Protobuf-driven runtime routing from generated operation metadata
- OpenAPI documentation generation
- Small framework-free runtime core
- RoadRunner-powered execution model
- Reusable backend building blocks instead of framework-driven architecture
- High test coverage and static analysis
- GitHub Actions CI/CD pipeline

## Documentation

Detailed project documentation for humans and LLM agents starts in [`docs/README.md`](docs/README.md).

## Setup

```bash
# Install dependencies
composer install

# Generate proto artifacts
task proto:gen:all

# Run unit tests
task test

# Run integration tests against PostgreSQL
task test:integration

# Run the default verification gate (lint, static analysis, unit tests)
task verify

# Run the full verification gate, including integration tests
task verify:full
```

## Running the Application

The application uses RoadRunner for high performance. To run:

```bash
# Install RoadRunner globally (if not already installed)
# Visit https://roadrunner.dev/download for installation instructions

# Start optional services and run the application
task run

# The API will be available at http://localhost:8080

# To stop all services
task services:stop
```

## Testing Strategy

- `task test` runs unit tests only and requires no external services
- `task verify` is the default local gate: lint, static analysis and unit tests
- `task test:integration` runs the PostgreSQL integration profile
- `task verify:full` runs both the default gate and integration verification

Use `task services:start` before integration runs or local runtime checks when you need a PostgreSQL instance.

## GitHub Actions

This project uses GitHub Actions for CI/CD:

- **CI Workflow**: Runs on every push and pull request to master
  - Validates code style
  - Runs static analysis
  - Executes all tests
  - Generates proto artifacts

- **Release Workflow**: Runs when a new release is created
  - Builds a production package
  - Attaches the package to the GitHub release
  - Uploads the OpenAPI documentation

## Conventions

### DTO
All DTOs are final readonly classes with public promoted properties:

```php
final readonly class Session
{
    public function __construct(
        public string $id,
        public string $token
    ) {}
}
```
