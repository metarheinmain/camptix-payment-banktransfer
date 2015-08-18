CampTix Payment Method: Bank Transfer
=====================================

Simple WordPress plugin which adds bank transfer support to CampTix

Please not that this plugin is *not actively maintained*. It has been used in production and it worked for us, but we do not update it as we do not use camptix any more in favour of [pretix](http://pretix.eu/).

Installation
------------
* Install and set up CampTix.
* Install this plugin:
```
$ cd my/wordpress/installation/wp-content/plugins/
$ git clone git://github.com/mrmcd13/camptix-payment-banktransfer.git
```
* Activate the plugin
* Activate the payment method and provide your bank information in CampTix settings panel.
* Add `[bankdetails]` to your "Single purchase" and "Multiple purchase (receipt)" e-mail templates. We put it right behind `[receipt]` with a newline in between.
