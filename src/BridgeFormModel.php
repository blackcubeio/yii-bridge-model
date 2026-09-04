<?php

declare(strict_types=1);

/**
 * BridgeFormModel.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel;

use Blackcube\BridgeModel\Attributes\Bridge as BridgeAttribute;
use Blackcube\BridgeModel\Components\Bridge;
use Blackcube\BridgeModel\Mappers\Mapper;
use Blackcube\ActiveRecord\Elastic\ElasticInterface;
use Blackcube\ActiveRecord\Elastic\Validator\JsonSchemaRuleMapper;
use Blackcube\Injector\Injector;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use TypeError;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\FormModel\FormModel;
use Yiisoft\FormModel\NonArrayTypeCaster;
use Yiisoft\Hydrator\Attribute\SkipHydration;
use Yiisoft\Hydrator\Hydrator;
use Yiisoft\Hydrator\TypeCaster\CompositeTypeCaster;
use Yiisoft\Hydrator\TypeCaster\HydratorTypeCaster;
use Yiisoft\Hydrator\TypeCaster\NullTypeCaster;
use Yiisoft\Hydrator\TypeCaster\PhpNativeTypeCaster;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\RulesProviderInterface;

/**
 * Abstract form model for bridging data between models
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BridgeFormModel extends FormModel implements RulesProviderInterface, DataSetInterface
{
    public const SCENARIO_DEFAULT = 'default';
    public const ALL_ELASTIC_ATTRIBUTES = '__all_elastic_attributes__';
    public const NO_ELASTIC_ATTRIBUTES = '__no_elastic_attributes__';

    #[SkipHydration]
    private static ?FormHydrator $formHydrator = null;

    #[SkipHydration]
    private string $scenario = self::SCENARIO_DEFAULT;

    #[SkipHydration]
    private array $properties = [];

    #[SkipHydration]
    private array $initedModels = [];

    #[SkipHydration]
    private array $elasticAttributes = [];

    #[SkipHydration]
    private ?array $elasticAttributeNames = null;

    #[SkipHydration]
    private array $activeScenarioFields = [];

    private static function formHydrator(): FormHydrator
    {
        if (self::$formHydrator === null) {
            self::$formHydrator = Injector::get([
                'class' => FormHydrator::class,
                '__construct()' => [
                    'hydrator' => new ElasticHydrator(
                        new Hydrator(
                            typeCaster: new CompositeTypeCaster(
                                new NullTypeCaster(emptyString: true),
                                new PhpNativeTypeCaster(),
                                new NonArrayTypeCaster(),
                                new HydratorTypeCaster(),
                            ),
                        ),
                    ),
                ],
            ]);
        }
        return self::$formHydrator;
    }

    public static function createFromModel(object $model): static
    {
        $formModel = new static();
        $formModel->ensureInited($model);
        $formModel->transfer($model, $formModel, false);
        return $formModel;
    }

    public static function loadMultiple(array $models, array $data, ?string $scope = null): bool
    {
        if ($models === []) {
            return false;
        }

        $firstModel = reset($models);
        $scope ??= $firstModel->getFormName();

        if ($scope === '' || isset($data[$scope]) === false || is_array($data[$scope]) === false) {
            return false;
        }

        $indexedData = $data[$scope];
        $populated = false;

        foreach ($models as $i => $model) {
            if (isset($indexedData[$i]) === true && is_array($indexedData[$i]) === true) {
                $model->load([$scope => $indexedData[$i]], $scope);
                $populated = true;
            }
        }

        return $populated;
    }

    public function setScenario(string $scenario): static
    {
        $this->scenario = $scenario;
        return $this;
    }

    public function getScenario(): string
    {
        return $this->scenario;
    }

    public function scenarios(): array
    {
        return [
            self::SCENARIO_DEFAULT => array_keys($this->properties),
        ];
    }

    protected function getElasticAttributeNames(): array
    {
        if ($this->elasticAttributeNames === null) {
            $elasticProperties = array_filter(
                $this->properties,
                fn($property) => $property->isElastic(static::class) === true
            );
            $this->elasticAttributeNames = array_keys($elasticProperties);
        }
        return $this->elasticAttributeNames;
    }

    protected function getActiveFields(): array
    {
        $scenario = $this->getScenario();
        if (isset($this->activeScenarioFields[$scenario]) === false) {
            $activeFields = $this->scenarios()[$scenario] ?? [];
            $elasticAttributes = $this->getElasticAttributeNames();

            if (in_array(self::ALL_ELASTIC_ATTRIBUTES, $activeFields) === true) {
                $activeFields = [...$activeFields, ...$elasticAttributes];
            } elseif (in_array(self::NO_ELASTIC_ATTRIBUTES, $activeFields) === true) {
                $activeFields = array_filter($activeFields, fn($field) => in_array($field, $elasticAttributes) === false);
            }

            $this->activeScenarioFields[$scenario] = array_filter($activeFields, fn($field) =>
                $field !== self::ALL_ELASTIC_ATTRIBUTES && $field !== self::NO_ELASTIC_ATTRIBUTES
            );
        }
        return $this->activeScenarioFields[$scenario];
    }

    public function load(mixed $data, ?string $scope = null): bool
    {
        $scope = $scope ?? $this->getFormName();
        $values = ($scope === '') ? $data : ($data[$scope] ?? null);
        if (is_array($values) === false) {
            return false;
        }

        return self::formHydrator()->populate($this, $data, scope: $scope);
    }

    public function validate(): bool
    {
        $result = self::formHydrator()->validate($this);
        $this->processValidationResult($result);
        return $result->isValid();
    }

    public function rules(): array
    {
        return [];
    }

    public function getRules(): array
    {
        $activeFields = $this->getActiveFields();
        $allRules = $this->rules();

        $filteredRules = [];
        foreach ($activeFields as $field) {
            if (isset($allRules[$field]) === true) {
                $filteredRules[$field] = $allRules[$field];
            }
            if (isset($this->properties[$field]) === true) {
                $bridgeRules = $this->properties[$field]->getRules();
                if (empty($bridgeRules) === false) {
                    $filteredRules[$field] = [
                        ...($filteredRules[$field] ?? []),
                        ...$bridgeRules,
                    ];
                }
            }
        }

        return $filteredRules;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getData(): array
    {
        $data = [];

        if (empty($this->properties) === false) {
            foreach ($this->properties as $name => $bridge) {
                $endpoint = $bridge->getEndpoint(static::class);
                if ($endpoint !== null) {
                    $getter = $endpoint['getter'] ?? null;
                    $property = $endpoint['property'] ?? null;
                    $isElastic = $endpoint['isElastic'] ?? false;
                    if ($getter !== null && method_exists($this, $getter) === true) {
                        $data[$name] = $this->$getter();
                    } elseif ($property !== null) {
                        if ($isElastic) {
                            $data[$name] = $this->elasticAttributes[$name] ?? null;
                        } elseif (property_exists($this, $property) === true) {
                            $ref = new ReflectionClass($this);
                            $prop = $ref->getProperty($property);
                            $data[$name] = $prop->getValue($this);
                        }
                    }
                }
            }
            return $data;
        }

        $ref = new ReflectionClass($this);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() === true) {
                continue;
            }
            $attrs = $method->getAttributes(BridgeAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
            if (empty($attrs) === true) {
                continue;
            }

            $methodName = $method->getName();
            if (preg_match('/^(get|is)([A-Z].*)$/', $methodName, $m)) {
                $propName = lcfirst($m[2]);
                $data[$propName] = $this->$methodName();
            }
        }

        return $data;
    }

    private function ensureInited(object $model): void
    {
        $className = get_class($model);
        if (in_array($className, $this->initedModels) === false) {
            $this->initedModels[] = $className;
            $this->properties = $this->buildProperties($model, $this->properties);

            $this->elasticAttributeNames = null;
            $this->activeScenarioFields = [];
        }
    }

    public function initFromModel(object $model): void
    {
        $this->ensureInited($model);
        $this->transfer($model, $this, false);
    }

    public function populateModel(object $model): void
    {
        $this->ensureInited($model);
        $this->transfer($this, $model, true);
    }

    protected function transfer(object $from, object $to, bool $filter = true): void
    {
        if ($filter) {
            $activeFields = $this->getActiveFields();
            $properties = array_filter($this->properties, fn($key) => in_array($key, $activeFields) === true, ARRAY_FILTER_USE_KEY);
        } else {
            $properties = $this->properties;
        }

        foreach ($properties as $bridge) {
            try {
                $mapper = new Mapper($bridge, $from, $to);
                $mapper->transfer();
            } catch (LogicException|RuntimeException|TypeError $e) {
            }
        }
    }

    public function hasProperty(string $property): bool
    {
        if (isset($this->properties[$property]) === true && $this->properties[$property]->isElastic(static::class) === true) {
            return true;
        }

        return parent::hasProperty($property);
    }

    public function getPropertyValue(string $property): mixed
    {
        if (isset($this->properties[$property]) === true && $this->properties[$property]->isElastic(static::class) === true) {
            return $this->elasticAttributes[$property] ?? null;
        }

        return parent::getPropertyValue($property);
    }

    /**
     * Get all elastic property labels from JSON schema titles.
     *
     * @return array<string, string>
     */
    public function getElasticPropertyLabels(): array
    {
        $labels = [];
        foreach ($this->properties as $name => $bridge) {
            if ($bridge->isElastic(static::class) === true) {
                $meta = $bridge->getMeta();
                if (empty($meta['label']) === false) {
                    $labels[$name] = $meta['label'];
                }
            }
        }
        return $labels;
    }

    /**
     * Get all elastic property hints from JSON schema descriptions.
     *
     * @return array<string, string>
     */
    public function getElasticPropertyHints(): array
    {
        $hints = [];
        foreach ($this->properties as $name => $bridge) {
            if ($bridge->isElastic(static::class) === true) {
                $meta = $bridge->getMeta();
                if (empty($meta['hint']) === false) {
                    $hints[$name] = $meta['hint'];
                }
            }
        }
        return $hints;
    }

    /**
     * Get all elastic property placeholders from JSON schema examples.
     *
     * @return array<string, string>
     */
    public function getElasticPropertyPlaceholders(): array
    {
        $placeholders = [];
        foreach ($this->properties as $name => $bridge) {
            if ($bridge->isElastic(static::class) === true) {
                $meta = $bridge->getMeta();
                if (empty($meta['placeholder']) === false) {
                    $placeholders[$name] = $meta['placeholder'];
                }
            }
        }
        return $placeholders;
    }

    public function getPropertyLabel(string $property): string
    {
        if (isset($this->properties[$property]) === true && $this->properties[$property]->isElastic(static::class) === true) {
            $meta = $this->properties[$property]->getMeta();
            if (empty($meta['label']) === false) {
                return $meta['label'];
            }
        }

        return parent::getPropertyLabel($property);
    }

    public function getPropertyHint(string $property): string
    {
        if (isset($this->properties[$property]) === true && $this->properties[$property]->isElastic(static::class) === true) {
            $meta = $this->properties[$property]->getMeta();
            if (empty($meta['hint']) === false) {
                return $meta['hint'];
            }
        }

        return parent::getPropertyHint($property);
    }

    public function getPropertyPlaceholder(string $property): string
    {
        if (isset($this->properties[$property]) === true && $this->properties[$property]->isElastic(static::class) === true) {
            $meta = $this->properties[$property]->getMeta();
            if (empty($meta['placeholder']) === false) {
                return $meta['placeholder'];
            }
        }

        return parent::getPropertyPlaceholder($property);
    }

    public function __get(string $name): mixed
    {
        if (isset($this->properties[$name]) === true && $this->properties[$name]->isElastic(static::class) === true) {
            return $this->elasticAttributes[$name] ?? null;
        }
    }

    public function __set(string $name, mixed $value): void
    {
        if (isset($this->properties[$name]) === true && $this->properties[$name]->isElastic(static::class) === true) {
            $this->elasticAttributes[$name] = $value;
        }
    }

    public function __isset(string $name): bool
    {
        if (isset($this->properties[$name]) === true && $this->properties[$name]->isElastic(static::class) === true) {
            return isset($this->elasticAttributes[$name]) === true;
        }
        return false;
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (preg_match('/^(get|is)([A-Z].*)$/', $name, $m)) {
            $propName = lcfirst($m[2]);
            if (isset($this->properties[$propName]) === true && $this->properties[$propName]->isElastic(static::class) === true) {
                return $this->elasticAttributes[$propName] ?? null;
            }
        }

        if (preg_match('/^set([A-Z].*)$/', $name, $m)) {
            $propName = lcfirst($m[1]);
            if (isset($this->properties[$propName]) === true && $this->properties[$propName]->isElastic(static::class) === true) {
                $this->elasticAttributes[$propName] = $arguments[0] ?? null;
                return null;
            }
        }

        if (method_exists(parent::class, '__call') === true) {
            return parent::__call($name, $arguments);
        }
    }

    private function prepareMeta(object $property): array
    {
        $meta = [
            'field' => $property->field ?? null,
            'label' => $property->title ?? null,
            'hint' => $property->description ?? null,
            'placeholder' => $property->placeholder ?? null,
        ];

        $type = $property->type ?? 'string';
        $format = $property->format ?? null;

        $meta['field'] = match (true) {
            $type === 'string' && in_array($format, ['file', 'files'], true) === true => $format,
            $type === 'string' && in_array($format, ['wysiwyg', 'textarea', 'email', 'date', 'checkbox', 'radio'], true) === true => $format,
            $type === 'string' && in_array($format, ['radiolist', 'radioList', 'dropdownlist', 'dropdownList'], true) === true => $format,
            $type === 'string' && $format === 'date-time' => 'datetime-local',
            $type === 'number', $type === 'integer' => 'number',
            $type === 'boolean' => 'checkbox',
            default => $meta['field'] ?? 'text',
        };

        if (in_array($meta['field'], ['file', 'files'], true) === true) {
            $meta['fileType'] = $property->fileType ?? null;
            if (empty($property->imageWidth) === false) {
                $meta['imageWidth'] = $property->imageWidth;
            }
            if (empty($property->imageHeight) === false) {
                $meta['imageHeight'] = $property->imageHeight;
            }
        }

        if ($property->options instanceof \stdClass === true) {
            $meta['options'] = json_decode(json_encode($property->options), true);
        }

        if (isset($property->items) === true && is_iterable($property->items) === true) {
            $meta['items'] = [];
            foreach ($property->items as $item) {
                $meta['items'][] = [
                    'title' => $item->title ?? null,
                    'value' => $item->value ?? null,
                    'description' => $item->description ?? null,
                ];
            }
        }

        return $meta;
    }

    private function buildProperties(object $targetModel, array $properties = []): array
    {
        $sourceRef = new ReflectionClass($this);
        $sourceClassName = get_class($this);
        $targetClassName = get_class($targetModel);

        foreach ($sourceRef->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() === false) {
                foreach ($property->getAttributes(BridgeAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attr) {
                    $instance = $attr->newInstance();
                    $sourceName = $property->getName();
                    $name = $instance->name ?? $sourceName;

                    $derivedGetter = 'get'.ucfirst($name);
                    $derivedIser = 'is'.ucfirst($name);
                    $targetGetter = $instance->getter;
                    $derivedSetter = 'set'.ucfirst($name);
                    $targetSetter = $instance->setter;
                    $derivedProperty = $name;
                    $targetProperty = $instance->property;

                    if ($targetSetter === null && method_exists($targetModel, $derivedSetter) === true) {
                        $targetSetter = $derivedSetter;
                    } elseif ($targetSetter === null && $targetProperty === null && property_exists($targetModel, $derivedProperty) === true) {
                        $targetProperty = $derivedProperty;
                    }

                    if ($targetGetter === null && method_exists($targetModel, $derivedGetter) === true) {
                        $targetGetter = $derivedGetter;
                    } elseif ($targetGetter === null && method_exists($targetModel, $derivedIser) === true) {
                        $targetGetter = $derivedIser;
                    } elseif ($targetGetter === null && $targetProperty === null && property_exists($targetModel, $derivedProperty) === true) {
                        $targetProperty = $derivedProperty;
                    }

                    if ($targetGetter !== null || $targetSetter !== null || $targetProperty !== null) {
                        if (isset($properties[$name]) === false) {
                            $properties[$name] = new Bridge($name);
                        }
                        $properties[$name]->setEndpoint(
                            className: $sourceClassName,
                            getter: null,
                            setter: null,
                            property: $sourceName,
                            type: $instance->type ?? ($property->getType() instanceof \ReflectionNamedType ? $property->getType()->getName() : null),
                            format: $instance->format,
                            isNullable: $property->getType()?->allowsNull() ?? true,
                        );

                        $targetType = $instance->type;
                        $targetNullable = false;
                        $targetRef = new ReflectionClass($targetModel);
                        if ($targetType === null && $targetGetter !== null) {
                            $getterMethod = $targetRef->getMethod($targetGetter);
                            $returnType = $getterMethod->getReturnType();
                            $targetType = $returnType instanceof \ReflectionNamedType === true ? $returnType->getName() : null;
                            $targetNullable = $returnType?->allowsNull() ?? false;
                        } elseif ($targetType === null && $targetSetter !== null) {
                            $setterMethod = $targetRef->getMethod($targetSetter);
                            $params = $setterMethod->getParameters();
                            if (count($params) > 0) {
                                $paramType = $params[0]->getType();
                                $targetType = $paramType instanceof \ReflectionNamedType === true ? $paramType->getName() : null;
                                $targetNullable = $paramType?->allowsNull() ?? false;
                            }
                        } elseif ($targetType === null && $targetProperty !== null) {
                            $targetProp = $targetRef->getProperty($targetProperty);
                            $propType = $targetProp->getType();
                            $targetType = $propType instanceof \ReflectionNamedType === true ? $propType->getName() : null;
                            $targetNullable = $propType?->allowsNull() ?? false;
                        }

                        $properties[$name]->setEndpoint(
                            className: $targetClassName,
                            getter: $targetGetter,
                            setter: $targetSetter,
                            property: $targetProperty,
                            type: $targetType,
                            format: $instance->format,
                            isNullable: $targetNullable,
                        );
                    }
                }
            }
        }

        foreach ($sourceRef->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() === false) {
                foreach ($method->getAttributes(BridgeAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attr) {
                    $instance = $attr->newInstance();
                    $methodName = $method->getName();

                    preg_match('/^(get|set|is)([A-Z].*)$/', $methodName, $m);
                    $baseName = isset($m[2]) === true ? lcfirst($m[2]) : $methodName;
                    $isGetter = isset($m[1]) === true && ($m[1] === 'get' || $m[1] === 'is');

                    $name = $instance->name ?? $baseName;
                    $derivedProperty = $name;
                    $targetProperty = $instance->property;

                    if ($isGetter) {
                        $derivedSetter = 'set'.ucfirst($name);
                        $targetSetter = $instance->setter;

                        if ($targetSetter === null && method_exists($targetModel, $derivedSetter) === true) {
                            $targetSetter = $derivedSetter;
                        } elseif ($targetSetter === null && $targetProperty === null && property_exists($targetModel, $derivedProperty) === true) {
                            $targetProperty = $derivedProperty;
                        }

                        if ($targetSetter !== null || $targetProperty !== null) {
                            if (isset($properties[$name]) === false) {
                                $properties[$name] = new Bridge($name);
                            }
                            $properties[$name]->setEndpoint(
                                className: $sourceClassName,
                                getter: $methodName,
                                setter: null,
                                property: null,
                                type: $instance->type ?? ($method->getReturnType() instanceof \ReflectionNamedType ? $method->getReturnType()->getName() : null),
                                format: $instance->format,
                                isNullable: $method->getReturnType()?->allowsNull() ?? true,
                            );

                            $targetType = $instance->type;
                            $targetNullable = false;
                            $targetRef = new ReflectionClass($targetModel);
                            if ($targetType === null && $targetSetter !== null) {
                                $setterMethod = $targetRef->getMethod($targetSetter);
                                $params = $setterMethod->getParameters();
                                if (count($params) > 0) {
                                    $paramType = $params[0]->getType();
                                    $targetType = $paramType instanceof \ReflectionNamedType === true ? $paramType->getName() : null;
                                    $targetNullable = $paramType?->allowsNull() ?? false;
                                }
                            } elseif ($targetType === null && $targetProperty !== null) {
                                $targetProp = $targetRef->getProperty($targetProperty);
                                $propType = $targetProp->getType();
                                $targetType = $propType instanceof \ReflectionNamedType === true ? $propType->getName() : null;
                                $targetNullable = $propType?->allowsNull() ?? false;
                            }

                            $properties[$name]->setEndpoint(
                                className: $targetClassName,
                                getter: null,
                                setter: $targetSetter,
                                property: $targetProperty,
                                type: $targetType,
                                format: $instance->format,
                                isNullable: $targetNullable,
                            );
                        }
                    } else {
                        $derivedGetter = 'get'.ucfirst($name);
                        $derivedIser = 'is'.ucfirst($name);
                        $targetGetter = $instance->getter;

                        if ($targetGetter === null && method_exists($targetModel, $derivedGetter) === true) {
                            $targetGetter = $derivedGetter;
                        } elseif ($targetGetter === null && method_exists($targetModel, $derivedIser) === true) {
                            $targetGetter = $derivedIser;
                        } elseif ($targetGetter === null && $targetProperty === null && property_exists($targetModel, $derivedProperty) === true) {
                            $targetProperty = $derivedProperty;
                        }

                        if ($targetGetter !== null || $targetProperty !== null) {
                            if (isset($properties[$name]) === false) {
                                $properties[$name] = new Bridge($name);
                            }
                            $properties[$name]->setEndpoint(
                                className: $sourceClassName,
                                getter: null,
                                setter: $methodName,
                                property: null,
                                type: $instance->type,
                                format: $instance->format,
                                isNullable: false,
                            );

                            $targetType = $instance->type;
                            $targetNullable = false;
                            $targetRef = new ReflectionClass($targetModel);
                            if ($targetType === null && $targetGetter !== null) {
                                $getterMethod = $targetRef->getMethod($targetGetter);
                                $returnType = $getterMethod->getReturnType();
                                $targetType = $returnType instanceof \ReflectionNamedType === true ? $returnType->getName() : null;
                                $targetNullable = $returnType?->allowsNull() ?? false;
                            } elseif ($targetType === null && $targetProperty !== null) {
                                $targetProp = $targetRef->getProperty($targetProperty);
                                $propType = $targetProp->getType();
                                $targetType = $propType instanceof \ReflectionNamedType === true ? $propType->getName() : null;
                                $targetNullable = $propType?->allowsNull() ?? false;
                            }

                            $properties[$name]->setEndpoint(
                                className: $targetClassName,
                                getter: $targetGetter,
                                setter: null,
                                property: $targetProperty,
                                type: $targetType,
                                format: $instance->format,
                                isNullable: $targetNullable,
                            );
                        }
                    }
                }
            }
        }

        if ($targetModel instanceof ElasticInterface === true) {
            $schema = $targetModel->getSchema();
            if ($schema !== null) {
                $requiredProperties = $schema->required ?? [];
                $mapper = new JsonSchemaRuleMapper();

                foreach ($schema->getProperties() as $attribute => $property) {
                    if (isset($properties[$attribute]) === true && $properties[$attribute]->isElastic($sourceClassName) === false) {
                        throw new LogicException(sprintf('Elastic property %s conflicts with existing property in form model.', $attribute));
                    }

                    if (isset($properties[$attribute]) === false) {
                        $properties[$attribute] = new Bridge($attribute);

                        $properties[$attribute]->setEndpoint(
                            className: $sourceClassName,
                            property: $attribute,
                            type: $property->type ?? 'string',
                            format: $property->format ?? null,
                            isNullable: true,
                            isElastic: true,
                        );

                        $properties[$attribute]->setMeta($this->prepareMeta($property));
                    }

                    $properties[$attribute]->setEndpoint(
                        className: $targetClassName,
                        property: $attribute,
                        type: $property->type ?? 'string',
                        format: $property->format ?? null,
                        isNullable: true,
                        isElastic: true,
                    );

                    $propertyRules = $mapper->map($attribute, $property, $requiredProperties);
                    if (empty($propertyRules) === false) {
                        $existingRules = $properties[$attribute]->getRules();
                        $properties[$attribute]->setRules([...$existingRules, ...$propertyRules]);
                    }
                }
            }
        }

        return $properties;
    }
}