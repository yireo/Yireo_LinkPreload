<?php
/**
 * Plugin to add a Link header for each static asset
 */
namespace Yireo\ServerPush\Plugin;

use Magento\Framework\View\Asset\AssetInterface;
use Magento\Framework\View\Asset\GroupedCollection as Subject;

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

    /**
     * GroupedCollection constructor.
     * @param \Magento\Framework\App\Response\Http $httpResponse
     */
    public function __construct(
        \Magento\Framework\App\Response\Http $httpResponse
    )
    {
        $this->httpResponse = $httpResponse;
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
        $this->addHeaderLink($identifier, $asset->getUrl());

        return [$identifier, $asset, $properties];
    }

    /**
     * @param string $identifier
     * @param string $url
     */
    protected function addHeaderLink($identifier, $url)
    {
        if (preg_match('/\.js$/', $identifier)) {
            $this->addJsLink($url);
        }

        if (preg_match('/\.css/', $identifier)) {
            $this->addCssLink($url);
        }
    }

    /**
     * @param string $url
     */
    protected function addJsLink($url)
    {
        header('Link: '.$url.'; rel=preload; as=script', false);
        //$this->httpResponse->setHeader('Link', $url.'; rel=preload; as=script', false);
    }

    /**
     * @param string $url
     */
    protected function addCssLink($url)
    {
        header('Link: '.$url.'; rel=preload; as=style', false);
        //$this->httpResponse->setHeader('Link', $url.'; rel=preload; as=style', false);
    }
}
