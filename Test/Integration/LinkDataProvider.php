<?php declare(strict_types=1);

namespace Yireo\LinkPreload\Test\Integration;

class LinkDataProvider
{
    public function getLinks(): array
    {
        return [
            ['style', 'css/styles-l.css'],
            ['style', 'css/styles-m.css'],
            ['style', 'css/print.css'],
            ['style', 'mage/calendar.css'],
            ['script', 'requirejs/require.js'],
            ['script', 'jquery.js'],
            ['script', 'mage/bootstrap.js'],
        ];
    }
}
