<?php
/**
 * Plugin to add a Link header for each static asset
 */
namespace Yireo\ServerPush\Plugin;

use Magento\Framework\View\Asset\AssetInterface;
use Magento\Framework\View\Asset\GroupedCollection as Subject;
use Zend\Http\Header\GenericMultiHeader;

/**
 * Class GroupedCollection
 * @package Yireo\ServerPush\Plugin
 */
class GroupedCollection
{
    /**
     * @var \Magento\Framework\App\Response\Http
     */
    protected $httpResponse;

    /** @var  string */
    protected $baseUrl;
    /**
     * GroupedCollection constructor.
     * @param \Magento\Framework\App\Response\Http $httpResponse
     */
    public function __construct(
        \Magento\Framework\App\Response\Http $httpResponse,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->httpResponse = $httpResponse;
        $this->baseUrl = $storeManager->getStore()->getBaseUrl();
    }

    /**
     * @param Subject $subject
     * @param string $identifier
     * @param AssetInterface $asset
     * @param array $properties
     * @return array
     */
    public function beforeAdd(Subject $subject, $identifier, AssetInterface $asset, array $properties = [])
    {
        $this->addHeaderLink($identifier, $asset->getUrl(), $asset->getContentType());

        return [$identifier, $asset, $properties];
    }

    /**
     * @param string $identifier
     * @param string $url
     */
    protected function addHeaderLink($identifier, $url, $contentType)
    {
        if (strpos($url, $this->baseUrl) === 0) {
            $url = '/' . ltrim(substr($url, strlen($this->baseUrl)), '/');
        }

        if ($contentType === 'js') {
            $this->addJsLink($url);
        }

        if ($contentType === 'css') {
            $this->addCssLink($url);
        }
    }

    /**
     * @param string $url
     */
    protected function addJsLink($url)
    {
        $this->addLinkHeader("<{$url}>; rel=preload; as=script");
    }

    /**
     * @param string $url
     */
    protected function addCssLink($url)
    {
        $this->addLinkHeader("<{$url}>; rel=preload; as=style");
    }

    protected function addLinkHeader($value)
    {
        $header = $this->httpResponse->getHeader('Link');

        if ($header) {
            $value = $header->getFieldValue() . ', ' . $value;
        }

        $this->httpResponse->setHeader('Link', $value, true);
    }
}
