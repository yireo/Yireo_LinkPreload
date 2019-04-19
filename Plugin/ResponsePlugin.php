<?php
declare(strict_types=1);

namespace Yireo\ServerPush\Plugin;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Yireo\ServerPush\Config\Config;

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
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @param Config $config
     * @param HttpRequest $request
     * @param StoreManagerInterface $storeManager
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        Config $config,
        HttpRequest $request,
        StoreManagerInterface $storeManager,
        CookieManagerInterface $cookieManager
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->cookieManager = $cookieManager;
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
        if ($response instanceof HttpResponse && $this->shouldAddLinkHeader($response)) {
            $this->addLinkHeader($response);
        }
    }

    /**
     * Check if the headers needs to be sent.
     *
     * @param HttpResponse $response
     *
     * @return bool
     * @throws LocalizedException
     */
    private function shouldAddLinkHeader(HttpResponse $response)
    {
        if (!$this->config->enabled()) {
            return false;
        }

        if ($response->isRedirect()) {
            return false;
        }

        if ($this->request->isAjax()) {
            return false;
        }

        if (!$response->getContent()) {
            return false;
        }

        if ($this->config->useCookie()) {
            if ((int)$this->cookieManager->getCookie('serverpush') === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add Link header to the response, based on the content
     *
     * @param HttpResponse $response
     *
     * @throws NoSuchEntityException
     */
    private function addLinkHeader(HttpResponse $response)
    {
        $values = [];
        $crawler = new Crawler($response->getContent());

        // Find all stylesheets
        $stylesheets = $crawler->filter('link[as="style"]')->extract(['href']);
        foreach ($stylesheets as $link) {
            $link = $this->prepareLink($link);
            if (!empty($link)) {
                $values[] = "<" . $link . ">; rel=preload; as=style";
            }
        }

        // Find all scripts
        $scripts = $crawler->filter('script[type="text/javascript"][src]')->extract(['src']);
        foreach ($scripts as $link) {
            $link = $this->prepareLink($link);
            if (!empty($link)) {
                $values[] = "<" . $link . ">; rel=preload; as=script";
            }
        }

        // Find all images
        $images = $crawler->filter('img[src]')->extract(['src']);
        foreach ($images as $link) {
            $link = $this->prepareLink($link);
            if (!empty($link)) {
                $values[] = "<" . $link . ">; rel=preload; as=image";
            }
        }

        if ($values) {
            $response->setHeader('Link', implode(', ', $values));
        }
    }

    /**
     * Prepare and check the link
     *
     * @param string $link
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function prepareLink(string $link): string
    {
        if (empty($link)) {
            return '';
        }

        // Absolute urls
        if ($link[0] === '/') {
            return $link;
        }

        // If it's not absolute, we only parse absolute urls
        $scheme = parse_url($link, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            return '';
        }

        // Replace the baseUrl to save some chars.
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        if (strpos($link, $baseUrl) === 0) {
            $link = '/' . ltrim(substr($link, strlen($baseUrl)), '/');
        }

        return $link;
    }
}
