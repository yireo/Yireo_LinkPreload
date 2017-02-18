# Yireo ServerPush
Magento 2 extension to set HTTP Link headers for primary resources to allow for HTTP/2 Server Push.

## Backgrounds
With HTTP/2 a lot of cool things have been added to the web. However, for things to work really optimal a feature called
*Server Push* requires the HTML document to be sent with `Link` headers in the HTTP response. This extension simply adds
these `Link` headers to the HTTP response, allowing your Magento 2 site to be loaded faster under HTTP/2.

## Installation
First of all, make sure your Magento 2 hosting environment supports HTTP/2. If not, this extension will not aid anything. 

To install this extension, use the following command:

    composer require yireo/yireo_serverpush

Afterwards, enable this module, run the setup scripts and flush the cache:

    ./bin/magento module:enable Yireo_ServerPush
    ./bin/magento setup:upgrade
    ./bin/magento setup:upgrade

You should be able to see new `Link:` headers by debugging the HTTP headers of the HTML source of your Magento pages.

## Workings

## Status
Stable

## Todo
`\Magento\Framework\App\Response\Http::setHeader()` is supposed to allow for multiple headers with the same name, but it
does not work. For now a simple `header()` is used instead.

This extension does not support HTTP/2 Server Push for scripts that are loaded via RequireJS. RequireJS itself is pushed
though.
