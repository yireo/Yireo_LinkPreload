<?php
declare(strict_types=1);

namespace Yireo\Foobar\Test\Integration;

use Magento\TestFramework\TestCase\AbstractController;

class HeaderTest extends AbstractController
{
    /**
     * @magentoConfigFixture system/yireo_linkpreload/enabled 1
     */
    public function testIfLinkHeadersExistsWhenModuleIsEnabled()
    {
        $this->dispatch('/');
        $headers = $this->getResponse()->getHeaders();

        $match = false;
        foreach ($headers as $header) {
            /** @var $header \Zend\Http\Header\HeaderInterface */
            if (preg_match('/^Link:/', $header->toString())) {
                $match = true;
                break;
            }
        }

        $this->assertTrue($match);
    }

    /**
     * @magentoConfigFixture system/yireo_linkpreload/enabled 0
     */
    public function testIfLinkHeadersExistsWhenModuleIsDisabled()
    {
        $this->dispatch('/');
        $headers = $this->getResponse()->getHeaders();

        $match = false;
        foreach ($headers as $header) {
            /** @var $header \Zend\Http\Header\HeaderInterface */
            if (preg_match('/^Link:/', $header->toString())) {
                $match = true;
                break;
            }
        }

        $this->assertTrue($match);
    }
}