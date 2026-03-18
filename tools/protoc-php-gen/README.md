# Proto PHP Transport Generator

`protoc-php-gen` is the internal protoc plugin used by this repository to generate and validate PHP transport artifacts from protobuf `service/rpc` definitions.

The current production-grade scope is intentionally narrow: the main project path supports `transport_contracts`, `endpoint_validation`, and `route_manifest`.

## Current role

The supported project path is transport-oriented:

- parse protobuf descriptors;
- generate endpoint interfaces;
- generate HTTP handlers for the runtime adapter;
- validate handwritten endpoint implementations against generated expectations;
- generate endpoint binding manifests for handwritten runtime implementations;
- generate route manifests from `google.api.http`.

It is not the canonical path for domain-to-proto mapper generation.

## Product direction

This tool should be treated as a modular PHP code generation platform around protobuf descriptors, not as a one-off script and not as a grab bag of unrelated generators.

Today, `transport_contracts`, `endpoint_validation`, and `route_manifest` are stable and supported.

Future generators are allowed only if they:

- solve a reusable backend problem;
- have an explicit generation flag;
- keep a separate output contract;
- have dedicated tests;
- do not leak business or persistence policy into the codegen layer.

## Technical roadmap

The internal stabilization baseline is already in place:

1. modular plugin core (`PluginOptions`, `CodeGeneratorModule`, `GeneratorRegistry`);
2. typed descriptor model instead of raw array-driven flow;
3. dedicated type resolver for fully-qualified protobuf types;
4. runtime profile abstraction for transport generation;
5. stronger end-to-end coverage for the plugin protocol.

The next stage is not “more generators at any cost”, but a cleaner protobuf-first flow in the main template:

1. generated route manifests as the only route source of truth;
2. less manual runtime glue between generated transport and handwritten endpoints;
3. stricter consistency checks for generated artifacts.

The detailed product-level rationale is documented in:

- `docs/design/protoc-php-gen-product.md`

## Usage

```bash
protoc -I=./protos/proto \
  --plugin=protoc-gen-php-transport=./tools/protoc-php-gen/bin/protoc-php-gen.php \
  --php-transport_out=namespace=App\\Generated\\Transport,output_dir=gen,source_root=src,transport_profile=base_api_template,generate_transport_contracts=true,generate_endpoint_validation=true,generate_route_manifest=true:. \
  ./protos/proto/app/v1/*.proto
```

## Parameters used by the main project

- `namespace` - Base namespace for generated transport classes
- `output_dir` - Output directory for generated files
- `source_root` - Root directory for handwritten endpoint implementations
- `generate_transport_contracts` - Enable transport contract generation
- `generate_endpoint_validation` - Fail generation when handwritten endpoint implementations are missing or declared incorrectly
- `generate_route_manifest` - Enable route manifest generation

## Output

The main project expects generated files in:

- `gen/Generated/Transport/...`
- `gen/Generated/EndpointBindings/...`
- `gen/Generated/RouteManifest/...`

These files are used together with handwritten endpoint implementations in:

- `src/Platform/Http/Endpoint/...`
