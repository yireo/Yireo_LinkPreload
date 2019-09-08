<?php
declare(strict_types=1);

namespace Yireo\LinkPreload\Plugin;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Yireo\LinkPreload\Config\Config;

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
     * @var LayoutInterface
     */
    private $layout;

    /**
     * @var Repository
     */
    private $assetRepository;

    /**
     * @var array
     */
    private $values = [];

    /**
     * @param Config $config
     * @param HttpRequest $request
     * @param StoreManagerInterface $storeManager
     * @param CookieManagerInterface $cookieManager
     * @param LayoutInterface $layout
     * @param Repository $assetRepository
     */
    public function __construct(
        Config $config,
        HttpRequest $request,
        StoreManagerInterface $storeManager,
        CookieManagerInterface $cookieManager,
        LayoutInterface $layout,
        Repository $assetRepository
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->cookieManager = $cookieManager;
        $this->layout = $layout;
        $this->assetRepository = $assetRepository;
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
            $this->addLinkHeadersFromResponse($response);
            $this->addLinkHeadersFromLayout();
            $this->processHeaders($response);
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
        if ($this->config->enabled() === false) {
            return false;
        }

        if ($response->isRedirect()) {
            return false;
        }

        if ($this->request->isAjax()) {
            return false;
        }

        if ($response->getContent() === false) {
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
     */
    private function processHeaders(HttpResponse $response)
    {
        if (!empty($this->values)) {
            $response->setHeader('Link', implode(', ', $this->values));
            $this->values = [];
        }
    }

    /**
     * Add Link header to the response, based on the content
     *
     * @param HttpResponse $response
     *
     * @throws NoSuchEntityException
     */
    private function addLinkHeadersFromResponse(HttpResponse $response)
    {
        $crawler = new Crawler($response->getContent());

        // Find all stylesheets
        $stylesheets = $crawler->filter('link[rel="stylesheet"]')->extract(['href']);
        $this->addStylesheetsAsLinkHeader($stylesheets);

        // Find all scripts
        $scripts = $crawler->filter('script[type="text/javascript"][src]')->extract(['src']);
        $this->addScriptsAsLinkHeader($scripts);

        // Find all images
        if ($this->config->skipImages() === false) {
            $images = $crawler->filter('img[src]')->extract(['src']);
            $this->addImagesAsLinkHeader($images);
        }
    }

    /**
     * @param array $stylesheets
     * @throws NoSuchEntityException
     */
    private function addStylesheetsAsLinkHeader(array $stylesheets)
    {
        foreach ($stylesheets as $stylesheet) {
            $this->addStylesheetAsLinkHeader($stylesheet);
        }
    }

    /**
     * @param string $stylesheet
     * @throws NoSuchEntityException
     */
    private function addStylesheetAsLinkHeader(string $stylesheet)
    {
        $stylesheet = $this->prepareLink($stylesheet);
        if (empty($stylesheet)) {
            return;
        }

        $this->values[] = "<" . $stylesheet . ">; rel=preload; as=style";
    }

    /**
     * @param array $scripts
     * @throws NoSuchEntityException
     */
    private function addScriptsAsLinkHeader(array $scripts)
    {
        foreach ($scripts as $script) {
            $this->addScriptAsLinkHeader($script);
        }
    }

    /**
     * @param string $script
     * @throws NoSuchEntityException
     */
    private function addScriptAsLinkHeader(string $script)
    {
        $script = $this->prepareLink($script);
        if (!empty($script)) {
            $this->values[] = "<" . $script . ">; rel=preload; as=script";
        }
    }

    /**
     * @param array $images
     * @throws NoSuchEntityException
     */
    private function addImagesAsLinkHeader(array $images)
    {
        foreach ($images as $image) {
            $this->addImageAsLinkHeader($image);
        }
    }

    /**
     * @param array $images
     * @throws NoSuchEntityException
     */
    private function addImageAsLinkHeader(string $image)
    {
        $image = $this->prepareLink($image);
        if (!empty($image)) {
            $this->values[] = "<" . $image . ">; rel=preload; as=image";
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    private function addLinkHeadersFromLayout()
    {
        $block = $this->layout->getBlock('link-preload');
        if (!$block instanceof Template) {
            return;
        }

        $scripts = $block->getData('scripts');
        if (!empty($scripts)) {
            foreach ($scripts as $script) {
                $script = $this->assetRepository->getUrlWithParams($script, []);
                $this->addScriptAsLinkHeader($script);
            }
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
