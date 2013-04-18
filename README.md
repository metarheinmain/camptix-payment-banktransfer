CampTix Payment Method: Bank Transfer
=====================================

Simple WordPress plugin which adds bank transfer support to CampTix

Installation
------------
* Install and set up CampTix. We need some extra hooks which we requested
  to be integrated into CampTix mainline, but for now you need this patch:
  [mrmcd13/camptix 71aeeb9](https://github.com/mrmcd13/camptix/commit/71aeeb98a80a304e37deab8ea36f74d24d3525cf)

  Up-to-date information on this issue: [Automattic/camptix #42](https://github.com/Automattic/camptix/pull/42)
* Install this plugin:
```
$ cd my/wordpress/installation/wp-content/plugins/
$ git clone git://github.com/mrmcd13/camptix-payment-banktransfer.git
```
* Activate the plugin
* Activate the payment method and provide your bank information in CampTix settings panel.
* Add `[bankdetails]` to your "Single purchase" and "Multiple purchase (receipt)" e-mail templates. We put it right behind `[receipt]` with a newline in between.
