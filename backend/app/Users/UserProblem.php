<?php
namespace App\Users;

class UserProblem extends \RuntimeException
{
    public function __construct(public int $status, string $message, public array $fields = []) { parent::__construct($message); }
}
