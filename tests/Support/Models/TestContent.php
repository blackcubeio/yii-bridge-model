<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\Support\Models;

use Blackcube\ActiveRecord\PopulatePropertyTrait;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * TestContent ActiveRecord for testing BridgeFormModel with real DB.
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property int|null $age
 * @property bool $active
 * @property DateTimeImmutable|null $birthdate
 * @property DateTimeImmutable $created_at
 */
class TestContent extends ActiveRecord
{
    use PopulatePropertyTrait;

    protected int $id;
    protected string $name = '';
    protected ?string $email = null;
    protected ?int $age = null;
    protected bool $active = false;
    protected ?DateTimeImmutable $birthdate = null;
    protected DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function tableName(): string
    {
        return '{{%testContents}}';
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): void
    {
        $this->age = $age;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getBirthdate(): ?DateTimeImmutable
    {
        return $this->birthdate;
    }

    public function setBirthdate(?DateTimeImmutable $birthdate): void
    {
        $this->birthdate = $birthdate;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
