<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Plugin;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Result\Layout;
use Yireo\LinkPreload\Config\Config;
use Yireo\LinkPreload\Link\LinkParser;

/**
 * Plugin to add a Link header for each static asset
 */
class LayoutPlugin
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
    public function afterRenderResult(Layout $subject, Layout $layout, ResponseInterface $response)
    {
        /** @var HttpResponse $response */
        $block = $layout->getLayout()->getBlock('link-preload');
        if (false === $block instanceof AbstractBlock) {
            return;
        }

        //print_r($block->getData());exit;
    }
}
