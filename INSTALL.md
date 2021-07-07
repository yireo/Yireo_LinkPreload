# Installation
First of all, make sure your Magento 2 hosting environment supports HTTP/2. If not, this extension will not aid anything. 

To install this extension, use the following command:

    composer require yireo/magento2-linkpreload

Afterwards, enable this module, run the setup scripts and flush the cache:

    bin/magento module:enable Yireo_LinkPreload
    bin/magento setup:upgrade
    bin/magento cache:flush

Next, head over to the Store Configuration **Advanced > System > Yireo LinkPreload** to configure things. 

Note that this module is compatible with both Luma and Hyvä.