<?php

namespace Karls\MediaBundle\Field\Types;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UniteCMS\CoreBundle\Entity\Content;
use UniteCMS\CoreBundle\Entity\ContentType;
use UniteCMS\CoreBundle\Entity\FieldableContent;
use UniteCMS\CoreBundle\Entity\FieldableField;
use UniteCMS\CoreBundle\Entity\SettingType;
use UniteCMS\CoreBundle\Field\FieldableFieldSettings;
use UniteCMS\CoreBundle\Field\FieldType;
use UniteCMS\CoreBundle\ParamConverter\IdentifierNormalizer;
use UniteCMS\CoreBundle\SchemaType\SchemaTypeManager;
use Karls\MediaBundle\Model\PreSignedUrl;
use UniteCMS\StorageBundle\Field\Types\FileFieldType;
use UniteCMS\StorageBundle\Field\Types\FieldableContentType;
use Karls\MediaBundle\Form\StorageFileType;
use Karls\MediaBundle\Service\StorageService;

class MediaFieldType extends FieldType
{
    const TYPE                      = "media";
    const FORM_TYPE                 = StorageFileType::class;
    const SETTINGS                  = ['file_types', 'bucket'];
    const REQUIRED_SETTINGS         = ['bucket'];

    private $router;
    private $secret;
    private $storageService;
    private $csrfTokenManager;

    public function __construct(Router $router, string $secret, StorageService $storageService, CsrfTokenManager $csrfTokenManager)
    {
        $this->router = $router;
        $this->secret = $secret;
        $this->storageService = $storageService;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * Returns the bucket endpoint, including an optional sub-path.
     *
     * @param FieldableFieldSettings $settings
     * @return string
     */
    protected function generateEndpoint(FieldableFieldSettings $settings) : string {
        $endpoint = $settings->bucket['endpoint'].'/'.$settings->bucket['bucket'];

        if (!empty($settings->bucket['path'])) {
            $path = trim($settings->bucket['path'], "/ \t\n\r\0\x0B");

            if (!empty($path)) {
                $endpoint = $endpoint.'/'.$path;
            }
        }

        return $endpoint;
    }

    /**
     * {@inheritdoc}
     */
    function getFormOptions(FieldableField $field): array
    {
        $url = null;
        $selectFilesUrl = null;

        // To generate the sing url we need to find out the base fieldable.
        $fieldable = $field->getEntity()->getRootEntity();

        if($fieldable instanceof ContentType) {
            $url = $this->router->generate('karls_media_sign_uploadcontenttype', [
                'organization' => IdentifierNormalizer::denormalize($fieldable->getDomain()->getOrganization()->getIdentifier()),
                'domain' => IdentifierNormalizer::denormalize($fieldable->getDomain()->getIdentifier()),
                'content_type' => IdentifierNormalizer::denormalize($fieldable->getIdentifier()),
            ], Router::ABSOLUTE_URL);
            $selectFilesUrl = $this->router->generate('karls_media_select_selectfilescontenttype', [
                'organization' => IdentifierNormalizer::denormalize($fieldable->getDomain()->getOrganization()->getIdentifier()),
                'domain' => IdentifierNormalizer::denormalize($fieldable->getDomain()->getIdentifier()),
                'content_type' => IdentifierNormalizer::denormalize($fieldable->getIdentifier()),
            ], Router::ABSOLUTE_URL);

        } else if($fieldable instanceof SettingType) {
            $url = $this->router->generate('karls_media_sign_uploadsettingtype', [
                'organization' => IdentifierNormalizer::denormalize($fieldable->getDomain()->getOrganization()->getIdentifier()),
                'domain' => IdentifierNormalizer::denormalize($fieldable->getDomain()->getIdentifier()),
                'setting_type' => IdentifierNormalizer::denormalize($fieldable->getIdentifier()),
            ], Router::ABSOLUTE_URL);
            $selectFilesUrl = $this->router->generate('karls_media_select_selectfilessettingtype', [
                'organization' => IdentifierNormalizer::denormalize($fieldable->getDomain()->getOrganization()->getIdentifier()),
                'domain' => IdentifierNormalizer::denormalize($fieldable->getDomain()->getIdentifier()),
                'setting_type' => IdentifierNormalizer::denormalize($fieldable->getIdentifier()),
            ], Router::ABSOLUTE_URL);
        }

        return array_merge(parent::getFormOptions($field), [
            'attr' => [
                'file-types' => $field->getSettings()->file_types,
                'field-path' => $field->getIdentifierPath('/', false),
                'endpoint' => $this->generateEndpoint($field->getSettings()),
                'select-files-url' => $selectFilesUrl,
                'upload-sign-url' => $url,
                'upload-sign-csrf-token' => $this->csrfTokenManager->getToken('pre_sign_form'),
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    function getGraphQLType(FieldableField $field, SchemaTypeManager $schemaTypeManager, $nestingLevel = 0) {
        return $schemaTypeManager->getSchemaType('StorageFile');
    }

    /**
     * {@inheritdoc}
     */
    function getGraphQLInputType(FieldableField $field, SchemaTypeManager $schemaTypeManager, $nestingLevel = 0) {
        return $schemaTypeManager->getSchemaType('StorageFileInput');
    }

    /**
     * {@inheritdoc}
     */
    function resolveGraphQLData(FieldableField $field, $value, FieldableContent $content, array $args, $context, ResolveInfo $info)
    {
        if (!isset($value['id']) && !isset($value['name'])) {
            return;
        }

        // Create full URL to file.
        $value['url'] = $this->generateEndpoint($field->getSettings()) . '/' . $value['id'] . '/' . $value['name'];
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    function validateData(FieldableField $field, $data, ExecutionContextInterface $context)
    {
        // When deleting content, we don't need to validate data.
        if(strtoupper($context->getGroup()) === 'DELETE') {
            return;
        }

        if(empty($data)) {
            return;
        }

        if(empty($data['size']) || empty($data['id']) || empty($data['name']) || empty($data['checksum'])) {
            $context->buildViolation('storage.missing_file_definition')->addViolation();
        }

        if(empty($violations)) {
            $preSignedUrl = new PreSignedUrl('', $data['id'], $data['name'], $data['checksum']);

            if (!$preSignedUrl->check($this->secret)) {
                $context->buildViolation('storage.invalid_checksum')->addViolation();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    function validateSettings(FieldableFieldSettings $settings, ExecutionContextInterface $context)
    {
        // Validate allowed and required settings.
        parent::validateSettings($settings, $context);

        // Validate allowed bucket configuration.
        if($context->getViolations()->count() == 0) {
            foreach($settings->bucket as $field => $value) {
                if(!in_array($field, ['endpoint', 'key', 'secret', 'bucket', 'path', 'region'])) {
                    $context->buildViolation('additional_data')->atPath('bucket.' . $field)->addViolation();
                }
            }
        }

        // Validate required bucket configuration.
        if($context->getViolations()->count() == 0) {
            foreach(['endpoint', 'key', 'secret', 'bucket'] as $required_field) {
                if(!isset($settings->bucket[$required_field])) {
                    $context->buildViolation('required')->atPath('bucket.' . $required_field)->addViolation();
                }
            }
        }

        if($context->getViolations()->count() == 0) {
            if(!preg_match("/^(http|https):\/\//", $settings->bucket['endpoint'])) {
                $context->buildViolation('storage.absolute_url')->atPath('bucket.endpoint')->addViolation();
            }
        }
    }
}
