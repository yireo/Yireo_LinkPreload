<?php

namespace Yireo\ServerPush\Plugin;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Plugin to add a Link header for each static asset
 */
class ResponsePlugin
{
    /** @var  HttpRequest */
    protected $request;

    /** @var  string */
    protected $baseUrl;

    /**
     * \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(HttpRequest $request, StoreManagerInterface $storeManager)
    {
        $this->request = $request;
        $this->baseUrl = $storeManager->getStore()->getBaseUrl();
    }

    /**
     * Intercept the sendResponse call
     *
     * @param ResponseInterface $response
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
     * @return bool
     */
    protected function shouldAddLinkHeader(HttpResponse $response)
    {
        return (!$response->isRedirect() && !$this->request->isAjax() && $response->getContent());
    }

    /**
     * Add Link header to the response, based on the content
     *
     * @param HttpResponse $response
     */
    protected function addLinkHeader(HttpResponse $response)
    {
        $values = [];
        $crawler = new Crawler($response->getContent());

        // Find all stylesheets
        $stylesheets = $crawler->filter('link[rel="stylesheet"][type="text/css"][href]')->extract(['href']);
        foreach ($stylesheets as $link) {
            $link = $this->prepareLink($link);
            if ($link) {
                $values[] = "<{$link}>; rel=preload; as=style";
            }
        }

        // Find all scripts
        $scripts = $crawler->filter('script[type="text/javascript"][src]')->extract(['src']);
        foreach ($scripts as $link) {
            $link = $this->prepareLink($link);
            if ($link) {
                $values[] = "<{$link}>; rel=preload; as=script";
            }
        }

        if ($values) {
            $response->setHeader('Link', implode(', ', $values));
        }
    }

    /**
     * Prepare and check the link
     *
     * @param $link
     * @return string
     */
    protected function prepareLink($link)
    {
        if (empty($link)) {
            return null;
        }

        // Absolute urls
        if ($link[0] === '/') {
            return $link;
        }

        // If it's not absolute, we only parse absolute urls
        $scheme = parse_url($link, PHP_URL_SCHEME);
        if ( ! in_array($scheme, ['http', 'https'])) {
            return null;
        }

        // Replace the baseUrl to save some chars.
        if (strpos($link, $this->baseUrl) === 0) {
            $link = '/' . ltrim(substr($link, strlen($this->baseUrl)), '/');
        }

        return $link;
    }
}