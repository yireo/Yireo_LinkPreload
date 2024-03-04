<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Test\Integration;

use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Laminas\Http\Header\HeaderInterface;

class HeaderTest extends AbstractTestCase
{
    /**
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     */
    public function testIfLinkHeadersExistsWhenModuleIsEnabled()
    {
        $this->assertEnabledValue(1);
        $linkHeaders = $this->getLinkHeaders();
        $this->assertTrue(count($linkHeaders) > 0, 'No Link-headers found');
        foreach ((new LinkDataProvider())->getLinks() as $link) {
            $this->assertLinkHeadersContain($link[0], $link[1], $linkHeaders);
        }
    }

    /**
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     * @magentoCache full_page enabled
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testIfLinkHeadersExistsWhenModuleIsEnabledAndWithFullPageCache()
    {
        $this->assertEnabledValue(1);

        if (constant('TESTS_CLEANUP') === 'disabled') {
            $this->markTestSkipped('Test does not work with TESTS_CLEANUP disabled');
        }

        //$this->markTestSkipped('Test does not work with TESTS_CLEANUP enabled');
        $cache = $this->_objectManager->get(Manager::class);
        $cache->clean(['full_page']);

        $this->getLinkHeaders();
        $this->getLinkHeaders();
        $allHeaders = $this->getResponse()->getHeaders();
        $this->assertTrue(count($allHeaders) > 0, 'No headers found: '.$allHeaders->toString());

        $linkHeaders = $this->getLinkHeaders();
        $this->assertTrue(count($linkHeaders) > 0, 'No Link-headers found: '.$this->getResponse()->getHeaders()->toString());
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

    private function assertLinkHeadersContain(string $type, string $uri, array $linkHeaders): void
    {
        $match = false;
        foreach ($linkHeaders as $linkHeader) {
            if (strstr($linkHeader, $uri)) {
                $match = true;
            }
        }

        $this->assertTrue($match, 'Failed to find '.$type.' "'.$uri.'"');
    }
}
