# Cloudflare API

HumHub Component for Cloudflare API v4.0. [Cloudflare](https://www.cloudflare.com/)

## Minimum requirements
- HumHub v1.x.x
- cURL

## Configuration in config.php
```php
'components' => [
//...
    'cloudflare' => [
        'class'         => 'humhub\components\CloudflareApi',
        'apiUrl'        => 'https://api.cloudflare.com/client/v4/',
        'authKey'       => '{api-key-here}',
        'authEmail'     => 'admin@mail.com',
    ],
//...
]
```

After configuring, you can use `\Yii::$app->cloudflare`.

## Usage Examples

1. Purge all Cloudflare cache:

```php
// Get the Cloudflare API component
$cf = \Yii::$app->cloudflare;

// Purge cache for a specific website:
$cf->purgeCache('thebest-country.ua');

// Clear the cache for the first site from the specified list:
$cf->purgeCache();
```

## License
Licensed under AGPL v3 or later.
