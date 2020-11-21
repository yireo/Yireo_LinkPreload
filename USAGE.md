# Usage
Once the extension is installed, you will still need to enable it via **Store > Configuration > System > Yireo LinkPreload**.

Once enabled, you should be able to see new `Link:` headers by debugging the HTTP headers of the HTML source of your Magento pages. This allows your browser to request for some initial CSS and JavaScript, once the HTML document has
been received.

# Optional XML layout configuration
Optionally, you can add additional links via the XML layout, which is mostly useful for JavaScript loaded via RequireJS, or webfonts loaded via css:

```xml
<referenceBlock name="link-preload">
    <arguments>
        <argument name="scripts" xsi:type="array">
            <item name="my-script-name" xsi:type="string">my-script.js</item>
        </argument>
        <argument name="fonts" xsi:type="array">
            <item name="my-font-name" xsi:type="string">my-font.woff2</item>
        </argument>
        <argument name="styles" xsi:type="array">
            <item name="my-style-name" xsi:type="string">my-style.css</item>
        </argument>
        <argument name="images" xsi:type="array">
            <item name="my-image-name" xsi:type="string">my-image.png</item>
        </argument>
    </arguments>
</referenceBlock>
```

Please note that RequireJS bundling has a similar effect and should be the preferred way to go.


# Backgrounds
With HTTP/2 a lot of cool things have been added to the web. However, for things to work really optimal a HTML document could mark additional resources (like CSS or JavaScript) with `Link` headers in the HTTP response. This extension simply adds these `Link` headers to the HTTP response, allowing your Magento 2 site to be loaded faster under HTTP/2.

# About the browser cache
Please note that this extension does not implement a `PUSH` initiated from the server, which would bypass the browser cache. Instead, it implements a method that respects the browser cache, which resembles more of a *pull* than a *push*.

# Use a cookie?
This module also ships with a setting **Use Cookie**. Keep it disabled unless you know what you are doing. Theoretically, there could be a reverse-proxy between the browser and Magento that doesn't forward `Link` header but instead uses it to initiate a real `PUSH`, so that it bypasses the browser cache. This depends entirely on the configuration of this reverse-proxy. But in this specific case, the cookie feature needs to be enabled.
