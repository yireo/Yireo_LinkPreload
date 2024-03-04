<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Plugin;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
     * @param Config $config
     * @param HttpRequest $request
     * @param CookieManagerInterface $cookieManager
     * @param LinkParser $linkParser
     */
    public function __construct(
        Config $config,
        HttpRequest $request,
        CookieManagerInterface $cookieManager,
        LinkParser $linkParser
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->cookieManager = $cookieManager;
        $this->linkParser = $linkParser;
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

        $this->linkParser->parse($response);
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
        if ($response->getContent() === false) {
            return false;
        }

        if (false === stristr((string)$content, '<!doctype html>')) {
            return false;
        }

        if ($this->config->useCookie()) {
            if ((int)$this->cookieManager->getCookie('linkpreload') === 1) {
                return false;
            }
        }

        return true;
    }
}
