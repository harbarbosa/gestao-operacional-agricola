<?php

namespace Tests\App;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class HealthTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testHealthReturnsOnlyStatus(): void
    {
        $result = $this->get('/api/v1/health');
        $result->assertStatus(200);
        $result->assertJSONExact(['status' => 'ok']);
        $result->assertHeader('Cache-Control', 'no-store, max-age=0, no-cache');
    }
}
