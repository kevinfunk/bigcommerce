#BigCommerce

Integrates Drupal with [BigCommerce](https://www.bigcommerce.com/).

The module will allow you to leverage the obvious strengths of each platform:
Drupal as the front-end CMS for customized UX, design, and content management
(including display of intended BC content), and BigCommerce as the headless
commerce engine.

Please report bugs in the [issue queue](https://www.drupal.org/project/issues/bigcommerce).

[Documentation](https://www.drupal.org/docs/8/modules/bigcommerce)

[Issue Tracker](https://www.drupal.org/project/issues/bigcommerce)

##Testing
Enable the bigcommerce_test module
Run the follow code using `drush php --uri http://REPLACE_ME`
```php
\Drupal::service('commerce_price.currency_importer')->import('USD');
$store = \Drupal\commerce_store\Entity\Store::create([
  'type' => 'online',
  'uid' => 1,
  'name' => 'Bigcommerce test',
  'mail' => 'test@example.com',
  'default_currency' => 'USD',
  'timezone' => 'Australia/Sydney',
  'address' => [
    'country_code' => 'US',
    'address_line1' => '1 House Street',
    'locality' => 'A city',
    'administrative_area' => 'WI',
    'postal_code' => '53597',
  ],
  'billing_countries' => ['US'],
  'is_default' => TRUE,
]);
$store->save();
$config = \Drupal::configFactory()->getEditable('bigcommerce.settings');
$config->set('api', [
  'path' => \Drupal\Core\Url::fromUri('base://bigcommerce_stub/cart')->setAbsolute()->toString(),
  'access_token' => 'an access token',
  'client_id' => 'a client ID',
  'client_secret' => 'a client secret',
  'timeout' => 15,
]);
$config->save();
```
