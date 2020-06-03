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
            $this->addLink($stylesheet, 'style');
        }
    }

    /**
     * @param array $scripts
     * @throws NoSuchEntityException
     */
    private function addScriptsAsLinkHeader(array $scripts)
    {
        foreach ($scripts as $script) {
            $this->addLink($script, 'script');
        }
    }

    /**
     * @param array $images
     * @throws NoSuchEntityException
     */
    private function addImagesAsLinkHeader(array $images)
    {
        foreach ($images as $image) {
            $this->addLink($image, 'image');
        }
    }

    /**
     * @param string $link
     * @param string $type
     * @throws NoSuchEntityException
     */
    private function addLink(string $link, string $type)
    {
        $link = $this->prepareLink($link);
        if (!empty($link)) {
            $this->values[] = "<" . $link . ">; rel=preload; as=" . $type;
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

        $types = [
            'scripts' => 'script',
            'fonts' => 'font',
            'images' => 'image',
            'styles' => 'style',
        ];

        foreach ($types as $typeBlock => $type) {
            $links = $block->getData($typeBlock);
            if (!empty($links)) {
                foreach ($links as $link) {
                    $link = $this->assetRepository->getUrlWithParams($link, []);
                    $this->addLink($link, $type);
                }
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

        $baseUrl = $this->storeManager->getStore()->getBaseUrl();

        // Absolute urls
        if ($link[0] === '/') {
            return $link;
        }

        // Full urls
        if (preg_match('/^(http|https):\/\//', $link) || preg_match('/^\/\//', $link)) {
            if (strstr($link, $baseUrl)) {
                $link = '/' . ltrim(substr($link, strlen($baseUrl)), '/');
            }

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
