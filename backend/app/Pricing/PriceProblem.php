<?php

namespace App\Pricing;

class PriceProblem extends \RuntimeException
{
    public function __construct(public int $status, string $message, public array $fields = [])
    {
        parent::__construct($message);
    }
}
