<?php

declare(strict_types=1);

/**
 * IntOrNull.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel\Attributes;

use Attribute;
use Yiisoft\Hydrator\Attribute\Parameter\ParameterAttributeInterface;
use Yiisoft\Hydrator\Attribute\Parameter\ParameterAttributeResolverInterface;
use Yiisoft\Hydrator\AttributeHandling\ParameterAttributeResolveContext;
use Yiisoft\Hydrator\Result;
use Yiisoft\Hydrator\AttributeHandling\Exception\UnexpectedAttributeException;

use function is_scalar;

/**
 * Converts the resolved value to int or null. Non-resolved values are skipped.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
class IntOrNull implements ParameterAttributeInterface, ParameterAttributeResolverInterface
{
    public function getResolver(): self
    {
        return $this;
    }

    public function getParameterValue(ParameterAttributeInterface $attribute, ParameterAttributeResolveContext $context): Result
    {
        if ($attribute instanceof self === false) {
            throw new UnexpectedAttributeException(self::class, $attribute);
        }

        if ($context->isResolved() === true) {
            $resolvedValue = $context->getResolvedValue();
            if (is_string($resolvedValue) === true && $resolvedValue === '') {
                return Result::success(null);
            }
            return Result::success((int) $resolvedValue);

        }

        return Result::fail();
    }
}