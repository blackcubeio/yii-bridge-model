<?php

declare(strict_types=1);

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
final class IntOrNull implements ParameterAttributeInterface, ParameterAttributeResolverInterface
{
    public function getResolver(): self
    {
        return $this;
    }

    public function getParameterValue(ParameterAttributeInterface $attribute, ParameterAttributeResolveContext $context): Result
    {
        if (!$attribute instanceof self) {
            throw new UnexpectedAttributeException(self::class, $attribute);
        }

        if ($context->isResolved()) {
            $resolvedValue = $context->getResolvedValue();
            if (is_string($resolvedValue) && $resolvedValue === '') {
                return Result::success(null);
            }
            return Result::success((int) $resolvedValue);

        }

        return Result::fail();
    }
}