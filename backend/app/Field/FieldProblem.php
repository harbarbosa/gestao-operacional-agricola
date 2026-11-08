<?php
namespace App\Field;
class FieldProblem extends \RuntimeException
{
    public function __construct(public int $status, string $message, public array $fields = []) { parent::__construct($message); }
}
