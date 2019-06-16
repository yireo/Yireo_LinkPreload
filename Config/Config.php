<?php
declare(strict_types=1);

namespace Yireo\LinkPreload\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;

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
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * Config constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ModuleListInterface $moduleList
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->moduleList = $moduleList;
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

    /**
     * @return bool
     */
    public function skipImages(): bool
    {
        if ($this->isModuleEnabled('Yireo_Webp2')) {
            return true;
        }

        return (bool)$this->scopeConfig->getValue('system/yireo_linkpreload/skip_images');
    }

    /**
     * @param string $module
     * @return bool
     */
    private function isModuleEnabled(string $module): bool
    {
        return (bool)$this->moduleList->has($module);
    }
}
