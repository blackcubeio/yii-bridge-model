<?php

declare(strict_types=1);

/**
 * ElasticHydrator.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel;

use Blackcube\BridgeModel\Components\Bridge;
use Yiisoft\Hydrator\ArrayData;
use Yiisoft\Hydrator\DataInterface;
use Yiisoft\Hydrator\Hydrator;
use Yiisoft\Hydrator\HydratorInterface;

/**
 * Hydrator that handles elastic (virtual) properties via __set
 * before delegating to the standard Hydrator for real properties.
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class ElasticHydrator implements HydratorInterface
{
    private HydratorInterface $hydrator;

    public function __construct(?HydratorInterface $hydrator = null)
    {
        $this->hydrator = $hydrator ?? new Hydrator();
    }

    public function hydrate(object $object, array|DataInterface $data = []): void
    {
        if (is_array($data)) {
            $data = new ArrayData($data);
        }

        // Handle elastic properties if object supports them
        if ($object instanceof BridgeFormModel) {
            $properties = $object->getProperties();
            $className = get_class($object);

            foreach ($properties as $name => $property) {
                if ($property instanceof Bridge && $property->isElastic($className)) {
                    $result = $data->getValue($name);
                    if ($result->isResolved()) {
                        $object->{$name} = $result->getValue();
                    }
                }
            }
        }

        // Delegate to wrapped hydrator for real properties
        $this->hydrator->hydrate($object, $data);
    }

    public function create(string $class, array|DataInterface $data = []): object
    {
        $object = $this->hydrator->create($class, $data);

        if ($object instanceof BridgeFormModel) {
            if (is_array($data)) {
                $data = new ArrayData($data);
            }

            $properties = $object->getProperties();
            $className = get_class($object);

            foreach ($properties as $name => $property) {
                if ($property instanceof Bridge && $property->isElastic($className)) {
                    $result = $data->getValue($name);
                    if ($result->isResolved()) {
                        $object->{$name} = $result->getValue();
                    }
                }
            }
        }

        return $object;
    }
}