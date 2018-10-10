
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


##Usage


####Media
unite cms does not manage any files directly but provides a file field that stores a reference using any s3 compatible API (Amazon, minio.io etc.). The file field renders an upload input element that allows the content editors to upload files directly to the s3 compatible server, using a presgined upload url. The file filed also reacts on content delete and update events and tries to delete files, that are not used anymore. In order to use the file field, set the required bucket and optional file_type settings:

```
{
  "type": "media",
  "settings": {
    "bucket": {
      "endpoint": "S3 Endpoint",
      "bucket": "S3 Bucket",
      "path": "myfiles"
    },
    "file_types": "txt,pdf,doc"
  }
}
```

####MediaImage
The image type is an extension of the file input type that renders a thumbnail preview next to the upload input type and limits file_type to "png,gif,jpeg,jpg":

```
{
  "type": "mediaimage",
  "settings": {
    "bucket": { ... },
    "thumbnail_url": "your_thumbnailing_service.com/{endpoint}/{id}/{name}"
  }
}
```
The optional thumbnail url allows you to add a link to the file directly or to any thumbnailing service. For a description of the bucket setting, please see the file type documentation.