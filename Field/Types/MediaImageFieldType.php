<?php

namespace Karls\MediaBundle\Field\Types;

use UniteCMS\CoreBundle\Entity\FieldableField;

class MediaImageFieldType extends MediaFieldType
{
    const TYPE                      = "mediaimage";
    const SETTINGS                  = ['bucket', 'thumbnail_url', 'file_types'];
    const REQUIRED_SETTINGS         = ['bucket'];

    /**
     * {@inheritdoc}
     */
    function getFormOptions(FieldableField $field): array {
        $options = parent::getFormOptions($field);
        $options['attr']['thumbnail-url'] = $field->getSettings()->thumbnail_url ?? '{endpoint}/{id}/{name}';
        $options['attr']['file-types'] = $field->getSettings()->file_types ?? 'png,gif,jpeg,jpg';
        return $options;
    }
}
