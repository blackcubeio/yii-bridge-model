<?php

declare(strict_types=1);

/**
 * ElasticHydrator.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class ElasticHydrator implements HydratorInterface
{
    private HydratorInterface $hydrator;

    public function __construct(?HydratorInterface $hydrator = null)
    {
        $this->hydrator = $hydrator ?? new Hydrator();
    }

    public function hydrate(object $object, array|DataInterface $data = []): void
    {
        if (is_array($data) === true) {
            $data = new ArrayData($data);
        }

        if ($object instanceof BridgeFormModel === true) {
            $properties = $object->getProperties();
            $className = get_class($object);

            foreach ($properties as $name => $property) {
                if ($property instanceof Bridge === true && $property->isElastic($className) === true) {
                    $result = $data->getValue($name);
                    if ($result->isResolved() === true) {
                        $object->{$name} = $result->getValue();
                    }
                }
            }
        }

        $this->hydrator->hydrate($object, $data);
    }

    public function create(string $class, array|DataInterface $data = []): object
    {
        $object = $this->hydrator->create($class, $data);

        if ($object instanceof BridgeFormModel === true) {
            if (is_array($data) === true) {
                $data = new ArrayData($data);
            }

            $properties = $object->getProperties();
            $className = get_class($object);

            foreach ($properties as $name => $property) {
                if ($property instanceof Bridge === true && $property->isElastic($className) === true) {
                    $result = $data->getValue($name);
                    if ($result->isResolved() === true) {
                        $object->{$name} = $result->getValue();
                    }
                }
            }
        }

        return $object;
    }
}