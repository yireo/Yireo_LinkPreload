<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Test\Integration;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Yireo\LinkPreload\Plugin\ResponsePlugin;

class ResponsePluginTest extends TestCase
{
    private const SCRIPTS = '<script type="text/javascript" src="/static/a.js"></script>'
        . '<script type="text/javascript" src="/static/b.js"></script>'
        . '<script type="text/javascript" src="/static/c.js"></script>';

    /**
     * @magentoAppArea frontend
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     */
    public function testHtmlResponseIsModified()
    {
        $response = $this->send($this->getHtml(self::SCRIPTS));

        $this->assertStringContainsString('/static/a.js', $this->getLinkHeader($response));
        $this->assertStringContainsString('rel="preload"', (string)$response->getBody());
    }

    /**
     * @magentoAppArea frontend
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     */
    public function testHtmlResponseWithLeadingWhitespaceIsModified()
    {
        $response = $this->send("\xEF\xBB\xBF\n  " . $this->getHtml(self::SCRIPTS), 'text/html; charset=UTF-8');

        $this->assertStringContainsString('/static/a.js', $this->getLinkHeader($response));
    }

    /**
     * @magentoAppArea frontend
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     * @dataProvider getNonHtmlContentTypes
     */
    public function testNonHtmlContentTypeIsNotModified(string $contentType)
    {
        $html = $this->getHtml(self::SCRIPTS);
        $response = $this->send($html, $contentType);

        $this->assertSame($html, (string)$response->getBody());
        $this->assertSame('', $this->getLinkHeader($response));
    }

    public static function getNonHtmlContentTypes(): array
    {
        return [
            ['application/json'],
            ['text/plain'],
            ['application/javascript'],
        ];
    }

    /**
     * @magentoAppArea frontend
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     */
    public function testBodyWithEmbeddedDoctypeIsNotModified()
    {
        $body = json_encode(['html' => $this->getHtml(self::SCRIPTS)]);
        $response = $this->send((string)$body);

        $this->assertSame($body, (string)$response->getBody());
        $this->assertSame('', $this->getLinkHeader($response));
    }

    /**
     * @magentoAppArea frontend
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     */
    public function testMaliciousUrlsDoNotBreakOrInjectHeaders()
    {
        $response = $this->send($this->getHtml(
            '<script type="text/javascript" src="/static/a.js&#13;&#10;X-Injected: 1"></script>'
            . '<link rel="stylesheet" href="/x.css&gt;; rel=stylesheet, &lt;https://evil.example/x.css" />'
            . '<script type="text/javascript" src="/static/ok.js"></script>'
        ));

        $headers = $response->getHeaders()->toString();
        $this->assertStringNotContainsString('X-Injected', $headers);
        $this->assertStringNotContainsString('evil.example', $headers);
        $this->assertStringContainsString('</static/ok.js>; rel="preload"; as="script"', $headers);
        $this->assertStringNotContainsString('evil.example', $this->getPreloadTags($response));
    }

    /**
     * @magentoAppArea frontend
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     * @magentoAdminConfigFixture system/yireo_linkpreload/max_links 2
     * @magentoAdminConfigFixture system/yireo_linkpreload/max_header_length 0
     */
    public function testMaximumNumberOfLinksIsApplied()
    {
        $header = $this->getLinkHeader($this->send($this->getHtml(self::SCRIPTS)));

        $this->assertStringContainsString('/static/b.js', $header);
        $this->assertStringNotContainsString('/static/c.js', $header);
    }

    /**
     * @magentoAppArea frontend
     * @magentoAdminConfigFixture system/yireo_linkpreload/enabled 1
     * @magentoAdminConfigFixture system/yireo_linkpreload/max_links 0
     * @magentoAdminConfigFixture system/yireo_linkpreload/max_header_length 60
     */
    public function testMaximumHeaderLengthIsApplied()
    {
        $header = $this->getLinkHeader($this->send($this->getHtml(self::SCRIPTS)));

        $this->assertNotSame('', $header);
        $this->assertLessThanOrEqual(60, strlen($header));
    }

    private function send(string $body, ?string $contentType = null): HttpResponse
    {
        $objectManager = Bootstrap::getObjectManager();
        $response = $objectManager->create(HttpResponse::class);
        $response->setBody($body);
        if ($contentType !== null) {
            $response->setHeader('Content-Type', $contentType, true);
        }

        $objectManager->get(ResponsePlugin::class)->beforeSendResponse($response);

        return $response;
    }

    private function getHtml(string $tags): string
    {
        return '<!doctype html><html><head><title>T</title>' . $tags . '</head><body></body></html>';
    }

    private function getLinkHeader(HttpResponse $response): string
    {
        $header = $response->getHeader('Link');
        if (!$header) {
            return '';
        }

        return (string)$header->getFieldValue();
    }

    private function getPreloadTags(HttpResponse $response): string
    {
        preg_match_all('#<link rel="preload"[^>]*>#', (string)$response->getBody(), $matches);
        return implode("\n", $matches[0]);
    }
}
