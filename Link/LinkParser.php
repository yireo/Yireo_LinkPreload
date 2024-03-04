<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Link;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Yireo\LinkPreload\Config\Config;

class LinkParser
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LayoutInterface
     */
    private $layout;

    /**
     * @var Repository
     */
    private $assetRepository;

    /**
     * @var Link[]
     */
    private $links = [];

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param LayoutInterface $layout
     * @param Repository $assetRepository
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        LayoutInterface $layout,
        Repository $assetRepository
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->layout = $layout;
        $this->assetRepository = $assetRepository;
    }

    /**
     * Intercept the sendResponse call
     *
     * @param HttpResponse $response
     *
     * @throws NoSuchEntityException
     */
    public function parse(HttpResponse $response)
    {
        $this->addLinkHeadersFromResponse($response);
        $this->addLinkHeadersFromLayout();
        $this->processHeaders($response);
        $this->processBody($response);
        $this->reset();
    }

    /**
     * @param HttpResponse $response
     */
    private function processHeaders(HttpResponse $response)
    {
        if (empty($this->links)) {
            return;
        }

        $links = [];
        foreach ($this->links as $link) {
            $links[] = '<'.$link->getUrl().'>; rel="preload"; as="'.$link->getType().'"';
        }

        $response->setHeader('Link', implode(', ', $links));
    }

    /**
     * @param HttpResponse $response
     */
    private function processBody(HttpResponse $response)
    {
        if (empty($this->links)) {
            return;
        }

        $body = $response->getBody();
        $newTags = [];

        foreach ($this->links as $link) {
            $originalTag = $link->getOriginalHtml();
            if (strstr($originalTag, 'rel="preload"')) {
                continue;
            }

            $createTag = '<link rel="preload" as="'.$link->getType().'"';
            if ($link->getType() === 'font') {
                $createTag .= ' crossorigin="anonymous"';
            }
            $createTag .= ' href="'.$link->getUrl().'" />';

            $newTags[] = $createTag;
        }

        $body = preg_replace('^</title>^', "</title>\n".implode("\n", $newTags), $body, 1);
        $response->setBody($body);
    }

    /**
     * Reset the links again
     */
    private function reset()
    {
        $this->links = [];
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
        $crawler = new Crawler((string)$response->getContent());

        if (!$this->config->isCriticalEnabled()) {
            $this->addStylesheetsAsLinkHeader($crawler->filter('link[rel="stylesheet"]'));
        }

        $this->addScriptsAsLinkHeader($crawler->filter('script[type="text/javascript"][src]'));

        if ($this->config->skipImages() === false) {
            $this->addImagesAsLinkHeader($crawler->filter('img[src]'));
        }
    }

    /**
     * @param Crawler $crawler
     * @throws NoSuchEntityException
     */
    private function addStylesheetsAsLinkHeader(Crawler $crawler)
    {
        $crawler->each(function (Crawler $crawler) {
            $this->addLink($crawler->extract(['href'])[0], 'style', $crawler->outerHtml());
        });
    }

    /**
     * @param Crawler $crawler
     * @throws NoSuchEntityException
     */
    private function addScriptsAsLinkHeader(Crawler $crawler)
    {
        $crawler->each(function (Crawler $crawler) {
            $this->addLink($crawler->extract(['src'])[0], 'script', $crawler->outerHtml());
        });
    }

    /**
     * @param Crawler $crawler
     * @throws NoSuchEntityException
     */
    private function addImagesAsLinkHeader(Crawler $crawler)
    {
        $crawler->each(function (Crawler $crawler) {
            $loadType = $crawler->extract(['loading']);
            if (empty($loadType) || $loadType[0] !== "lazy") {
                $this->addLink($crawler->extract(['src'])[0], 'image', $crawler->outerHtml());
            }
        });
    }

    /**
     * Construct link according to W3 specs, see https://www.w3.org/TR/preload/
     *
     * @param string $link
     * @param string $type
     * @param string $originalTag
     * @throws NoSuchEntityException
     */
    private function addLink(string $link, string $type, string $originalTag)
    {
        $link = $this->prepareLink($link);
        if (empty($link)) {
            return;
        }

        $this->links[$link] = new Link($link, $type, $originalTag);
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
                    $this->addLink($link, $type, '');
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

        if ($link[0] === '/') {
            return $link;
        }

        if (preg_match('/^(http|https):\/\//', $link) || preg_match('/^\/\//', $link)) {
            if (strstr($link, $baseUrl)) {
                $link = '/'.ltrim(substr($link, strlen($baseUrl)), '/');
            }

            return $link;
        }

        $scheme = parse_url($link, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            return '';
        }

        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        if (strpos($link, $baseUrl) === 0) {
            $link = '/'.ltrim(substr($link, strlen($baseUrl)), '/');
        }

        return $link;
    }
}
