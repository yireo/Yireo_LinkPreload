<?php
declare(strict_types=1);

namespace Yireo\Foobar\Test\Integration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Config\Scope;
use Magento\TestFramework\TestCase\AbstractController;

class HeaderTest extends AbstractController
{
    /**
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     */
    public function testIfLinkHeadersExistsWhenModuleIsEnabled()
    {
        $this->dispatch('/');
        $headers = $this->getResponse()->getHeaders();

        $match = false;
        $foundHeaders = [];
        foreach ($headers as $header) {
            /** @var $header \Zend\Http\Header\HeaderInterface */
            if (preg_match('/^Link:/', $header->toString())) {
                $foundHeaders[] = $header->toString();
                $match = true;
                break;
            }
        }

        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = $this->_objectManager->get(ScopeConfigInterface::class);
        $enabled = $scopeConfig->getValue('system/yireo_linkpreload/enabled');
        $this->assertEquals(1, $enabled);

        $this->assertTrue($match, 'Expected a Link-header, but found only this: '.implode("; ", $foundHeaders));
    }

    /**
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 0
     */
    public function testIfLinkHeadersExistsWhenModuleIsDisabled()
    {
        $this->dispatch('/');
        $headers = $this->getResponse()->getHeaders();

        $match = false;
        $foundHeaders = [];
        foreach ($headers as $header) {
            /** @var $header \Zend\Http\Header\HeaderInterface */
            if (preg_match('/^Link:/', $header->toString())) {
                $foundHeaders[] = $header->toString();
                $match = true;
                break;
            }
        }

        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = $this->_objectManager->get(ScopeConfigInterface::class);
        $enabled = $scopeConfig->getValue('system/yireo_linkpreload/enabled');
        $this->assertEquals(0, $enabled);

        $this->assertFalse($match, 'Expected no Link-header, but found headers anyway: '.implode("; ", $foundHeaders));
    }
}