<?php

declare(strict_types=1);

/**
 * BridgeFormModel.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel;

use Blackcube\BridgeModel\Attributes\Bridge as BridgeAttribute;
use Blackcube\BridgeModel\Components\Bridge;
use Blackcube\BridgeModel\Mappers\Mapper;
use Blackcube\ActiveRecord\Elastic\ElasticInterface;
use Blackcube\ActiveRecord\Elastic\Validator\JsonSchemaRuleMapper;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use TypeError;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Hydrator\Attribute\SkipHydration;
use Yiisoft\Hydrator\Hydrator;
use Yiisoft\Validator\DataSetInterface;
use Yiisoft\Validator\RulesProviderInterface;
use Yiisoft\Validator\Validator;

/**
 * Abstract form model for bridging data between models
 *
 * @copyright 2010-2026 Blackcube
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
            self::$formHydrator = new FormHydrator(
                new ElasticHydrator(new Hydrator()),
                new Validator()
            );
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

        if ($scope === '' || !isset($data[$scope]) || !is_array($data[$scope])) {
            return false;
        }

        $indexedData = $data[$scope];
        $populated = false;

        foreach ($models as $i => $model) {
            if (isset($indexedData[$i]) && is_array($indexedData[$i])) {
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
            $this->elasticAttributeNames = array_keys(array_filter(
                $this->properties,
                fn($property) => $property->isElastic(static::class)
            ));
        }
        return $this->elasticAttributeNames;
    }

    protected function getActiveFields(): array
    {
        $scenario = $this->getScenario();
        if (!isset($this->activeScenarioFields[$scenario])) {
            $activeFields = $this->scenarios()[$scenario] ?? [];
            $elasticAttributes = $this->getElasticAttributeNames();

            if (in_array(self::ALL_ELASTIC_ATTRIBUTES, $activeFields)) {
                $activeFields = [...$activeFields, ...$elasticAttributes];
            } elseif (in_array(self::NO_ELASTIC_ATTRIBUTES, $activeFields)) {
                $activeFields = array_filter($activeFields, fn($field) => !in_array($field, $elasticAttributes));
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
        if (!is_array($values)) {
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
            if (isset($allRules[$field])) {
                $filteredRules[$field] = $allRules[$field];
            }
            if (isset($this->properties[$field])) {
                $bridgeRules = $this->properties[$field]->getRules();
                if (!empty($bridgeRules)) {
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

        // If properties were built via ensureInited(), use them
        if (!empty($this->properties)) {
            foreach ($this->properties as $name => $bridge) {
                $endpoint = $bridge->getEndpoint(static::class);
                if ($endpoint !== null) {
                    $getter = $endpoint['getter'] ?? null;
                    $property = $endpoint['property'] ?? null;
                    $isElastic = $endpoint['isElastic'] ?? false;
                    if ($getter !== null && method_exists($this, $getter)) {
                        $data[$name] = $this->$getter();
                    } elseif ($property !== null) {
                        if ($isElastic) {
                            $data[$name] = $this->elasticAttributes[$name] ?? null;
                        } elseif (property_exists($this, $property)) {
                            $ref = new ReflectionClass($this);
                            $prop = $ref->getProperty($property);
                            $data[$name] = $prop->getValue($this);
                        }
                    }
                }
            }
            return $data;
        }

        // Fallback: scan methods with #[Bridge] directly (for standalone forms)
        $ref = new ReflectionClass($this);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }
            $attrs = $method->getAttributes(BridgeAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
            if (empty($attrs)) {
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
        if (!in_array($className, $this->initedModels)) {
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
            $properties = array_filter($this->properties, fn($key) => in_array($key, $activeFields), ARRAY_FILTER_USE_KEY);
        } else {
            $properties = $this->properties;
        }

        foreach ($properties as $bridge) {
            try {
                $mapper = new Mapper($bridge, $from, $to);
                $mapper->transfer();
            } catch (LogicException|RuntimeException|TypeError $e) {
                // skip
            }
        }
    }

    public function hasProperty(string $property): bool
    {
        // Check if it's an elastic property
        if (isset($this->properties[$property]) && $this->properties[$property]->isElastic(static::class)) {
            return true;
        }

        return parent::hasProperty($property);
    }

    public function getPropertyValue(string $property): mixed
    {
        // Check if it's an elastic property
        if (isset($this->properties[$property]) && $this->properties[$property]->isElastic(static::class)) {
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
            if ($bridge->isElastic(static::class)) {
                $meta = $bridge->getMeta();
                if (!empty($meta['label'])) {
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
            if ($bridge->isElastic(static::class)) {
                $meta = $bridge->getMeta();
                if (!empty($meta['hint'])) {
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
            if ($bridge->isElastic(static::class)) {
                $meta = $bridge->getMeta();
                if (!empty($meta['placeholder'])) {
                    $placeholders[$name] = $meta['placeholder'];
                }
            }
        }
        return $placeholders;
    }

    public function getPropertyLabel(string $property): string
    {
        if (isset($this->properties[$property]) && $this->properties[$property]->isElastic(static::class)) {
            $meta = $this->properties[$property]->getMeta();
            if (!empty($meta['label'])) {
                return $meta['label'];
            }
        }

        return parent::getPropertyLabel($property);
    }

    public function getPropertyHint(string $property): string
    {
        if (isset($this->properties[$property]) && $this->properties[$property]->isElastic(static::class)) {
            $meta = $this->properties[$property]->getMeta();
            if (!empty($meta['hint'])) {
                return $meta['hint'];
            }
        }

        return parent::getPropertyHint($property);
    }

    public function getPropertyPlaceholder(string $property): string
    {
        if (isset($this->properties[$property]) && $this->properties[$property]->isElastic(static::class)) {
            $meta = $this->properties[$property]->getMeta();
            if (!empty($meta['placeholder'])) {
                return $meta['placeholder'];
            }
        }

        return parent::getPropertyPlaceholder($property);
    }

    public function __get(string $name): mixed
    {
        if (isset($this->properties[$name]) && $this->properties[$name]->isElastic(static::class)) {
            return $this->elasticAttributes[$name] ?? null;
        }
    }

    public function __set(string $name, mixed $value): void
    {
        if (isset($this->properties[$name]) && $this->properties[$name]->isElastic(static::class)) {
            $this->elasticAttributes[$name] = $value;
        }
    }

    public function __isset(string $name): bool
    {
        if (isset($this->properties[$name]) && $this->properties[$name]->isElastic(static::class)) {
            return isset($this->elasticAttributes[$name]);
        }
        return false;
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (preg_match('/^(get|is)([A-Z].*)$/', $name, $m)) {
            $propName = lcfirst($m[2]);
            if (isset($this->properties[$propName]) && $this->properties[$propName]->isElastic(static::class)) {
                return $this->elasticAttributes[$propName] ?? null;
            }
        }

        if (preg_match('/^set([A-Z].*)$/', $name, $m)) {
            $propName = lcfirst($m[1]);
            if (isset($this->properties[$propName]) && $this->properties[$propName]->isElastic(static::class)) {
                $this->elasticAttributes[$propName] = $arguments[0] ?? null;
                return null;
            }
        }

        if (method_exists(parent::class, '__call')) {
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
            $type === 'string' && in_array($format, ['file', 'files'], true) => $format,
            $type === 'string' && in_array($format, ['wysiwyg', 'textarea', 'email', 'date', 'checkbox', 'radio'], true) => $format,
            $type === 'string' && in_array($format, ['radiolist', 'radioList', 'dropdownlist', 'dropdownList'], true) => $format,
            $type === 'string' && $format === 'date-time' => 'datetime-local',
            $type === 'number', $type === 'integer' => 'number',
            $type === 'boolean' => 'checkbox',
            default => $meta['field'] ?? 'text',
        };

        if (in_array($meta['field'], ['file', 'files'], true)) {
            $meta['fileType'] = $property->fileType ?? null;
            if (!empty($property->imageWidth)) {
                $meta['imageWidth'] = $property->imageWidth;
            }
            if (!empty($property->imageHeight)) {
                $meta['imageHeight'] = $property->imageHeight;
            }
        }

        if ($property->options instanceof \stdClass) {
            $meta['options'] = json_decode(json_encode($property->options), true);
        }

        if (isset($property->items) && is_iterable($property->items)) {
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

        // 1. Scan properties
        foreach ($sourceRef->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                foreach ($property->getAttributes(BridgeAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attr) {
                    $instance = $attr->newInstance();
                    $sourceName = $property->getName();
                    $name = $instance->name ?? $sourceName;

                    $derivedGetter = 'get' . ucfirst($name);
                    $derivedIser = 'is' . ucfirst($name);
                    $targetGetter = $instance->getter;
                    $derivedSetter = 'set' . ucfirst($name);
                    $targetSetter = $instance->setter;
                    $derivedProperty = $name;
                    $targetProperty = $instance->property;

                    if ($targetSetter === null && method_exists($targetModel, $derivedSetter)) {
                        $targetSetter = $derivedSetter;
                    } elseif ($targetSetter === null && $targetProperty === null && property_exists($targetModel, $derivedProperty)) {
                        $targetProperty = $derivedProperty;
                    }

                    if ($targetGetter === null && method_exists($targetModel, $derivedGetter)) {
                        $targetGetter = $derivedGetter;
                    } elseif ($targetGetter === null && method_exists($targetModel, $derivedIser)) {
                        $targetGetter = $derivedIser;
                    } elseif ($targetGetter === null && $targetProperty === null && property_exists($targetModel, $derivedProperty)) {
                        $targetProperty = $derivedProperty;
                    }

                    if ($targetGetter !== null || $targetSetter !== null || $targetProperty !== null) {
                        if (!isset($properties[$name])) {
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

                        // Detect target type from reflection
                        $targetType = $instance->type;
                        $targetNullable = false;
                        $targetRef = new ReflectionClass($targetModel);
                        if ($targetType === null && $targetGetter !== null) {
                            $getterMethod = $targetRef->getMethod($targetGetter);
                            $returnType = $getterMethod->getReturnType();
                            $targetType = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : null;
                            $targetNullable = $returnType?->allowsNull() ?? false;
                        } elseif ($targetType === null && $targetSetter !== null) {
                            $setterMethod = $targetRef->getMethod($targetSetter);
                            $params = $setterMethod->getParameters();
                            if (count($params) > 0) {
                                $paramType = $params[0]->getType();
                                $targetType = $paramType instanceof \ReflectionNamedType ? $paramType->getName() : null;
                                $targetNullable = $paramType?->allowsNull() ?? false;
                            }
                        } elseif ($targetType === null && $targetProperty !== null) {
                            $targetProp = $targetRef->getProperty($targetProperty);
                            $propType = $targetProp->getType();
                            $targetType = $propType instanceof \ReflectionNamedType ? $propType->getName() : null;
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

        // 2. Scan methods
        foreach ($sourceRef->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->isStatic()) {
                foreach ($method->getAttributes(BridgeAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attr) {
                    $instance = $attr->newInstance();
                    $methodName = $method->getName();

                    preg_match('/^(get|set|is)([A-Z].*)$/', $methodName, $m);
                    $baseName = isset($m[2]) ? lcfirst($m[2]) : $methodName;
                    $isGetter = isset($m[1]) && ($m[1] === 'get' || $m[1] === 'is');

                    $name = $instance->name ?? $baseName;
                    $derivedProperty = $name;
                    $targetProperty = $instance->property;

                    if ($isGetter) {
                        $derivedSetter = 'set' . ucfirst($name);
                        $targetSetter = $instance->setter;

                        if ($targetSetter === null && method_exists($targetModel, $derivedSetter)) {
                            $targetSetter = $derivedSetter;
                        } elseif ($targetSetter === null && $targetProperty === null && property_exists($targetModel, $derivedProperty)) {
                            $targetProperty = $derivedProperty;
                        }

                        if ($targetSetter !== null || $targetProperty !== null) {
                            if (!isset($properties[$name])) {
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

                            // Detect target type from reflection
                            $targetType = $instance->type;
                            $targetNullable = false;
                            $targetRef = new ReflectionClass($targetModel);
                            if ($targetType === null && $targetSetter !== null) {
                                $setterMethod = $targetRef->getMethod($targetSetter);
                                $params = $setterMethod->getParameters();
                                if (count($params) > 0) {
                                    $paramType = $params[0]->getType();
                                    $targetType = $paramType instanceof \ReflectionNamedType ? $paramType->getName() : null;
                                    $targetNullable = $paramType?->allowsNull() ?? false;
                                }
                            } elseif ($targetType === null && $targetProperty !== null) {
                                $targetProp = $targetRef->getProperty($targetProperty);
                                $propType = $targetProp->getType();
                                $targetType = $propType instanceof \ReflectionNamedType ? $propType->getName() : null;
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
                        $derivedGetter = 'get' . ucfirst($name);
                        $derivedIser = 'is' . ucfirst($name);
                        $targetGetter = $instance->getter;

                        if ($targetGetter === null && method_exists($targetModel, $derivedGetter)) {
                            $targetGetter = $derivedGetter;
                        } elseif ($targetGetter === null && method_exists($targetModel, $derivedIser)) {
                            $targetGetter = $derivedIser;
                        } elseif ($targetGetter === null && $targetProperty === null && property_exists($targetModel, $derivedProperty)) {
                            $targetProperty = $derivedProperty;
                        }

                        if ($targetGetter !== null || $targetProperty !== null) {
                            if (!isset($properties[$name])) {
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

                            // Detect target type from reflection
                            $targetType = $instance->type;
                            $targetNullable = false;
                            $targetRef = new ReflectionClass($targetModel);
                            if ($targetType === null && $targetGetter !== null) {
                                $getterMethod = $targetRef->getMethod($targetGetter);
                                $returnType = $getterMethod->getReturnType();
                                $targetType = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : null;
                                $targetNullable = $returnType?->allowsNull() ?? false;
                            } elseif ($targetType === null && $targetProperty !== null) {
                                $targetProp = $targetRef->getProperty($targetProperty);
                                $propType = $targetProp->getType();
                                $targetType = $propType instanceof \ReflectionNamedType ? $propType->getName() : null;
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

        // 3. Scan elastic properties from target model (fullbridge)
        if ($targetModel instanceof ElasticInterface) {
            $schema = $targetModel->getSchema();
            if ($schema !== null) {
                $requiredProperties = $schema->required ?? [];
                $mapper = new JsonSchemaRuleMapper();

                foreach ($schema->getProperties() as $attribute => $property) {
                    if (isset($properties[$attribute]) && !$properties[$attribute]->isElastic($sourceClassName)) {
                        throw new LogicException(sprintf('Elastic property %s conflicts with existing property in form model.', $attribute));
                    }

                    if (!isset($properties[$attribute])) {
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
                    if (!empty($propertyRules)) {
                        $existingRules = $properties[$attribute]->getRules();
                        $properties[$attribute]->setRules([...$existingRules, ...$propertyRules]);
                    }
                }
            }
        }

        return $properties;
    }
}