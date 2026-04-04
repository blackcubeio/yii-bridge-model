<?php

declare(strict_types=1);

namespace Blackcube\BridgeModel\Tests\Support\Models;

/**
 * Simple source model for testing Bridge
 * Uses public properties for direct access
 */
class SimpleSource
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
}
