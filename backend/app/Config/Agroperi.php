<?php
namespace Config;
class Agroperi extends \CodeIgniter\Config\BaseConfig
{
 public bool $legacyEnabled=false;
 public function __construct(){parent::__construct();$this->legacyEnabled=(bool)env('agroperi.legacyEnabled',false);}
}
