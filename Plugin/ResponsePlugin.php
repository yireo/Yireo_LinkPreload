<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Plugin;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Yireo\LinkPreload\Config\Config;
use Yireo\LinkPreload\Link\LinkParser;

/**
 * Plugin to add a Link header for each static asset
 */
class ResponsePlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var HttpRequest
     */
    private $request;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var LinkParser
     */
    private $linkParser;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Config $config
     * @param HttpRequest $request
     * @param CookieManagerInterface $cookieManager
     * @param LinkParser $linkParser
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        HttpRequest $request,
        CookieManagerInterface $cookieManager,
        LinkParser $linkParser,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->cookieManager = $cookieManager;
        $this->linkParser = $linkParser;
        $this->logger = $logger;
    }

    /**
     * Intercept the sendResponse call
     *
     * @param ResponseInterface $response
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function beforeSendResponse(ResponseInterface $response)
    {
        if (!$response instanceof HttpResponse) {
            return;
        }

        if (!$this->shouldAddLinkHeader($response)) {
            return;
        }

        try {
            $this->linkParser->parse($response);
        } catch (\Throwable $exception) {
            $this->logger->error('Yireo_LinkPreload: '.$exception->getMessage(), ['exception' => $exception]);
        }
    }

    /**
     * Check if the headers needs to be sent.
     *
     * @param HttpResponse $response
     *
     * @return bool
     */
    private function shouldAddLinkHeader(HttpResponse $response)
    {
        if (false === $this->config->enabled()) {
            return false;
        }

        if ($response->isRedirect()) {
            return false;
        }

        if ($this->request->isAjax()) {
            return false;
        }

        $content = $response->getContent();
        if ($content === false) {
            return false;
        }

        if (false === $this->isHtmlContentType($response)) {
            return false;
        }

        if (false === $this->isHtmlDocument((string)$content)) {
            return false;
        }

        if ($this->config->useCookie()) {
            if ((int)$this->cookieManager->getCookie('linkpreload') === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param HttpResponse $response
     * @return bool
     */
    private function isHtmlContentType(HttpResponse $response): bool
    {
        $header = $response->getHeader('Content-Type');
        if (!$header) {
            return true;
        }

        $contentType = $header instanceof \ArrayIterator ? $header->current() : $header;
        $value = strtolower(trim((string)$contentType->getFieldValue(), " \f\n\r\t\v\0"));

        return $value === '' || str_starts_with($value, 'text/html');
    }

    /**
     * @param string $content
     * @return bool
     */
    private function isHtmlDocument(string $content): bool
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $content = ltrim($content, " \f\n\r\t\v\0");

        return 0 === stripos($content, '<!doctype html');
    }
}
