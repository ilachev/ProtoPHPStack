# Base API Template

[![CI](https://github.com/ilachev/base-api-template/actions/workflows/ci.yml/badge.svg)](https://github.com/ilachev/base-api-template/actions/workflows/ci.yml)

A pure PHP infrastructure-first backend template with Protocol Buffers for transport contracts, automatic route generation, and a small RoadRunner-based runtime core.

Current target state for this repository: a production-ready infrastructure template on pure PHP, without framework coupling, organized around `Platform`, reusable `Capabilities`, and small `Examples`.

Default runtime is infrastructure-only. Reference endpoints live in a separate reference app config and are not loaded by default.

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

# Run tests
task test

# Run all verifications (lint, static analysis, tests)
task verify
```

## Running the Application

The application uses RoadRunner for high performance. To run:

```bash
# Install RoadRunner globally (if not already installed)
# Visit https://roadrunner.dev/download for installation instructions

# Start all services and run the application (PostgreSQL + RoadRunner)
task run

# The API will be available at http://localhost:8080

# Run the separate reference app entrypoint when example endpoints are needed
php public/reference.php

# To stop all services
task services:stop
```

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
