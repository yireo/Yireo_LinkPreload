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
        $this->assertEnabledValue(1);
        $linkHeaders = $this->getLinkHeaders();
        $this->assertTrue(count($linkHeaders) > 0, 'No Link-headers found');
    }

    /**
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     * @magentoCache all enabled
     */
    public function testIfLinkHeadersExistsWhenModuleIsEnabledAndWithFullPageCache()
    {
        $this->assertEnabledValue(1);
        $this->getLinkHeaders();
        $this->getLinkHeaders();
        $linkHeaders = $this->getLinkHeaders();
        $this->assertTrue(count($linkHeaders) > 0, 'No Link-headers found');
    }

    /**
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 0
     */
    public function testIfLinkHeadersExistsWhenModuleIsDisabled()
    {
        $this->assertEnabledValue(0);
        $linkHeaders = $this->getLinkHeaders();
        $this->assertFalse(count($linkHeaders) > 0, 'Expected no Link-header, but found headers anyway');
    }

    private function getLinkHeaders(): array
    {
        $this->dispatch('/');
        $headers = $this->getResponse()->getHeaders();

        $linkHeaders = [];
        foreach ($headers as $header) {
            /** @var $header HeaderInterface */
            if (preg_match('/^Link:/', $header->toString())) {
                $this->assertValidLinkHeader($header->toString());
                $linkHeaders[] = $header->toString();
                break;
            }
        }

        return $linkHeaders;
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
            $linkParamName = trim($linkParam[0]);
            $linkParamValue = str_replace('"', '', trim($linkParam[1]));
            $this->assertContains($linkParamName, ['rel', 'as']);

            if ($linkParamName === 'rel') {
                $this->assertEquals('preload', $linkParamValue);
            }

            if ($linkParamName === 'as') {
                $this->assertContains($linkParamValue, ['style', 'script', 'font']);
            }
        }
    }

    private function assertEnabledValue(int $expectedValue): void
    {
        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = $this->_objectManager->get(ScopeConfigInterface::class);
        $this->assertEquals($expectedValue, $scopeConfig->getValue('system/yireo_linkpreload/enabled'));
    }

    private function reset(): void
    {
        $foundHeaders = [];
    }
}
