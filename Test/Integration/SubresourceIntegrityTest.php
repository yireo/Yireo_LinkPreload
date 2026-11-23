<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Test\Integration;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Yireo\LinkPreload\Link\LinkParser;

class SubresourceIntegrityTest extends TestCase
{
    /**
     * @magentoAppArea frontend
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     */
    public function testScriptWithIntegrityGetsMatchingPreload()
    {
        $response = $this->parse(
            '<script type="text/javascript" integrity="sha256-abc" crossorigin="anonymous" src="'
            . $this->getBaseUrl() . 'static/requirejs/require.js"></script>'
            . '<script type="text/javascript" src="' . $this->getBaseUrl() . 'static/plain.js"></script>'
        );

        $body = (string)$response->getBody();
        $this->assertStringContainsString(
            '<link rel="preload" as="script" href="/static/requirejs/require.js"'
            . ' integrity="sha256-abc" crossorigin="anonymous" />',
            $body
        );
        $this->assertStringContainsString('<link rel="preload" as="script" href="/static/plain.js" />', $body);

        $linkHeader = $response->getHeader('Link')->getFieldValue();
        $links = explode(', ', $linkHeader);
        $this->assertContains(
            '</static/requirejs/require.js>; rel="preload"; as="script"; crossorigin="anonymous"',
            $links
        );
        $this->assertContains('</static/plain.js>; rel="preload"; as="script"', $links);
        $this->assertStringNotContainsString('integrity', $linkHeader);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     */
    public function testParsingTwiceDoesNotLeakAttributesBetweenResponses()
    {
        $this->parse('<script type="text/javascript" integrity="sha256-abc" src="/static/a.js"></script>');
        $response = $this->parse('<script type="text/javascript" src="/static/b.js"></script>');

        $body = (string)$response->getBody();
        $this->assertStringNotContainsString('integrity', $body);
        $this->assertStringNotContainsString('/static/a.js', $response->getHeader('Link')->getFieldValue());
    }

    private function parse(string $scripts): HttpResponse
    {
        $objectManager = Bootstrap::getObjectManager();
        $html = '<html><head><title>T</title>' . $scripts . '</head><body></body></html>';

        $response = $objectManager->create(HttpResponse::class);
        $response->setBody($html);
        $objectManager->get(LinkParser::class)->parse($response);

        return $response;
    }

    private function getBaseUrl(): string
    {
        return Bootstrap::getObjectManager()
            ->get(StoreManagerInterface::class)
            ->getStore()
            ->getBaseUrl();
    }
}
