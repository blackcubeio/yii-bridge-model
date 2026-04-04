# Blackcube Yii Bridge Model

> **⚠️ Blackcube Warning**
>
> This is not auto-hydration. It's a declared, typed, scenario-filtered bridge between FormModel and ActiveRecord.
>
> You put `#[Bridge]` on what transfers in FormModel. You control the direction, the format, the scope. Nothing moves without your say-so.

Bidirectional data bridge between FormModel and ActiveRecord for Yii framework.

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/blackcube/yii-bridge-model.svg)](https://packagist.org/packages/blackcube/yii-bridge-model)
[![Warning](https://img.shields.io/badge/Blackcube-Warning-orange)](BLACKCUBE_WARNING.md)

## Installation
```bash
composer require blackcube/yii-bridge-model
```

## Why Bridge?

| Approach | Problem |
|----------|---------|
| Manual mapping | Boilerplate hell, copy-paste errors |
| Auto-hydration | Breaks on type mismatch, no control |
| **Bridge** | None of the above |

**You declare mappings with attributes.** Bridge handles the rest.

**Type conversion is automatic.** `DateTimeImmutable` ↔ `string` just works.

**Scenarios filter what transfers.** Edit form ≠ Create form ≠ API form.

**Elastic properties are transparent.** Dynamic JSON Schema fields work like regular properties.

## How It Works

### The Bridge Attribute
```php
#[Bridge]                           // Auto-detect target getter/setter
#[Bridge(name: 'title')]            // Map to different name
#[Bridge(format: 'Y-m-d')]          // Date format for conversion
#[Bridge(getter: 'getX', setter: 'setX')]  // Explicit target methods
```

### Data Flow
```
┌─────────────────────────────────────────────────────────────────┐
│                         DATA FLOW                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  AR (native types)  ──────────────────►  FormModel (strings)     │
│                      createFromModel()                           │
│                      initFromModel()                             │
│                                                                  │
│  FormModel (strings) ──────────────────►  AR (native types)      │
│                       populateModel()                            │
│                                                                  │
│  #[Bridge] = mapping instruction                                 │
│  Mapper = automatic DateTimeImmutable ↔ string conversion        │
│  Scenarios = field filtering                                     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Quick Start

### 1. Create your ActiveRecord
```php
<?php

declare(strict_types=1);

namespace App\Model;

use Yiisoft\ActiveRecord\ActiveRecord;

class Product extends ActiveRecord
{
    protected ?int $id = null;
    protected string $name = '';
    protected ?float $price = null;
    protected bool $active = true;
    protected ?DateTimeImmutable $publishedAt = null;

    public function tableName(): string
    {
        return 'products';
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $price): void { $this->price = $price; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; }
    public function getPublishedAt(): ?DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?DateTimeImmutable $publishedAt): void { $this->publishedAt = $publishedAt; }
}
```

### 2. Create your FormModel
```php
<?php

declare(strict_types=1);

namespace App\Form;

use Blackcube\BridgeModel\Attributes\Bridge;
use Blackcube\BridgeModel\BridgeFormModel;

class ProductForm extends BridgeFormModel
{
    #[Bridge]
    public ?int $id = null;

    #[Bridge]
    public string $name = '';

    #[Bridge]
    public ?float $price = null;

    #[Bridge]
    public bool $active = true;

    #[Bridge(format: 'Y-m-d')]
    public ?string $publishedAt = null;

    public function rules(): array
    {
        return [
            'name' => [new Required(), new Length(min: 3, max: 255)],
            'price' => [new Number(min: 0)],
        ];
    }
}
```

## Usage

### Load from ActiveRecord
```php
// Factory method — creates and populates
$form = ProductForm::createFromModel($product);

// Or manually
$form = new ProductForm();
$form->initFromModel($product);
```

### Save to ActiveRecord
```php
// Load POST data
$form->load($request->getParsedBody());

// Validate
if ($form->validate()) {
    // Transfer to AR
    $form->populateModel($product);
    $product->save();
}
```

### Scenarios

Control which fields are active for validation and transfer:
```php
class ProductForm extends BridgeFormModel
{
    public const SCENARIO_CREATE = 'create';
    public const SCENARIO_EDIT = 'edit';

    #[Bridge]
    public ?int $id = null;

    #[Bridge]
    public string $name = '';

    #[Bridge]
    public ?float $price = null;

    public function scenarios(): array
    {
        return [
            self::SCENARIO_CREATE => ['name', 'price'],
            self::SCENARIO_EDIT => ['name', 'price', 'id'],
        ];
    }
}

// Usage
$form = ProductForm::createFromModel($product);
$form->setScenario(ProductForm::SCENARIO_EDIT);
```

### Date Conversion

`DateTimeImmutable` ↔ `string` conversion is automatic when you specify a format:
```php
// AR has DateTimeImmutable
protected ?DateTimeImmutable $publishedAt = null;

// FormModel has string with format
#[Bridge(format: 'Y-m-d')]
public ?string $publishedAt = null;

// Transfer AR → FormModel: DateTimeImmutable becomes "2025-02-01"
// Transfer FormModel → AR: "2025-02-01" becomes DateTimeImmutable
```

### Type Override

When reflection can't detect the type (mixed, inherited, etc.):
```php
#[Bridge(type: 'DateTimeImmutable', format: 'Y-m-d')]
public ?string $createdAt = null;
```

## Elastic Integration

Bridge works transparently with [elastic](https://github.com/blackcubeio/elastic) dynamic properties.

### AR with Elastic
```php
class Product extends ActiveRecord implements ElasticInterface
{
    use MagicComposeActiveRecordTrait;
    use ElasticTrait;
    
    // Regular properties...
}
```

### FormModel with Elastic

Elastic properties are auto-discovered from the JSON Schema:
```php
class ProductForm extends BridgeFormModel
{
    #[Bridge]
    public string $name = '';

    // Elastic properties (sku, color, size) are added automatically
    // from the AR's JSON Schema when you call initFromModel()

    public function scenarios(): array
    {
        return [
            self::SCENARIO_DEFAULT => ['name', self::ALL_ELASTIC_ATTRIBUTES],
            'basic' => ['name', self::NO_ELASTIC_ATTRIBUTES],
        ];
    }
}
```

### Elastic Constants

| Constant | Effect |
|----------|--------|
| `ALL_ELASTIC_ATTRIBUTES` | Include all elastic fields in scenario |
| `NO_ELASTIC_ATTRIBUTES` | Exclude all elastic fields from scenario |

## Bridge Resolution

Bridge auto-detects target accessors in this order:

| Source | Target Detection Order |
|--------|------------------------|
| Property `$name` | `setName()` → `getName()`/`isName()` → `$name` |
| Getter `getName()` | `setName()` → `$name` |
| Setter `setName()` | `getName()`/`isName()` → `$name` |

Explicit attributes always win:
```php
#[Bridge(getter: 'fetchTitle', setter: 'storeTitle')]
public string $name = '';
```

## API Reference

### BridgeFormModel

| Method | Description |
|--------|-------------|
| `createFromModel($model)` | Factory: create and populate from AR |
| `initFromModel($model)` | Populate from AR |
| `populateModel($model)` | Transfer to AR (filtered by scenario) |
| `load($data, $scope)` | Hydrate from array (POST data) |
| `validate()` | Validate with filtered rules |
| `setScenario($scenario)` | Set active scenario |
| `getScenario()` | Get active scenario |
| `getProperties()` | Get all Bridge components |
| `getRules()` | Get rules filtered by scenario |

### Bridge Attribute

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | `?string` | Canonical name (for fusion) |
| `type` | `?string` | Override detected type |
| `format` | `?string` | Date format for conversion |
| `property` | `?string` | Explicit target property |
| `getter` | `?string` | Explicit target getter |
| `setter` | `?string` | Explicit target setter |

## Let's be honest

**No magic**

Bridge doesn't guess. If you don't put `#[Bridge]`, the property is ignored.

**One AR per FormModel (usually)**

Multi-AR forms are possible (call `initFromModel()` multiple times) but think twice. Complex forms often mean complex problems.

**Elastic requires ElasticHydrator**

Yii's default Hydrator doesn't handle `elastics`. Bridge includes `ElasticHydrator` that does.

## Rules

1. **Always use `#[Bridge]`** — unmarked properties don't transfer
2. **Specify format for dates** — or get "Y-m-d H:i:s" default
3. **Use scenarios** — don't transfer everything everywhere
4. **Always add rules** — Bridge doesn't transfer Form -> AR if property has no validation rules
4. **Validate before populate** — Bridge doesn't validate, it transfers

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).

## Author

Philippe Gaultier <philippe@blackcube.io>