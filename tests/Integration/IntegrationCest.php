<?php

declare(strict_types=1);

/**
 * IntegrationCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\BridgeModel\Tests\Integration;

use Blackcube\BridgeModel\Tests\Support\DatabaseCestTrait;
use Blackcube\BridgeModel\Tests\Support\IntegrationTester;
use Blackcube\BridgeModel\Tests\Support\Models\TestContent;
use Blackcube\BridgeModel\Tests\Support\Models\TestContentFormModel;
use DateTimeImmutable;

final class IntegrationCest
{
    use DatabaseCestTrait;


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

        $formModel->name = 'Updated via Scenario';
        $formModel->active = false;

        $formModel->populateModel($ar);

        $I->assertEquals('Updated via Scenario', $ar->getName());
        $I->assertTrue($ar->isActive());
    }


    public function testFullRoundTrip(IntegrationTester $I): void
    {
        $this->db->createCommand()->insert('{{%testContents}}', [
            'name' => 'Round Trip',
            'email' => 'roundtrip@example.com',
            'age' => 30,
            'active' => 1,
            'birthdate' => '1994-07-04',
            'createdAt' => '2024-01-01 10:00:00',
        ])->execute();

        $ar = TestContent::query()->one();
        $formModel = TestContentFormModel::createFromModel($ar);

        $I->assertEquals('Round Trip', $formModel->name);
        $I->assertEquals('roundtrip@example.com', $formModel->email);
        $I->assertEquals(30, $formModel->age);
        $I->assertTrue($formModel->active);

        $formModel->name = 'Modified Round Trip';
        $formModel->age = 31;

        $formModel->populateModel($ar);
        $ar->save();

        $reloaded = TestContent::query()->where(['id' => $ar->getId()])->one();
        $I->assertEquals('Modified Round Trip', $reloaded->getName());
        $I->assertEquals(31, $reloaded->getAge());
        $I->assertEquals('roundtrip@example.com', $reloaded->getEmail());
    }


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

        $I->assertInstanceOf(DateTimeImmutable::class, $ar->getBirthdate());
        $I->assertInstanceOf(DateTimeImmutable::class, $ar->getCreatedAt());

        $formModel = TestContentFormModel::createFromModel($ar);

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

        $formModel->birthdate = '2000-12-25';
        $formModel->createdAt = '2025-06-15 14:30:00';

        $formModel->populateModel($ar);

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

        $formModel->birthdate = null;

        $formModel->populateModel($ar);

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

        $I->assertEquals('1994-07-04', $formModel->birthdate);
        $I->assertStringContainsString('2024-06-15', $formModel->createdAt);

        $formModel->birthdate = '1995-08-20';
        $formModel->createdAt = '2025-06-15 12:00:00';

        $formModel->populateModel($ar);
        $ar->save();

        $reloaded = TestContent::query()->where(['id' => $ar->getId()])->one();
        $I->assertEquals('1995-08-20', $reloaded->getBirthdate()->format('Y-m-d'));

        $I->assertEquals('2025-06-15', $reloaded->getCreatedAt()->format('Y-m-d'));

        $newFormModel = TestContentFormModel::createFromModel($reloaded);
        $I->assertEquals('1995-08-20', $newFormModel->birthdate);
        $I->assertStringContainsString('2025-06-15', $newFormModel->createdAt);
    }
}
