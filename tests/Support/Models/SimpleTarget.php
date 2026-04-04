<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\Support\Models;

/**
 * Simple target model for testing Bridge
 * Uses getter/setter methods
 */
class SimpleTarget
{
    public string $name = '';
    public ?int $age = null;
    public bool $active = false;

    private string $email = '';

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    private string $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
