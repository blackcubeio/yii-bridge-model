<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\Bridge;

use Blackcube\BridgeModel\Components\Bridge;
use Blackcube\BridgeModel\Tests\Support\BridgeTester;
use Blackcube\BridgeModel\Tests\Support\Models\SimpleSource;
use Blackcube\BridgeModel\Tests\Support\Models\SimpleTarget;
use LogicException;

final class BridgeCest
{
    // ===== setEndpoint tests =====

    public function testAddEndpointWithGetterSetter(BridgeTester $I): void
    {
        $bridge = new Bridge('email');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            getter: 'getEmail',
            setter: 'setEmail',
        );

        $I->assertTrue($bridge->hasEndpoint(SimpleSource::class));
        $endpoint = $bridge->getEndpoint(SimpleSource::class);
        $I->assertEquals('getEmail', $endpoint['getter']);
        $I->assertEquals('setEmail', $endpoint['setter']);
        $I->assertNull($endpoint['property']);
    }

    public function testAddEndpointWithProperty(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            property: 'name',
        );

        $I->assertTrue($bridge->hasEndpoint(SimpleSource::class));
        $endpoint = $bridge->getEndpoint(SimpleSource::class);
        $I->assertNull($endpoint['getter']);
        $I->assertNull($endpoint['setter']);
        $I->assertEquals('name', $endpoint['property']);
    }

    public function testAddEndpointWithTypeAndFormat(BridgeTester $I): void
    {
        $bridge = new Bridge('birthdate');
        $source = new SimpleSource();

        $bridge->setEndpoint(
            className: SimpleSource::class,
            property: 'birthdate',
            type: 'string',
            format: 'date',
            isNullable: true,
        );

        $I->assertEquals('string', $bridge->getType($source));
        $I->assertEquals('date', $bridge->getFormat($source));
        $I->assertTrue($bridge->isNullable($source));
    }

    public function testSetEndpointMergesGetterAndSetterSeparately(BridgeTester $I): void
    {
        $bridge = new Bridge('active');

        // First call: only getter (like isActive())
        $bridge->setEndpoint(
            className: SimpleSource::class,
            getter: 'isActive',
        );

        // Second call: only setter (like setActive())
        $bridge->setEndpoint(
            className: SimpleSource::class,
            setter: 'setActive',
        );

        // Both should be present after merge
        $endpoint = $bridge->getEndpoint(SimpleSource::class);
        $I->assertEquals('isActive', $endpoint['getter'], 'Getter should be preserved after second setEndpoint');
        $I->assertEquals('setActive', $endpoint['setter'], 'Setter should be merged from second setEndpoint');
    }

    public function testSetEndpointMergesTypeFromSecondCall(BridgeTester $I): void
    {
        $bridge = new Bridge('value');

        // First call: getter with type
        $bridge->setEndpoint(
            className: SimpleSource::class,
            getter: 'getValue',
            type: 'string',
        );

        // Second call: setter (type already set, should keep it)
        $bridge->setEndpoint(
            className: SimpleSource::class,
            setter: 'setValue',
        );

        $endpoint = $bridge->getEndpoint(SimpleSource::class);
        $I->assertEquals('getValue', $endpoint['getter']);
        $I->assertEquals('setValue', $endpoint['setter']);
        $I->assertEquals('string', $endpoint['type'], 'Type from first call should be preserved');
    }

    // ===== get/set tests on source =====

    public function testGetOnSourceWithGetter(BridgeTester $I): void
    {
        $bridge = new Bridge('email');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            getter: 'getEmail',
            setter: 'setEmail',
        );

        $source = new SimpleSource();
        $source->setEmail('test@example.com');

        $I->assertEquals('test@example.com', $bridge->get($source));
    }

    public function testSetOnSourceWithSetter(BridgeTester $I): void
    {
        $bridge = new Bridge('email');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            getter: 'getEmail',
            setter: 'setEmail',
        );

        $source = new SimpleSource();
        $bridge->set($source, 'new@example.com');

        $I->assertEquals('new@example.com', $source->getEmail());
    }

    public function testGetOnSourceWithProperty(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            property: 'name',
        );

        $source = new SimpleSource();
        $source->name = 'John';

        $I->assertEquals('John', $bridge->get($source));
    }

    public function testSetOnSourceWithProperty(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            property: 'name',
        );

        $source = new SimpleSource();
        $bridge->set($source, 'Jane');

        $I->assertEquals('Jane', $source->name);
    }

    public function testGetOnSourceWithDefaultProperty(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $bridge->setEndpoint(
            className: SimpleSource::class,
        );

        $source = new SimpleSource();
        $source->name = 'Default';

        $I->assertEquals('Default', $bridge->get($source));
    }

    public function testSetOnSourceWithDefaultProperty(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $bridge->setEndpoint(
            className: SimpleSource::class,
        );

        $source = new SimpleSource();
        $bridge->set($source, 'Default');

        $I->assertEquals('Default', $source->name);
    }

    // ===== get/set tests on target =====

    public function testGetOnTargetWithGetter(BridgeTester $I): void
    {
        $bridge = new Bridge('email');
        $bridge->setEndpoint(
            className: SimpleTarget::class,
            getter: 'getEmail',
            setter: 'setEmail',
        );

        $target = new SimpleTarget();
        $target->setEmail('target@example.com');

        $I->assertEquals('target@example.com', $bridge->get($target));
    }

    public function testSetOnTargetWithSetter(BridgeTester $I): void
    {
        $bridge = new Bridge('email');
        $bridge->setEndpoint(
            className: SimpleTarget::class,
            getter: 'getEmail',
            setter: 'setEmail',
        );

        $target = new SimpleTarget();
        $bridge->set($target, 'newtarget@example.com');

        $I->assertEquals('newtarget@example.com', $target->getEmail());
    }

    public function testGetOnTargetWithProperty(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $bridge->setEndpoint(
            className: SimpleTarget::class,
            property: 'name',
        );

        $target = new SimpleTarget();
        $target->name = 'TargetName';

        $I->assertEquals('TargetName', $bridge->get($target));
    }

    public function testSetOnTargetWithProperty(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $bridge->setEndpoint(
            className: SimpleTarget::class,
            property: 'name',
        );

        $target = new SimpleTarget();
        $bridge->set($target, 'NewTargetName');

        $I->assertEquals('NewTargetName', $target->name);
    }

    // ===== isTransferable tests =====

    public function testIsTransferableWithZeroEndpoints(BridgeTester $I): void
    {
        $bridge = new Bridge('test');

        $I->assertFalse($bridge->isTransferable());
    }

    public function testIsTransferableWithOneEndpoint(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            property: 'name',
        );

        $I->assertFalse($bridge->isTransferable());
    }

    public function testIsTransferableWithTwoEndpoints(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            property: 'name',
        );
        $bridge->setEndpoint(
            className: SimpleTarget::class,
            property: 'name',
        );

        $I->assertTrue($bridge->isTransferable());
    }

    // ===== Error cases =====

    public function testGetOnUnregisteredModelThrowsException(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $source = new SimpleSource();

        $I->expectThrowable(LogicException::class, function () use ($bridge, $source) {
            $bridge->get($source);
        });
    }

    public function testSetOnUnregisteredModelThrowsException(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $source = new SimpleSource();

        $I->expectThrowable(LogicException::class, function () use ($bridge, $source) {
            $bridge->set($source, 'value');
        });
    }

    // ===== getName test =====

    public function testGetNameReturnsCorrectName(BridgeTester $I): void
    {
        $bridge = new Bridge('myProperty');

        $I->assertEquals('myProperty', $bridge->getName());
    }

    // ===== isElastic tests =====

    public function testIsElasticReturnsFalseByDefault(BridgeTester $I): void
    {
        $bridge = new Bridge('name');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            property: 'name',
        );

        $I->assertFalse($bridge->isElastic(SimpleSource::class));
    }

    public function testIsElasticReturnsTrueWhenSet(BridgeTester $I): void
    {
        $bridge = new Bridge('dynamic');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            property: 'dynamic',
            isElastic: true,
        );

        $I->assertTrue($bridge->isElastic(SimpleSource::class));
    }

    // ===== meta tests =====

    public function testSetAndGetMeta(BridgeTester $I): void
    {
        $bridge = new Bridge('field');
        $meta = [
            'label' => 'Field Label',
            'hint' => 'Field Hint',
            'placeholder' => 'Enter value',
        ];

        $bridge->setMeta($meta);

        $I->assertEquals($meta, $bridge->getMeta());
    }

    public function testMetaEmptyByDefault(BridgeTester $I): void
    {
        $bridge = new Bridge('field');

        $I->assertEquals([], $bridge->getMeta());
    }

    // ===== rules tests =====

    public function testSetAndGetRules(BridgeTester $I): void
    {
        $bridge = new Bridge('field');
        $rules = ['required', 'string'];

        $bridge->setRules($rules);

        $I->assertEquals($rules, $bridge->getRules());
    }

    public function testRulesEmptyByDefault(BridgeTester $I): void
    {
        $bridge = new Bridge('field');

        $I->assertEquals([], $bridge->getRules());
    }

    // ===== hasEndpoint test =====

    public function testHasEndpointReturnsFalseForUnregistered(BridgeTester $I): void
    {
        $bridge = new Bridge('name');

        $I->assertFalse($bridge->hasEndpoint(SimpleSource::class));
    }

    // ===== Complete transfer scenario =====

    public function testCompleteTransferBetweenSourceAndTarget(BridgeTester $I): void
    {
        $bridge = new Bridge('email');
        $bridge->setEndpoint(
            className: SimpleSource::class,
            getter: 'getEmail',
            setter: 'setEmail',
        );
        $bridge->setEndpoint(
            className: SimpleTarget::class,
            getter: 'getEmail',
            setter: 'setEmail',
        );

        $source = new SimpleSource();
        $source->setEmail('source@example.com');

        $target = new SimpleTarget();

        $value = $bridge->get($source);
        $bridge->set($target, $value);

        $I->assertEquals('source@example.com', $target->getEmail());
        $I->assertTrue($bridge->isTransferable());
    }
}
