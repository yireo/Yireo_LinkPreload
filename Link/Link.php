<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Link;

class Link
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $originalHtml;

    /**
     * @var string
     */
    private $integrity;

    /**
     * @var string
     */
    private $crossorigin;

    /**
     * Link constructor.
     * @param string $url
     * @param string $type
     * @param string $originalHtml
     * @param string $integrity
     * @param string $crossorigin
     */
    public function __construct(
        string $url,
        string $type,
        string $originalHtml,
        string $integrity = '',
        string $crossorigin = ''
    ) {
        $this->url = $url;
        $this->type = $type;
        $this->originalHtml = $originalHtml;
        $this->integrity = $integrity;
        $this->crossorigin = $crossorigin;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getOriginalHtml(): string
    {
        return $this->originalHtml;
    }

    /**
     * @return string
     */
    public function getIntegrity(): string
    {
        return $this->integrity;
    }

    /**
     * @return string
     */
    public function getCrossorigin(): string
    {
        return $this->crossorigin;
    }

    /**
     * @return string
     */
    public function getHeader(): string
    {
        $newValue = [
            '<' . $this->getUrl() . '>',
            'rel=preload',
            'as=' . $this->getType(),
            'nopush',
        ];

        if ($this->getType() === 'font') {
            $newValue[] = 'crossorigin=anonymous';
        }

        return implode('; ', $newValue);
    }
}
