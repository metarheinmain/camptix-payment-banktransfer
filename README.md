CampTix Payment Method: Bank Transfer
=====================================

Simple WordPress plugin which adds bank transfer support to CampTix

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
