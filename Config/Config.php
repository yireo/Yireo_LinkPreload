<?php
declare(strict_types=1);

namespace Yireo\LinkPreload\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

/***
 * Class Config
 * @package Yireo\LinkPreload\Config
 */
class Config
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Config constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     */
    public function enabled(): bool
    {
        return (bool)$this->scopeConfig->getValue('system/yireo_linkpreload/enabled');
    }

    /**
     * @return bool
     */
    public function useCookie(): bool
    {
        return (bool)$this->scopeConfig->getValue('system/yireo_linkpreload/use_cookie');
    }
}
