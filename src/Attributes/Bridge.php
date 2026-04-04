<?php

declare(strict_types=1);

/**
 * Bridge.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel\Attributes;

use Attribute;

/**
 * Attribute indicating that a property or method is bridged to another model
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Bridge
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly ?string $format = null,
        public readonly string|false|null $property = null,
        public readonly ?string $getter = null,
        public readonly ?string $setter = null,
    ) {
    }
}