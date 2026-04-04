# API

## BridgeFormModel

Abstract form model that bridges data between FormModel and ActiveRecord using `#[Bridge]` attributes.

### Constants

| Constant | Description |
|----------|-------------|
| `SCENARIO_DEFAULT` | Default scenario (`'default'`) |
| `ALL_ELASTIC_ATTRIBUTES` | Include all elastic fields in a scenario |
| `NO_ELASTIC_ATTRIBUTES` | Exclude all elastic fields from a scenario |

### Methods

| Method | Description |
|--------|-------------|
| `createFromModel(object $model)` | Factory: create FormModel and populate from AR |
| `initFromModel(object $model)` | Populate from AR |
| `populateModel(object $model)` | Transfer to AR (filtered by active scenario) |
| `loadMultiple(array $models, array $data, ?string $scope)` | Load POST data into multiple indexed models |
| `load(mixed $data, ?string $scope)` | Hydrate from array (POST data) |
| `validate()` | Validate with scenario-filtered rules |
| `setScenario(string $scenario)` | Set active scenario |
| `getScenario()` | Get active scenario |
| `scenarios()` | Define available scenarios and their fields |
| `rules()` | Define validation rules (override in subclass) |
| `getRules()` | Get rules filtered by active scenario + elastic rules |
| `getProperties()` | Get all Bridge components |
| `getData()` | Get all property values as array |

### Elastic support

When the target AR implements `ElasticInterface`, elastic properties from the JSON Schema are auto-discovered and added as virtual properties. They are accessible via `__get`/`__set`/`__isset`/`__call` and provide labels, hints, and placeholders from the schema.

Use `ALL_ELASTIC_ATTRIBUTES` or `NO_ELASTIC_ATTRIBUTES` in scenarios to control elastic field inclusion:

```php
public function scenarios(): array
{
    return [
        self::SCENARIO_DEFAULT => ['name', self::ALL_ELASTIC_ATTRIBUTES],
        'basic' => ['name', self::NO_ELASTIC_ATTRIBUTES],
    ];
}
```

## Attributes

### Bridge

`#[Bridge]` — Declares a property or method as bridged to an ActiveRecord.

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | `?string` | Canonical name (maps to a different target name) |
| `type` | `?string` | Override detected type (e.g. `'DateTimeImmutable'`) |
| `format` | `?string` | Date format for conversion (e.g. `'Y-m-d'`) |
| `property` | `string\|false\|null` | Explicit target property (`false` to skip) |
| `getter` | `?string` | Explicit target getter method |
| `setter` | `?string` | Explicit target setter method |

Target accessor auto-detection order: `setName()` / `getName()` / `isName()` / `$name`.

### IntOrNull

`#[IntOrNull]` — Hydrator attribute that converts resolved values to `int` or `null` (empty string becomes `null`).

## Components

### Bridge (internal)

Manages endpoints for a single bridged property between two classes. Stores getter/setter/property references, types, formats, nullability, elastic flag, meta, and validation rules for each class endpoint.

| Method | Description |
|--------|-------------|
| `get(object $model)` | Read value from model via getter or property |
| `set(object $model, mixed $value)` | Write value to model via setter or property |
| `isTransferable()` | True when at least 2 endpoints are registered |
| `isElastic(string $className)` | True if the property is elastic for the given class |

## Mappers

### Mapper

Handles type conversion during transfer between two models.

Supported conversions:

| From | To | Behavior |
|------|----|----------|
| `DateTimeImmutable` | `string` | Formats using Bridge format (default: `Y-m-d H:i:s`) |
| `string` | `DateTimeImmutable` | Parses string into DateTimeImmutable |
| `BackedEnum` | `string` or `int` | Extracts `->value` |
| Any | Any | Direct assignment (no conversion) |

Null handling: nullable targets receive `null` when the source value is `null` or empty string.

## ElasticHydrator

Decorator for `HydratorInterface` that handles elastic (virtual) properties via `__set` before delegating to the standard Hydrator for real properties.

Used internally by `BridgeFormModel` — registered automatically, no manual setup required.
