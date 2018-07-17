<?php
declare(strict_types=1);

namespace Yireo\ServerPush\Plugin;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Plugin to add a Link header for each static asset
 */
class ResponsePlugin
{
    /** @var  HttpRequest */
    protected $request;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var AppState
     */
    private $appState;

    /**
     * \Magento\Store\Model\StoreManagerInterface $storeManager
     *
     * @param HttpRequest $request
     * @param StoreManagerInterface $storeManager
     * @param AppState $appState
     */
    public function __construct(
        HttpRequest $request,
        StoreManagerInterface $storeManager,
        AppState $appState
    ) {
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->appState = $appState;
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
    protected function shouldAddLinkHeader(HttpResponse $response)
    {
        if ($this->appState->getAreaCode() !== 'frontend') {
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

        return true;
    }

    /**
     * Add Link header to the response, based on the content
     *
     * @param HttpResponse $response
     *
     * @throws NoSuchEntityException
     */
    protected function addLinkHeader(HttpResponse $response)
    {
        $values = [];
        $crawler = new Crawler($response->getContent());

        // Find all stylesheets
        $stylesheets = $crawler->filter('link[as="style"]')->extract(['href']);
        foreach ($stylesheets as $link) {
            $link = $this->prepareLink($link);
            if (!empty($link)) {
                $values[] = "<".$link.">; rel=preload; as=style";
            }
        }

        // Find all scripts
        $scripts = $crawler->filter('script[type="text/javascript"][src]')->extract(['src']);
        foreach ($scripts as $link) {
            $link = $this->prepareLink($link);
            if (!empty($link)) {
                $values[] = "<".$link.">; rel=preload; as=script";
            }
        }

        // Find all images
        $images = $crawler->filter('img[src]')->extract(['src']);
        foreach ($images as $link) {
            $link = $this->prepareLink($link);
            if (!empty($link)) {
                $values[] = "<".$link.">; rel=preload; as=image";
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
    protected function prepareLink(string $link): string
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
        if ( ! in_array($scheme, ['http', 'https'])) {
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
