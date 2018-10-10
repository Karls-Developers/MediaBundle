
# Karls Media Bundle for Unite-Cms

Provides a new Field Type for handling S3 Stored files select from Storage and save it right.

##Installation

via composer
```
    composer require karls/media-bundle
```

in config/bundles.php

```
Karls\MediaBundle\KarlsMediaBundle::class => ['all' => true],
```

in config/packages/assets.yaml

```yaml
framework:
    assets:
        packages:
            KarlsMediaBundle:
                json_manifest_path: "%kernel.project_dir%/public/bundles/karlsmedia/manifest.json"
                base_path: '/bundles/karlsmedia'

```

in config/routes/unite-cms.php

```php
$routes->addCollection($loader->import("@KarlsMediaBundle/Resources/config/routing.$approach.yml"));
```