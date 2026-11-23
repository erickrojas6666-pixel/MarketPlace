<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Evita "Vite manifest not found": las pruebas no necesitan compilar el frontend.
        $this->withoutVite();
    }
}
