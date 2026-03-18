# Proto PHP Transport Generator

`protoc-php-gen` is the internal protoc plugin used by this repository to generate server-side transport contracts from protobuf `service/rpc` definitions.

## Current role

The supported project path is transport-oriented:

- parse protobuf descriptors;
- generate endpoint interfaces;
- generate HTTP handlers for the runtime adapter.

It is not the canonical path for domain-to-proto mapper generation.

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
