# Base API Template

[![CI](https://github.com/ilachev/base-api-template/actions/workflows/ci.yml/badge.svg)](https://github.com/ilachev/base-api-template/actions/workflows/ci.yml)

A pure PHP infrastructure-first backend template with Protocol Buffers for transport contracts, automatic route generation, and a small RoadRunner-based runtime core.

Current target state for this repository: a production-ready infrastructure template on pure PHP, without framework coupling, organized around `Platform`, reusable `Capabilities`, and small `Examples`.

Default runtime is infrastructure-only. Example code is kept in the repository, but it is not part of the main bootstrap or route surface.

## Features

- Protocol Buffer based transport contracts
- Automatic route generation from proto files
- OpenAPI documentation generation
- Small framework-free runtime core
- RoadRunner-powered execution model
- Capability-oriented architecture for reusable backend building blocks
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

# Start all services and run the application (PostgreSQL + RoadRunner)
task run

# The API will be available at http://localhost:8080

# To stop all services
task services:stop
```

## Testing Strategy

- `task test` runs unit tests only and requires no external services
- `task verify` is the default local gate: lint, static analysis and unit tests
- `task test:integration` runs PostgreSQL-backed integration tests
- `task verify:full` runs both the default gate and integration verification

Use `task services:start` before integration runs when you need a local PostgreSQL instance.

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
