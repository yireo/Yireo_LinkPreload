<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Test\Integration;

use Magento\Framework\App\Response\Http;

class BodyTest extends AbstractTestCase
{
    /**
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     */
    public function testIfLinkHeadersExistsWhenModuleIsEnabled()
    {
        $this->assertEnabledValue(1);
        $this->dispatch('/');
        /** @var Http $response */
        $response = $this->getResponse();
        $body = (string)$response->getBody();
        foreach ((new LinkDataProvider())->getLinks() as $link) {
            $this->assertBodyContainsLink($link[0], $link[1], $body);
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
        if (constant('TESTS_CLEANUP') === 'disabled') {
            $this->markTestSkipped('Test does not work with TESTS_CLEANUP disabled');
        }

        $this->assertEnabledValue(1);
        $this->dispatch('/');
        /** @var Http $response */
        $response = $this->getResponse();
        $body = (string)$response->getBody();
        $this->assertNotEmpty($body);

        foreach ((new LinkDataProvider())->getLinks() as $link) {
            $this->assertBodyContainsLink($link[0], $link[1], $body);
        }
    }

    private function assertBodyContainsLink(string $type, string $uri, string $body)
    {
        preg_match_all('#<link(.*)rel="preload"(.*)>#', $body, $matches);

        $foundUri = false;
        foreach ($matches[0] as $match) {
            if (strstr($match, $uri)) {
                $foundUri = true;
            }
        }

        $this->assertTrue($foundUri, 'URI "'.$uri.'" not found in body: '.$body);
    }
}
