<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\Integration;

use Blackcube\BridgeModel\Tests\Support\DatabaseCestTrait;
use Blackcube\BridgeModel\Tests\Support\IntegrationTester;
use Blackcube\BridgeModel\Tests\Support\Models\TestContent;
use Blackcube\BridgeModel\Tests\Support\Models\TestContentFormModel;
use DateTimeImmutable;

final class IntegrationCest
{
    use DatabaseCestTrait;

    // ===== transfer() AR → FormModel =====

    public function testTransferFromArToFormModel(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'active' => 1,
            'birthdate' => '1994-05-15',
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);

        $I->assertEquals('John Doe', $formModel->name);
        $I->assertEquals('john@example.com', $formModel->email);
        $I->assertEquals(30, $formModel->age);
        $I->assertTrue($formModel->active);
    }

    // ===== transfer() FormModel → AR =====

    public function testTransferFromFormModelToAr(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Original',
            'email' => 'original@example.com',
            'age' => 25,
            'active' => 0,
            'birthdate' => null,
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);

        $formModel->name = 'Updated Name';
        $formModel->email = 'updated@example.com';
        $formModel->age = 35;
        $formModel->active = true;

        $formModel->populateModel($ar);

        $I->assertEquals('Updated Name', $ar->getName());
        $I->assertEquals('updated@example.com', $ar->getEmail());
        $I->assertEquals(35, $ar->getAge());
        $I->assertTrue($ar->isActive());
    }

    // ===== initFromModel =====

    public function testInitFromModel(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Test Init',
            'email' => 'init@example.com',
            'age' => 40,
            'active' => 1,
            'birthdate' => '1984-03-20',
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = new TestContentFormModel();
        $formModel->initFromModel($ar);

        $I->assertEquals('Test Init', $formModel->name);
        $I->assertEquals('init@example.com', $formModel->email);
        $I->assertEquals(40, $formModel->age);
    }

    // ===== createFromModel factory =====

    public function testCreateFromModelFactory(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Factory Test',
            'email' => 'factory@example.com',
            'age' => 50,
            'active' => 0,
            'birthdate' => null,
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);

        $I->assertInstanceOf(TestContentFormModel::class, $formModel);
        $I->assertEquals('Factory Test', $formModel->name);
    }

    // ===== populateModel =====

    public function testPopulateModelAndSave(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Before Save',
            'email' => 'before@example.com',
            'age' => 20,
            'active' => 0,
            'birthdate' => null,
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);

        $formModel->name = 'After Save';
        $formModel->email = 'after@example.com';

        $formModel->populateModel($ar);
        $ar->save();

        $reloaded = TestContent::query()->where(['id' => $ar->getId()])->one();
        $I->assertEquals('After Save', $reloaded->getName());
        $I->assertEquals('after@example.com', $reloaded->getEmail());
    }

    // ===== Scenario filtering =====

    public function testScenarioFiltersActiveFields(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Scenario Test',
            'email' => 'scenario@example.com',
            'age' => 25,
            'active' => 1,
            'birthdate' => '1999-01-01',
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);
        $formModel->setScenario(TestContentFormModel::SCENARIO_UPDATE);

        // In UPDATE scenario, only name, email, age are active
        $formModel->name = 'Updated via Scenario';
        $formModel->active = false;  // This should NOT be transferred (not in scenario)

        $formModel->populateModel($ar);

        $I->assertEquals('Updated via Scenario', $ar->getName());
        $I->assertTrue($ar->isActive());  // Should still be true (unchanged)
    }

    // ===== Full round-trip test =====

    public function testFullRoundTrip(IntegrationTester $I): void
    {
        // Create
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Round Trip',
            'email' => 'roundtrip@example.com',
            'age' => 30,
            'active' => 1,
            'birthdate' => '1994-07-04',
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        // Load into FormModel
        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);

        $I->assertEquals('Round Trip', $formModel->name);
        $I->assertEquals('roundtrip@example.com', $formModel->email);
        $I->assertEquals(30, $formModel->age);
        $I->assertTrue($formModel->active);

        // Modify
        $formModel->name = 'Modified Round Trip';
        $formModel->age = 31;

        // Save back
        $formModel->populateModel($ar);
        $ar->save();

        // Reload and verify
        $reloaded = TestContent::query()->where(['id' => $ar->getId()])->one();
        $I->assertEquals('Modified Round Trip', $reloaded->getName());
        $I->assertEquals(31, $reloaded->getAge());
        $I->assertEquals('roundtrip@example.com', $reloaded->getEmail()); // Unchanged
    }

    // ===== DateTimeImmutable ↔ string conversion tests =====

    public function testDateTimeImmutableToStringConversion(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Date Test',
            'email' => 'date@example.com',
            'age' => 30,
            'active' => 1,
            'birthdate' => '1994-05-15',
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();

        // Verify AR has DateTimeImmutable
        $I->assertInstanceOf(DateTimeImmutable::class, $ar->getBirthdate());
        $I->assertInstanceOf(DateTimeImmutable::class, $ar->getCreatedAt());

        // Transfer to FormModel
        $formModel = TestContentFormModel::createFromModel($ar);

        // Verify FormModel has string with correct format
        $I->assertEquals('1994-05-15', $formModel->birthdate);
        $I->assertEquals('2024-01-01 10:00:00', $formModel->createdAt);
    }

    public function testStringToDateTimeImmutableConversion(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'String to Date Test',
            'email' => 'stringtodate@example.com',
            'age' => 25,
            'active' => 1,
            'birthdate' => '1999-01-01',
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);

        // Modify date strings in FormModel
        $formModel->birthdate = '2000-12-25';
        $formModel->createdAt = '2025-06-15 14:30:00';

        // Transfer back to AR
        $formModel->populateModel($ar);

        // Verify AR has DateTimeImmutable with correct values
        $I->assertInstanceOf(DateTimeImmutable::class, $ar->getBirthdate());
        $I->assertEquals('2000-12-25', $ar->getBirthdate()->format('Y-m-d'));

        $I->assertInstanceOf(DateTimeImmutable::class, $ar->getCreatedAt());
        $I->assertEquals('2025-06-15 14:30:00', $ar->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    public function testNullDateTimeImmutableToString(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Null Date Test',
            'email' => 'nulldate@example.com',
            'age' => 30,
            'active' => 1,
            'birthdate' => null,
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);

        // Null DateTimeImmutable should become null string
        $I->assertNull($formModel->birthdate);
    }

    public function testNullStringToDateTimeImmutable(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Null String Test',
            'email' => 'nullstring@example.com',
            'age' => 30,
            'active' => 1,
            'birthdate' => '1990-01-01',
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);

        // Set birthdate to null in FormModel
        $formModel->birthdate = null;

        // Transfer back to AR
        $formModel->populateModel($ar);

        // AR should have null birthdate
        $I->assertNull($ar->getBirthdate());
    }

    public function testDateRoundTripPreservesValues(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Date Round Trip',
            'email' => 'dateroundtrip@example.com',
            'age' => 30,
            'active' => 1,
            'birthdate' => '1994-07-04',
            'createdAt' => '2024-06-15 12:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);

        // Verify initial birthdate (Y-m-d format)
        $I->assertEquals('1994-07-04', $formModel->birthdate);
        // Verify initial createdAt contains the date part
        $I->assertStringContainsString('2024-06-15', $formModel->createdAt);

        // Modify and save
        $formModel->birthdate = '1995-08-20';
        $formModel->createdAt = '2025-06-15 12:00:00';

        $formModel->populateModel($ar);
        $ar->save();

        // Reload and verify birthdate (date conversion works perfectly)
        $reloaded = TestContent::query()->where(['id' => $ar->getId()])->one();
        $I->assertEquals('1995-08-20', $reloaded->getBirthdate()->format('Y-m-d'));

        // Verify createdAt date part (timezone conversion may shift time)
        $I->assertEquals('2025-06-15', $reloaded->getCreatedAt()->format('Y-m-d'));

        // Create new FormModel from reloaded AR
        $newFormModel = TestContentFormModel::createFromModel($reloaded);
        $I->assertEquals('1995-08-20', $newFormModel->birthdate);
        // Verify date part of createdAt
        $I->assertStringContainsString('2025-06-15', $newFormModel->createdAt);
    }
}
