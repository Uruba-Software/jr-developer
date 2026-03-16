<?php

namespace App\Enums;

enum OperatingMode: string
{
    case Manual = 'manual'; // No AI, zero API cost
    case Agent  = 'agent';  // User's own API key
    case Cloud  = 'cloud';  // Paid, our API pool

    public function label(): string
    {
        return match($this) {
            self::Manual => 'Manual',
            self::Agent  => 'Agent (Your API Key)',
            self::Cloud  => 'Cloud (Jr Developer API)',
        };
    }

    public function requiresApiKey(): bool
    {
        return match($this) {
            self::Manual => false,
            self::Agent  => true,
            self::Cloud  => false,
        };
    }
}
