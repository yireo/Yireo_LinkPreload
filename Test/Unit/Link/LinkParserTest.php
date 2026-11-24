<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Test\Unit\Link;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
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
        $body = $this->parse('<script type="text/javascript" src="/static/a.js?x=1&amp;z=1"></script>');

        $this->assertStringContainsString('href="/static/a.js?x=1&amp;z=1"', $this->getPreloadTags($body));
    }

    /**
     * @dataProvider getMaliciousUrls
     */
    public function testUrlWithUnsafeCharactersIsSkipped(string $src)
    {
        $body = $this->parse(
            '<script type="text/javascript" src="' . $src . '"></script>'
            . '<script type="text/javascript" src="/static/ok.js"></script>'
        );

        $this->assertSame('</static/ok.js>; rel="preload"; as="script"', $this->header);
        $this->assertSame(
            '<link rel="preload" as="script" href="/static/ok.js" />',
            $this->getPreloadTags($body)
        );
    }

    public static function getMaliciousUrls(): array
    {
        return [
            'greater than' => ['/a.js&gt;; rel=stylesheet'],
            'less than' => ['/a.js&lt;x'],
            'quote' => ['/a.js&quot;x'],
            'single quote' => ["/a.js&#39;x"],
            'space' => ['/a.js x'],
            'newline entity' => ['/a.js&#10;Set-Cookie: x=1'],
            'carriage return entity' => ['/a.js&#13;&#10;X: 1'],
            'tab entity' => ['/a.js&#9;x'],
            'injected entry' => ['/a.js&gt;; rel=preload, &lt;https://evil.example/x.css&gt;; rel=stylesheet'],
            'javascript scheme' => ['javascript:alert(1)'],
        ];
    }

    public function testCommaAndSemicolonAreEncodedInHeaderOnly()
    {
        $body = $this->parse('<script type="text/javascript" src="/static/a.js?x=1,2;3"></script>');

        $this->assertSame('</static/a.js?x=1%2C2%3B3>; rel="preload"; as="script"', $this->header);
        $this->assertStringContainsString('href="/static/a.js?x=1,2;3"', $body);
    }

    public function testNonAsciiCharactersArePercentEncoded()
    {
        $this->parse('<script type="text/javascript" src="/static/café.js"></script>');

        $this->assertSame('</static/caf%C3%A9.js>; rel="preload"; as="script"', $this->header);
    }

    public function testDollarAndBackslashInUrlAreNotTreatedAsBackReference()
    {
        $body = $this->parse('<script type="text/javascript" src="/static/a$0\\1.js"></script>');

        $this->assertStringContainsString('href="/static/a$0\\1.js"', $body);
        $this->assertSame(1, substr_count($body, '</title>'));
    }

    public function testTagsAreInsertedAfterHeadTitleOnly()
    {
        $html = '<!doctype html><html><head>'
            . '<script type="text/javascript" src="/static/a.js"></script></head>'
            . '<body><svg><title>Icon</title></svg></body></html>';
        $body = $this->parse('', $html);

        $this->assertStringContainsString('<svg><title>Icon</title></svg>', $body);
        $this->assertStringContainsString(
            "<head>\n" . '<link rel="preload" as="script" href="/static/a.js" />',
            $body
        );
    }

    public function testTagsAreNotInsertedWithoutHead()
    {
        $html = '<svg><title>Icon</title></svg><script type="text/javascript" src="/static/a.js"></script>';
        $body = $this->parse('', $html);

        $this->assertSame($html, $body);
        $this->assertSame('</static/a.js>; rel="preload"; as="script"', $this->header);
    }

    public function testMaximumNumberOfLinksIsApplied()
    {
        $this->parse(
            '<script type="text/javascript" src="/static/a.js"></script>'
            . '<script type="text/javascript" src="/static/b.js"></script>'
            . '<script type="text/javascript" src="/static/c.js"></script>',
            null,
            2
        );

        $this->assertSame(
            '</static/a.js>; rel="preload"; as="script", </static/b.js>; rel="preload"; as="script"',
            $this->header
        );
    }

    public function testMaximumHeaderLengthIsApplied()
    {
        $entry = '</static/a.js>; rel="preload"; as="script"';
        $this->parse(
            '<script type="text/javascript" src="/static/a.js"></script>'
            . '<script type="text/javascript" src="/static/b.js"></script>',
            null,
            0,
            strlen($entry) + 1
        );

        $this->assertSame($entry, $this->header);
    }

    public function testStylesArePrioritizedOverScriptsInHeader()
    {
        $this->parse(
            '<script type="text/javascript" src="/static/a.js"></script>'
            . '<link rel="stylesheet" href="/static/a.css" />',
            null,
            1,
            0,
            false
        );

        $this->assertSame('</static/a.css>; rel="preload"; as="style"', $this->header);
    }

    public function testExternalUrlContainingBaseUrlIsNotMadeRelative()
    {
        $this->parse(
            '<script type="text/javascript" src="https://cdn.example.org/https://example.com/a.js"></script>'
        );

        $this->assertSame(
            '<https://cdn.example.org/https://example.com/a.js>; rel="preload"; as="script"',
            $this->header
        );
    }

    public function testBaseUrlIsMadeRelative()
    {
        $this->parse('<script type="text/javascript" src="https://example.com/static/a.js"></script>');

        $this->assertSame('</static/a.js>; rel="preload"; as="script"', $this->header);
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

    private function parse(
        string $scriptTag,
        ?string $html = null,
        int $maxLinks = 0,
        int $maxHeaderLength = 0,
        bool $criticalEnabled = true
    ): string {
        $html = $html ?? '<html><head><title>T</title>' . $scriptTag . '</head><body></body></html>';
        $this->header = '';

        /** @var HttpResponse&Stub $response */
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
        $config->method('isCriticalEnabled')->willReturn($criticalEnabled);
        $config->method('skipImages')->willReturn(true);
        $config->method('getMaxLinks')->willReturn($maxLinks);
        $config->method('getMaxHeaderLength')->willReturn($maxHeaderLength);

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
