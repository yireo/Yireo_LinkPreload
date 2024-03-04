<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Test\Integration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\TestFramework\TestCase\AbstractController;
use Laminas\Http\Header\HeaderInterface;

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
            /** @var $header HeaderInterface */
            $foundHeaders[] = $header->toString();
            if (preg_match('/^Link:/', $header->toString())) {
                $this->assertValidLinkHeader($header->toString());
                $match = true;
                break;
            }
        }

        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = $this->_objectManager->get(ScopeConfigInterface::class);
        $enabled = $scopeConfig->getValue('system/yireo_linkpreload/enabled');
        $this->assertEquals(1, $enabled);

        if (empty($foundHeaders)) {
            $msg = 'No headers found';
        } else {
            $msg = 'Expected a Link-header, but found only this: ' . implode("; ", $foundHeaders);
        }

        $this->assertTrue($match, $msg);
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
            /** @var $header HeaderInterface */
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

        $msg = 'Expected no Link-header, but found headers anyway: ' . implode("; ", $foundHeaders);
        $this->assertFalse($match, $msg);
    }


    private function assertValidLinkHeader(string $linkHeader): void
    {
        $linkHeader = str_replace('Link:', '', $linkHeader);
        $links = explode(',', $linkHeader);
        foreach ($links as $link) {
            $this->assertValidLink($link);
        }
    }

    private function assertValidLink(string $link): void
    {
        $link = trim($link);
        $linkParams = explode(';', $link);
        $linkUri = trim(array_shift($linkParams));
        $this->assertStringStartsWith('<', $linkUri);
        $this->assertStringEndsWith('>', $linkUri);

        foreach ($linkParams as $linkParam) {
            $linkParam = explode('=', $linkParam);
            $this->assertEquals(2, count($linkParam));
            $this->assertEquals('rel', trim($linkParam[0]));
            $this->assertEquals('preload', str_replace('"', '', trim($linkParam[1])));
        }
    }
}
