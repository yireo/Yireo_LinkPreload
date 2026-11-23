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
    private string $header = '';

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
        $this->assertSame('</static/a.js>; rel="preload"; as="script"', $this->header);
    }

    public function testScriptWithEmptyCrossoriginAndIntegrityGetsAnonymous()
    {
        $body = $this->parse(
            '<script type="text/javascript" src="/static/a.js" integrity="sha256-abc" crossorigin=""></script>'
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="script" href="/static/a.js" integrity="sha256-abc" crossorigin="anonymous" />',
            $body
        );
    }

    public function testScriptWithCrossoriginWithoutIntegrityKeepsCrossorigin()
    {
        $body = $this->parse(
            '<script type="text/javascript" src="https://cdn.example.org/a.js" crossorigin="anonymous"></script>'
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="script" href="https://cdn.example.org/a.js" crossorigin="anonymous" />',
            $body
        );
    }

    public function testScriptWithEmptyCrossoriginWithoutIntegrityGetsAnonymous()
    {
        $body = $this->parse('<script type="text/javascript" src="/static/a.js" crossorigin></script>');

        $this->assertStringContainsString(
            '<link rel="preload" as="script" href="/static/a.js" crossorigin="anonymous" />',
            $body
        );
    }

    public function testInvalidCrossoriginValueBecomesAnonymous()
    {
        $body = $this->parse(
            '<script type="text/javascript" src="/static/a.js" integrity="sha256-abc" crossorigin="foobar"></script>'
        );

        $this->assertStringContainsString(
            '<link rel="preload" as="script" href="/static/a.js" integrity="sha256-abc" crossorigin="anonymous" />',
            $body
        );
        $this->assertStringNotContainsString('foobar', $this->getPreloadTags($body));
    }

    public function testCrossoriginValueIsCaseInsensitive()
    {
        $body = $this->parse(
            '<script type="text/javascript" src="/static/a.js" integrity="sha256-abc" crossorigin="USE-CREDENTIALS"></script>'
        );

        $this->assertStringContainsString('crossorigin="use-credentials" />', $body);
    }

    public function testIntegrityIsEscaped()
    {
        $body = $this->parse(
            '<script type="text/javascript" src="/static/a.js" integrity="sha256-&quot;&gt;&lt;x"></script>'
        );

        $tags = $this->getPreloadTags($body);
        $this->assertStringContainsString('integrity="sha256-&quot;&gt;&lt;x"', $tags);
        $this->assertStringNotContainsString('<x', $tags);
    }

    public function testHrefIsEscaped()
    {
        $body = $this->parse(
            '<script type="text/javascript" src="/static/a.js?x=&quot;&gt;&lt;y&amp;z=1"></script>'
        );

        $tags = $this->getPreloadTags($body);
        $this->assertStringContainsString('href="/static/a.js?x=&quot;&gt;&lt;y&amp;z=1"', $tags);
        $this->assertStringNotContainsString('<y', $tags);
    }

    public function testDeferredScriptWithIntegrityIsSkipped()
    {
        $body = $this->parse(
            '<script type="text/javascript" src="/static/a.js" integrity="sha256-abc" defer></script>'
        );

        $this->assertStringNotContainsString('rel="preload"', $body);
        $this->assertSame('', $this->header);
    }

    public function testAsyncScriptWithIntegrityIsSkipped()
    {
        $body = $this->parse(
            '<script type="text/javascript" src="/static/a.js" integrity="sha256-abc" async></script>'
        );

        $this->assertStringNotContainsString('rel="preload"', $body);
        $this->assertSame('', $this->header);
    }

    public function testLinkHeaderContainsCrossoriginForScriptWithIntegrity()
    {
        $this->parse(
            '<script type="text/javascript" src="/static/a.js" integrity="sha256-abc" crossorigin="use-credentials"></script>'
        );

        $this->assertSame('</static/a.js>; rel="preload"; as="script"; crossorigin="use-credentials"', $this->header);
        $this->assertStringNotContainsString('integrity', $this->header);
    }

    public function testLinkHeaderContainsCrossoriginForScriptWithCrossoriginOnly()
    {
        $this->parse('<script type="text/javascript" src="/static/a.js" crossorigin="anonymous"></script>');

        $this->assertSame('</static/a.js>; rel="preload"; as="script"; crossorigin="anonymous"', $this->header);
    }

    private function getPreloadTags(string $body): string
    {
        preg_match_all('#<link rel="preload"[^>]*>#', $body, $matches);
        return implode("\n", $matches[0]);
    }

    private function parse(string $scriptTag): string
    {
        $html = '<html><head><title>T</title>' . $scriptTag . '</head><body></body></html>';
        $this->header = '';

        /** @var HttpResponse|MockObject $response */
        $response = $this->createStub(HttpResponse::class);
        $response->method('getContent')->willReturn($html);
        $response->method('getBody')->willReturn($html);
        $result = $html;
        $response->method('setBody')->willReturnCallback(
            function (string $body) use (&$result, $response) {
                $result = $body;
                return $response;
            }
        );
        $response->method('setHeader')->willReturnCallback(
            function (string $name, string $value) use ($response) {
                if ($name === 'Link') {
                    $this->header = $value;
                }
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
