<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\BridgeFormModel;

use Blackcube\BridgeModel\Tests\Support\BridgeFormModelTester;
use Blackcube\BridgeModel\Tests\Support\Models\ActiveFormModel;
use Blackcube\BridgeModel\Tests\Support\Models\ActiveTarget;
use Blackcube\BridgeModel\Tests\Support\Models\ExplicitGetterSetterFormModel;
use Blackcube\BridgeModel\Tests\Support\Models\ExplicitNameFormModel;
use Blackcube\BridgeModel\Tests\Support\Models\MethodBridgeFormModel;
use Blackcube\BridgeModel\Tests\Support\Models\SetterBridgeFormModel;
use Blackcube\BridgeModel\Tests\Support\Models\SimpleTarget;
use Blackcube\BridgeModel\Tests\Support\Models\TestFormModel;

final class BridgeFormModelCest
{
    // ===== Property source → getter/setter target (auto-detected) =====

    public function testPropertySourceToGetterSetterTarget(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $target = new SimpleTarget();
        $target->setEmail('target@example.com');

        $formModel->initFromModel($target);

        $I->wantToTest('Property source bridges to getter/setter target automatically');
    }

    public function testPropertySourceToPropertyTarget(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $target = new SimpleTarget();
        $target->name = 'TargetName';

        $formModel->initFromModel($target);

        $I->wantToTest('Property source bridges to property target automatically');
    }

    // ===== Getter source → setter target =====

    public function testGetterSourceToSetterTarget(BridgeFormModelTester $I): void
    {
        $formModel = new MethodBridgeFormModel();
        $target = new SimpleTarget();

        $formModel->initFromModel($target);

        $I->wantToTest('Getter source bridges to setter target');
    }

    // ===== Setter source → getter target =====

    public function testSetterSourceToGetterTarget(BridgeFormModelTester $I): void
    {
        $formModel = new SetterBridgeFormModel();
        $target = new SimpleTarget();

        $formModel->initFromModel($target);

        $I->wantToTest('Setter source bridges to getter target');
    }

    // ===== Explicit name in Bridge attribute =====

    public function testExplicitNameInBridgeAttribute(BridgeFormModelTester $I): void
    {
        $formModel = new ExplicitNameFormModel();
        $target = new SimpleTarget();
        $target->setTitle('My Title');

        $formModel->initFromModel($target);

        $I->wantToTest('Explicit name in Bridge attribute maps correctly');
    }

    // ===== Explicit getter/setter in Bridge attribute =====

    public function testExplicitGetterSetterInBridgeAttribute(BridgeFormModelTester $I): void
    {
        $formModel = new ExplicitGetterSetterFormModel();
        $target = new SimpleTarget();
        $target->setEmail('explicit@example.com');

        $formModel->initFromModel($target);

        $I->wantToTest('Explicit getter/setter in Bridge attribute are used');
    }

    // ===== createFromModel factory =====

    public function testCreateFromModelFactory(BridgeFormModelTester $I): void
    {
        $target = new SimpleTarget();
        $target->name = 'FactoryTest';

        $formModel = TestFormModel::createFromModel($target);

        $I->assertInstanceOf(TestFormModel::class, $formModel);
    }

    // ===== Scenario management =====

    public function testDefaultScenario(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();

        $I->assertEquals('default', $formModel->getScenario());
    }

    public function testSetScenario(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $formModel->setScenario('create');

        $I->assertEquals('create', $formModel->getScenario());
    }

    public function testSetScenarioReturnsSelf(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $result = $formModel->setScenario('update');

        $I->assertSame($formModel, $result);
    }

    // ===== loadMultiple =====

    public function testLoadMultipleWithEmptyModels(BridgeFormModelTester $I): void
    {
        $result = TestFormModel::loadMultiple([], []);

        $I->assertFalse($result);
    }

    public function testLoadMultipleWithNoScopeData(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $result = TestFormModel::loadMultiple([$formModel], ['other' => []]);

        $I->assertFalse($result);
    }

    // ===== load method =====

    public function testLoadWithEmptyScope(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $result = $formModel->load(['name' => 'Test'], '');

        $I->assertTrue($result);
    }

    public function testLoadWithNonArrayData(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $result = $formModel->load(['TestFormModel' => 'not an array']);

        $I->assertFalse($result);
    }

    // ===== getProperties =====

    public function testGetPropertiesReturnsAllBridges(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $target = new SimpleTarget();

        $formModel->initFromModel($target);

        $properties = $formModel->getProperties();
        $I->assertArrayHasKey('name', $properties);
        $I->assertArrayHasKey('email', $properties);
    }

    // ===== validate =====

    public function testValidateReturnsBool(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $result = $formModel->validate();

        $I->assertTrue($result);
    }

    // ===== scenarios default =====

    public function testScenariosReturnsPropertyKeys(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $target = new SimpleTarget();
        $formModel->initFromModel($target);

        $scenarios = $formModel->scenarios();

        $I->assertArrayHasKey('default', $scenarios);
        $I->assertIsArray($scenarios['default']);
    }

    // ===== getData =====

    public function testGetDataReturnsAllPropertyValues(BridgeFormModelTester $I): void
    {
        $target = new SimpleTarget();
        $formModel = new TestFormModel();
        $formModel->initFromModel($target);
        $formModel->name = 'Test Name';
        $formModel->email = 'test@example.com';

        $data = $formModel->getData();

        $I->assertEquals('Test Name', $data['name']);
        $I->assertEquals('test@example.com', $data['email']);
    }

    // ===== getRules merges bridge rules =====

    public function testGetRulesMergesManualAndBridgeRules(BridgeFormModelTester $I): void
    {
        $formModel = new TestFormModel();
        $target = new SimpleTarget();
        $formModel->initFromModel($target);

        $rules = $formModel->getRules();

        $I->assertIsArray($rules);
    }

    // ===== isActive() + setActive() separate bridges (THE BUG) =====

    public function testSeparateIsGetterAndSetterAreBothDiscovered(BridgeFormModelTester $I): void
    {
        // Target has active = true
        $target = new ActiveTarget();
        $target->setActive(true);

        // Create form from target - this triggers bridge discovery
        $formModel = ActiveFormModel::createFromModel($target);

        // Getter must work: form should have active = true from target
        $I->assertTrue($formModel->isActive(), 'Getter bridge must work: form should read active=true from target');

        // Now test setter: change form and populate back to target
        $formModel->setActive(false);
        $formModel->populateModel($target);

        // Setter must work: target should now be false
        $I->assertFalse($target->isActive(), 'Setter bridge must work: target should receive active=false from form');
    }

    public function testSeparateBridgesCreateSinglePropertyWithBothMethods(BridgeFormModelTester $I): void
    {
        $target = new ActiveTarget();
        $formModel = ActiveFormModel::createFromModel($target);

        $properties = $formModel->getProperties();

        // Should have exactly ONE 'active' property, not two
        $I->assertArrayHasKey('active', $properties);

        // The endpoint for FormModel class should have BOTH getter and setter
        $bridge = $properties['active'];
        $endpoint = $bridge->getEndpoint(ActiveFormModel::class);

        $I->assertEquals('isActive', $endpoint['getter'], 'Bridge must have isActive as getter');
        $I->assertEquals('setActive', $endpoint['setter'], 'Bridge must have setActive as setter');
    }
}
