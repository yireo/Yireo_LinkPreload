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
    ./bin/magento cache:flush

You should be able to see new `Link:` headers by debugging the HTTP headers of the HTML source of your Magento pages.

## NGINX
HTTP/2 Server Push doesn't work with Nginx and they have no plans in supporting this. Note that some posts on the web have mentioned that Nginx Plus supports HTTP/2 Server Push. This is incorrect and the mistake comes from Nginx Plus supporting HTTP/2 at first, while Nginx Community did not. Currently, HTTP/2 is supported in all Nginx versions. However, specifically, Server Push does not work with Nginx (either community version or Nginx Plus). More information about this topic:
- https://stackoverflow.com/questions/33537199/does-the-nginx-http-2-module-support-server-push
- https://serverfault.com/questions/765258/use-http-2-0-between-nginx-reverse-proxy-and-backend-webserver

## Status
Experimental, needs performance and real-world testing

## Todo
This extension does not support HTTP/2 Server Push for scripts that are loaded via RequireJS. RequireJS itself is pushed
though.
