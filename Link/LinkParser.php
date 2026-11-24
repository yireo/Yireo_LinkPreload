<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Link;

use Composer\InstalledVersions;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Yireo\LinkPreload\Config\Config;

class LinkParser
{
    private const MAX_BODY_SIZE = 2097152;

    private const TYPE_PRIORITIES = [
        'style' => 1,
        'script' => 2,
        'image' => 3,
    ];

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

        $maxLinks = $this->config->getMaxLinks();
        $maxLength = $this->config->getMaxHeaderLength();
        $entries = [];
        $length = 0;

        foreach ($this->getPrioritizedLinks() as $link) {
            if ($maxLinks > 0 && count($entries) >= $maxLinks) {
                break;
            }

            $entry = '<'.$this->formatHeaderUrl($link->getUrl()).'>; rel="preload"; as="'.$link->getType().'"';
            if ($link->getCrossorigin() !== '') {
                $entry .= '; crossorigin="'.$link->getCrossorigin().'"';
            }

            $newLength = $length + strlen($entry) + (empty($entries) ? 0 : 2);
            if ($maxLength > 0 && $newLength > $maxLength) {
                break;
            }

            $entries[] = $entry;
            $length = $newLength;
        }

        if (empty($entries)) {
            return;
        }

        $response->setHeader('Link', implode(', ', $entries), true);
    }

    /**
     * @return Link[]
     */
    private function getPrioritizedLinks(): array
    {
        $links = array_values($this->links);
        usort($links, function (Link $a, Link $b) {
            return $this->getPriority($a) <=> $this->getPriority($b);
        });

        return $links;
    }

    /**
     * @param Link $link
     * @return int
     */
    private function getPriority(Link $link): int
    {
        if ($link->getOriginalHtml() === '') {
            return 0;
        }

        return self::TYPE_PRIORITIES[$link->getType()] ?? 4;
    }

    /**
     * @param string $url
     * @return string
     */
    private function formatHeaderUrl(string $url): string
    {
        return str_replace([',', ';'], ['%2C', '%3B'], $url);
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
            $createTag .= ' href="'.htmlspecialchars($link->getUrl(), ENT_QUOTES).'"';
            if ($link->getIntegrity() !== '') {
                $createTag .= ' integrity="'.htmlspecialchars($link->getIntegrity(), ENT_QUOTES).'"';
            }

            if ($link->getCrossorigin() !== '') {
                $createTag .= ' crossorigin="'.$link->getCrossorigin().'"';
            }

            $createTag .= ' />';

            $newTags[] = $createTag;
        }

        if (empty($newTags)) {
            return;
        }

        $body = (string)$body;
        $position = $this->getInsertPosition($body);
        if ($position === null) {
            return;
        }

        $response->setBody(substr_replace($body, "\n".implode("\n", $newTags), $position, 0));
    }

    /**
     * Find the position within the HTML head where to insert new tags
     *
     * @param string $body
     * @return int|null
     */
    private function getInsertPosition(string $body): ?int
    {
        $headEnd = stripos($body, '</head>');
        if ($headEnd === false) {
            return null;
        }

        $titleEnd = stripos($body, '</title>');
        if ($titleEnd !== false && $titleEnd < $headEnd) {
            return $titleEnd + strlen('</title>');
        }

        if (preg_match('/<head(\s[^>]*)?>/i', $body, $matches, PREG_OFFSET_CAPTURE)
            && $matches[0][1] < $headEnd) {
            return $matches[0][1] + strlen($matches[0][0]);
        }

        return null;
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
        $content = (string)$response->getContent();
        if (strlen($content) > self::MAX_BODY_SIZE) {
            return;
        }

        try {
            $crawler = new Crawler($content, useHtml5Parser: $this->useHtml5Parser());
        } catch (\Throwable $e) {
            return;
        }

        if (!$this->config->isCriticalEnabled()) {
            $this->addStylesheetsAsLinkHeader($crawler->filter('link[rel="stylesheet"]'));
        }

        $this->addScriptsAsLinkHeader($crawler->filter('script[type="text/javascript"][src]'));

        if ($this->config->skipImages() === false) {
            $this->addImagesAsLinkHeader($crawler->filter('img[src]'));
        }
    }

    /**
     * @return bool
     */
    private function useHtml5Parser(): bool
    {
        if (PHP_VERSION_ID < 80400) {
            return false;
        }

        try {
            $version = InstalledVersions::getVersion('symfony/dom-crawler');
        } catch (\OutOfBoundsException $e) {
            return false;
        }

        return $version !== null && version_compare($version, '7.4', '>=');
    }

    /**
     * @param Crawler $crawler
     * @throws NoSuchEntityException
     */
    private function addStylesheetsAsLinkHeader(Crawler $crawler)
    {
        $crawler->each(function (Crawler $crawler) {
            $priority = $crawler->extract(['fetchpriority'])[0];
            if ($priority && $priority != 'high') {
                return;
            }

            $link = $crawler->extract(['href'])[0];
            $this->addLink($link, $link, 'style', $crawler->outerHtml());
        });
    }

    /**
     * @param Crawler $crawler
     * @throws NoSuchEntityException
     */
    private function addScriptsAsLinkHeader(Crawler $crawler)
    {
        $crawler->each(function (Crawler $crawler) {
            $node = $crawler->getNode(0);
            if ($node instanceof \DOMElement && ($node->hasAttribute('defer') || $node->hasAttribute('async'))) {
                return;
            }

            [$link, $integrity] = $crawler->extract(['src', 'integrity'])[0];
            $integrity = (string)$integrity;
            $crossorigin = $this->getCrossorigin($crawler, $integrity !== '');
            $this->addLink($link, $link, 'script', $crawler->outerHtml(), $integrity, $crossorigin);
        });
    }

    /**
     * Return the CORS mode of the element, limited to the values allowed for a preload
     *
     * @param Crawler $crawler
     * @param bool $required
     * @return string
     */
    private function getCrossorigin(Crawler $crawler, bool $required): string
    {
        $node = $crawler->getNode(0);
        if (!$node instanceof \DOMElement || !$node->hasAttribute('crossorigin')) {
            return $required ? 'anonymous' : '';
        }

        if (strtolower(trim($node->getAttribute('crossorigin'), " \f\n\r\t\v\0")) === 'use-credentials') {
            return 'use-credentials';
        }

        return 'anonymous';
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
                $link =$crawler->extract(['src'])[0];
                $this->addLink($link, $link, 'image', $crawler->outerHtml());
            }
        });
    }

    /**
     * Construct link according to W3 specs, see https://www.w3.org/TR/preload/
     *
     * @param string $link
     * @param string $type
     * @param string $originalTag
     * @param string $integrity
     * @param string $crossorigin
     * @throws NoSuchEntityException
     */
    private function addLink(
        string $id,
        ?string $link,
        string $type,
        string $originalTag,
        string $integrity = '',
        string $crossorigin = ''
    ) {
        if ($link === null) {
            $this->removeLinks($id);
            return;
        }

        $link = $this->prepareLink($link);
        if ($link === '') {
            return;
        }

        $this->links[$id] = new Link($link, $type, $originalTag, $integrity, $crossorigin);
    }

    /**
     * Remove links by ID or by the end of their URL
     *
     * @param string $id
     */
    private function removeLinks(string $id)
    {
        if ($id === '') {
            return;
        }

        unset($this->links[$id]);
        foreach ($this->links as $key => $existingLink) {
            if (str_ends_with($existingLink->getUrl(), $id)) {
                unset($this->links[$key]);
            }
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    private function addLinkHeadersFromLayout()
    {
        $block = $this->layout->getBlock('link-preload');
        if (false === $block instanceof AbstractBlock) {
            return;
        }

        $types = [
            'scripts' => 'script',
            'fonts' => 'font',
            'images' => 'image',
            'styles' => 'style',
        ];

        foreach ($types as $argumentName => $linkType) {
            $links = (array)$block->getData($argumentName);
            if (!empty($links)) {
                foreach ($links as $linkId => $link) {
                    if ($link === false) {
                        $this->addLink($linkId, null, $linkType, '');
                        continue;
                    }

                    $link = $this->assetRepository->getUrlWithParams($link, []);
                    $this->addLink($linkId, $link, $linkType, '');
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
        if (empty($link) || preg_match('/[\x00-\x20\x7F<>"\']/', $link)) {
            return '';
        }

        $link = (string)preg_replace_callback('/[\x80-\xFF]/', function (array $matches) {
            return rawurlencode($matches[0]);
        }, $link);

        /** @var Store $store */
        $store = $this->storeManager->getStore();
        $baseUrl = $store->getBaseUrl();

        if ($link[0] === '/') {
            return $link;
        }

        if (preg_match('/^(http|https):\/\//', $link) || preg_match('/^\/\//', $link)) {
            if (str_starts_with($link, $baseUrl)) {
                $link = '/'.ltrim(substr($link, strlen($baseUrl)), '/');
            }

            return $link;
        }

        $scheme = parse_url($link, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            return '';
        }

        if (strpos($link, $baseUrl) === 0) {
            $link = '/'.ltrim(substr($link, strlen($baseUrl)), '/');
        }

        return $link;
    }
}
