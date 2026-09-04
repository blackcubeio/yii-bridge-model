<?php

declare(strict_types=1);

/**
 * ElasticCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel\Tests\Elastic;

use Blackcube\BridgeModel\Attributes\Bridge as BridgeAttribute;
use Blackcube\BridgeModel\BridgeFormModel;
use Blackcube\BridgeModel\Components\Bridge;
use Blackcube\BridgeModel\Tests\Support\ElasticTester;
use Blackcube\BridgeModel\Tests\Support\Models\ElasticFormModel;
use Blackcube\BridgeModel\Tests\Support\Models\SimpleTarget;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests for elastic properties, magic methods, and ALL_ELASTIC_ATTRIBUTES / NO_ELASTIC_ATTRIBUTES scenarios.
 *
 * Note: Full ElasticInterface integration tests are expected to fail because
 * Yii hydrator doesn't handle dynamic elastic properties. This is documented
 * and expected behavior per the brief.
 */
final class ElasticCest
{

    public function testBridgeIsElasticDefaultFalse(ElasticTester $I): void
    {
        $bridge = new Bridge('testProp');
        $bridge->setEndpoint(
            className: SimpleTarget::class,
            property: 'name',
            type: 'string',
            isNullable: false,
        );

        $I->assertFalse($bridge->isElastic(SimpleTarget::class));
    }

    public function testBridgeIsElasticTrueWhenSet(ElasticTester $I): void
    {
        $bridge = new Bridge('testProp');
        $bridge->setEndpoint(
            className: SimpleTarget::class,
            property: 'name',
            type: 'string',
            isNullable: false,
            isElastic: true,
        );

        $I->assertTrue($bridge->isElastic(SimpleTarget::class));
    }

    public function testBridgeIsElasticPerClass(ElasticTester $I): void
    {
        $bridge = new Bridge('testProp');
        $bridge->setEndpoint(
            className: SimpleTarget::class,
            property: 'name',
            type: 'string',
            isNullable: false,
            isElastic: true,
        );
        $bridge->setEndpoint(
            className: ElasticFormModel::class,
            property: 'name',
            type: 'string',
            isNullable: false,
            isElastic: false,
        );

        $I->assertTrue($bridge->isElastic(SimpleTarget::class));
        $I->assertFalse($bridge->isElastic(ElasticFormModel::class));
    }


    public function testAllElasticAttributesConstantExists(ElasticTester $I): void
    {
        $I->assertEquals('__all_elastic_attributes__', BridgeFormModel::ALL_ELASTIC_ATTRIBUTES);
    }

    public function testNoElasticAttributesConstantExists(ElasticTester $I): void
    {
        $I->assertEquals('__no_elastic_attributes__', BridgeFormModel::NO_ELASTIC_ATTRIBUTES);
    }

    public function testScenariosIncludeElasticMarkers(ElasticTester $I): void
    {
        $formModel = new ElasticFormModel();
        $scenarios = $formModel->scenarios();

        $I->assertContains(BridgeFormModel::ALL_ELASTIC_ATTRIBUTES, $scenarios[ElasticFormModel::SCENARIO_DEFAULT]);
        $I->assertContains(BridgeFormModel::ALL_ELASTIC_ATTRIBUTES, $scenarios[ElasticFormModel::SCENARIO_WITH_ALL_ELASTIC]);
        $I->assertContains(BridgeFormModel::NO_ELASTIC_ATTRIBUTES, $scenarios[ElasticFormModel::SCENARIO_WITHOUT_ELASTIC]);
    }


    public function testGetActiveFieldsFiltersOutElasticMarkers(ElasticTester $I): void
    {
        $formModel = new ElasticFormModel();
        $target = new SimpleTarget();
        $formModel->initFromModel($target);

        $ref = new ReflectionClass($formModel);
        $method = $ref->getMethod('getActiveFields');
        $method->setAccessible(true);

        $activeFields = $method->invoke($formModel);

        $I->assertNotContains(BridgeFormModel::ALL_ELASTIC_ATTRIBUTES, $activeFields);
        $I->assertNotContains(BridgeFormModel::NO_ELASTIC_ATTRIBUTES, $activeFields);
    }

    public function testGetActiveFieldsIncludesNonElasticFields(ElasticTester $I): void
    {
        $formModel = new ElasticFormModel();
        $target = new SimpleTarget();
        $formModel->initFromModel($target);

        $ref = new ReflectionClass($formModel);
        $method = $ref->getMethod('getActiveFields');
        $method->setAccessible(true);

        $activeFields = $method->invoke($formModel);

        $I->assertContains('name', $activeFields);
        $I->assertContains('email', $activeFields);
    }


    public function testMagicGetReturnsNullForUndefinedElastic(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $value = $formModel->dynamicField;

        $I->assertNull($value);
    }

    public function testMagicGetReturnsValueForDefinedElastic(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $formModel->dynamicField = 'test value';

        $value = $formModel->dynamicField;

        $I->assertEquals('test value', $value);
    }


    public function testMagicSetStoresElasticValue(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $formModel->dynamicField = 'stored value';

        $I->assertEquals('stored value', $formModel->dynamicField);
    }

    public function testMagicSetOverwritesElasticValue(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $formModel->dynamicField = 'first';
        $formModel->dynamicField = 'second';

        $I->assertEquals('second', $formModel->dynamicField);
    }


    public function testMagicIssetReturnsFalseWhenNotSet(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();

        $I->assertFalse(isset($formModel->dynamicField));
    }

    public function testMagicIssetReturnsTrueWhenSet(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $formModel->dynamicField = 'value';

        $I->assertTrue(isset($formModel->dynamicField));
    }

    public function testMagicIssetReturnsFalseWhenSetToNull(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $formModel->dynamicField = null;

        $I->assertFalse(isset($formModel->dynamicField));
    }


    public function testMagicCallGetterReturnsValue(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $formModel->dynamicField = 'getter value';

        $value = $formModel->getDynamicField();

        $I->assertEquals('getter value', $value);
    }

    public function testMagicCallIserReturnsValue(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $formModel->dynamicActive = true;

        $value = $formModel->isDynamicActive();

        $I->assertTrue($value);
    }


    public function testMagicCallSetterStoresValue(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $formModel->setDynamicField('setter value');

        $I->assertEquals('setter value', $formModel->dynamicField);
    }

    public function testMagicCallSetterReturnsNull(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $result = $formModel->setDynamicField('value');

        $I->assertNull($result);
    }


    public function testElasticPropertyWithVariousTypes(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();

        $formModel->dynamicField = 'string';
        $I->assertEquals('string', $formModel->dynamicField);

        $formModel->dynamicField = 42;
        $I->assertEquals(42, $formModel->dynamicField);

        $formModel->dynamicField = true;
        $I->assertTrue($formModel->dynamicField);

        $formModel->dynamicField = ['key' => 'value'];
        $I->assertEquals(['key' => 'value'], $formModel->dynamicField);

        $formModel->dynamicField = null;
        $I->assertNull($formModel->dynamicField);
    }

    public function testMultipleElasticPropertiesIndependent(ElasticTester $I): void
    {
        $formModel = new ElasticTestFormModel();
        $formModel->dynamicField = 'field1';
        $formModel->dynamicActive = true;

        $I->assertEquals('field1', $formModel->dynamicField);
        $I->assertTrue($formModel->dynamicActive);
    }
}

/**
 * Test FormModel with manually configured elastic properties.
 * This class injects elastic bridges directly to test magic method behavior
 * without requiring full ElasticInterface integration.
 */
class ElasticTestFormModel extends BridgeFormModel
{
    public function __construct()
    {
        $this->injectElasticBridge('dynamicField');
        $this->injectElasticBridge('dynamicActive');
    }

    private function injectElasticBridge(string $name): void
    {
        $ref = new ReflectionClass(BridgeFormModel::class);
        $prop = $ref->getProperty('properties');
        $prop->setAccessible(true);

        $properties = $prop->getValue($this);
        $bridge = new Bridge($name);
        $bridge->setEndpoint(
            className: static::class,
            property: $name,
            type: 'mixed',
            isNullable: true,
            isElastic: true,
        );
        $properties[$name] = $bridge;
        $prop->setValue($this, $properties);
    }

    public function rules(): array
    {
        return [];
    }

    public function getRules(): array
    {
        return [];
    }
}
