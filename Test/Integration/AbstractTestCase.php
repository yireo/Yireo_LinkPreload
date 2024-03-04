<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Test\Integration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\TestFramework\TestCase\AbstractController;

class AbstractTestCase extends AbstractController
{
    protected function assertEnabledValue(int $expectedValue): void
    {
        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = $this->_objectManager->get(ScopeConfigInterface::class);
        $this->assertEquals($expectedValue, $scopeConfig->getValue('system/yireo_linkpreload/enabled'));
    }
}
