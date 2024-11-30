<?php

namespace Gupalo\MonologDbalLogger\Entity\Traits;

trait VirtualFieldsEntityTrait
{
    public function virtual(): string
    {
        return '';
    }

    public function virtualString(): string
    {
        return '';
    }

    public function virtualInt(): int
    {
        return 0;
    }

    public function virtualFloat(): float
    {
        return 0.0;
    }

    public function virtualBool(): bool
    {
        return false;
    }

    public function virtualStringNull(): ?string
    {
        return null;
    }

    public function virtualIntNull(): ?int
    {
        return null;
    }

    public function virtualFloatNull(): ?float
    {
        return null;
    }

    public function virtualBoolNull(): ?bool
    {
        return null;
    }
}
