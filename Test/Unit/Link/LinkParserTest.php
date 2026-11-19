<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Test\Unit\Link;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Yireo\LinkPreload\Config\Config;
use Yireo\LinkPreload\Link\LinkParser;

class LinkParserTest extends TestCase
{
    public function testScriptWithIntegrityGetsIntegrityAndCrossoriginInPreloadTag()
    {
        $body = $this->parse(
            '<script type="text/javascript" src="/static/a.js" integrity="sha256-abc" crossorigin="anonymous"></script>'
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="script" href="/static/a.js" integrity="sha256-abc" crossorigin="anonymous" />',
            $body
        );
    }

    public function testScriptWithIntegrityAndNoCrossoriginDefaultsToAnonymous()
    {
        $body = $this->parse('<script type="text/javascript" src="/static/a.js" integrity="sha256-abc"></script>');

        $this->assertStringContainsString(
            '<link rel="preload" as="script" href="/static/a.js" integrity="sha256-abc" crossorigin="anonymous" />',
            $body
        );
    }

    public function testScriptCrossoriginValueIsKept()
    {
        $body = $this->parse(
            '<script type="text/javascript" src="/static/a.js" integrity="sha256-abc" crossorigin="use-credentials"></script>'
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="script" href="/static/a.js" integrity="sha256-abc" crossorigin="use-credentials" />',
            $body
        );
    }

    public function testScriptWithoutIntegrityIsUnchanged()
    {
        $body = $this->parse('<script type="text/javascript" src="/static/a.js"></script>');

        $this->assertStringContainsString(
            '<link rel="preload" as="script" href="/static/a.js" />',
            $body
        );
    }

    private function parse(string $scriptTag): string
    {
        $html = '<html><head><title>T</title>' . $scriptTag . '</head><body></body></html>';

        /** @var HttpResponse|MockObject $response */
        $response = $this->createStub(HttpResponse::class);
        $response->method('getContent')->willReturn($html);
        $response->method('getBody')->willReturn($html);
        $result = '';
        $response->method('setBody')->willReturnCallback(
            function (string $body) use (&$result, $response) {
                $result = $body;
                return $response;
            }
        );

        $config = $this->createStub(Config::class);
        $config->method('isCriticalEnabled')->willReturn(true);
        $config->method('skipImages')->willReturn(true);

        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.com/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $parser = new LinkParser(
            $config,
            $storeManager,
            $this->createStub(LayoutInterface::class),
            $this->createStub(Repository::class)
        );
        $parser->parse($response);

        return $result;
    }
}
