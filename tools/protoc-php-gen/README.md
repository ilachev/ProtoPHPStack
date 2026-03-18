# Proto PHP Transport Generator

`protoc-php-gen` is the internal protoc plugin used by this repository to generate server-side transport contracts from protobuf `service/rpc` definitions.

The current production-grade scope is intentionally narrow: transport contracts are the only supported generator module used by the main project path.

## Current role

The supported project path is transport-oriented:

- parse protobuf descriptors;
- generate endpoint interfaces;
- generate HTTP handlers for the runtime adapter.

It is not the canonical path for domain-to-proto mapper generation.

## Product direction

This tool should be treated as a modular PHP code generation platform around protobuf descriptors, not as a one-off script and not as a grab bag of unrelated generators.

Today, only `transport_contracts` is stable and supported.

Future generators are allowed only if they:

- solve a reusable backend problem;
- have an explicit generation flag;
- keep a separate output contract;
- have dedicated tests;
- do not leak business or persistence policy into the codegen layer.

## Technical roadmap

Before adding new generator modules, the tool should be stabilized internally in this order:

1. modular plugin core (`PluginOptions`, `CodeGeneratorModule`, `GeneratorRegistry`);
2. typed descriptor model instead of raw array-driven flow;
3. dedicated type resolver for fully-qualified protobuf types;
4. runtime profile abstraction for transport generation;
5. stronger end-to-end and fixture-based tests.

The detailed product-level rationale is documented in:

- `docs/design/protoc-php-gen-product.md`

## Usage

```bash
protoc -I=./protos/proto \
  --plugin=protoc-gen-php-transport=./tools/protoc-php-gen/bin/protoc-php-gen.php \
  --php-transport_out=namespace=App\\Generated\\Transport,output_dir=gen,generate_transport_contracts=true:. \
  ./protos/proto/app/v1/*.proto
```

## Parameters used by the main project

- `namespace` - Base namespace for generated transport classes
- `output_dir` - Output directory for generated files
- `generate_transport_contracts` - Enable transport contract generation

## Output

The main project expects generated files in:

- `gen/Generated/Transport/...`

These files are used together with handwritten endpoint implementations in:

- `src/Platform/Http/Endpoint/...`
