<?php

declare(strict_types=1);

/**
 * Mapper.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel\Mappers;

use Blackcube\BridgeModel\Components\Bridge;
use DateTimeImmutable;
use RuntimeException;

/**
 * Mapper for bridge transfer
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class Mapper
{
    public function __construct(
        private Bridge $bridge,
        private object $from,
        private object $to,
    ) {
    }

    public function transfer(): void
    {
        $value = $this->bridge->get($this->from);

        $fromType = $this->bridge->getType($this->from);
        $toType = $this->bridge->getType($this->to);
        $toNullable = $this->bridge->isNullable($this->to);

        if ($fromType === 'DateTimeImmutable' && $toType === 'string') {
            if ($value === null || (is_string($value) === true && $value === '')) {
                $value = $toNullable ? null : throw new RuntimeException('Non nullable date property is null');
            } elseif ($value instanceof DateTimeImmutable === true) {
                $format = $this->bridge->getFormat($this->to) ?? 'Y-m-d H:i:s';
                $value = $value->format($format);
            }
        } elseif ($fromType === 'string' && $toType === 'DateTimeImmutable') {
            if ($value === null || $value === '') {
                $value = $toNullable ? null : throw new RuntimeException('Non nullable date property is null');
            } else {
                $value = new DateTimeImmutable($value);
            }
        }

        if ($value instanceof \BackedEnum === true) {
            $value = $value->value;
        }

        if ($toNullable && ($value === null || (is_string($value) === true && $value === ''))) {
            $value = null;
        }

        if ($value !== null && is_scalar($value) === true && is_subclass_of($toType, \BackedEnum::class) === true) {
            $value = $toType::from($value);
        } elseif ($value !== null) {
            $value = match ($toType) {
                'int' => (int)$value,
                'float' => (float)$value,
                'string' => (string)$value,
                'bool' => (bool)$value,
                default => $value,
            };
        }

        $this->bridge->set($this->to, $value);
    }
}