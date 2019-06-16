<?php

namespace Yireo\LinkPreload\Test\Integration;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

/**
 * Class ModuleTest
 *
 * @package Yireo\LinkPreload\Test\Integration
 */
class ModuleTest extends TestCase
{
    public function testIfModuleIsRegistered()
    {
        $registrar = new ComponentRegistrar();
        $paths = $registrar->getPaths(ComponentRegistrar::MODULE);
        $this->assertArrayHasKey('Yireo_LinkPreload', $paths);
    }
}
