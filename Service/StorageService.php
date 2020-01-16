<?php
namespace Karls\MediaBundle\Service;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Karls\MediaBundle\Field\Types\MediaFieldType;
use Karls\MediaBundle\Field\Types\MediaImageFieldType;
use Karls\MediaBundle\Model\PreSignedUrl;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UniteCMS\CoreBundle\Entity\Fieldable;
use UniteCMS\CoreBundle\Entity\FieldableField;
use UniteCMS\CoreBundle\Field\FieldTypeManager;
use UniteCMS\CoreBundle\Field\NestableFieldTypeInterface;

class StorageService
{

    /**
     * @var FieldTypeManager $fieldTypeManager
     */
    private $fieldTypeManager;

    /**
     * @var string $thumbnailService
     */
    private $thumbnailService = 'https://d2absclsvmaf7v.cloudfront.net';

    public function __construct(FieldTypeManager $fieldTypeManager)
    {
        $this->fieldTypeManager = $fieldTypeManager;
    }

    /**
     * Resolves a nestable file field by a given path. At the moment, nestable
     * fields are only used for the collection field type.
     *
     * @param Fieldable $fieldable
     * @param $path
     *
     * @return null|FieldableField
     */
    public function resolveFileFieldPath(Fieldable $fieldable, $path)
    {
        /**
         * @var FieldableField $field
         */
        if (!$field = $fieldable->resolveIdentifierPath($path, true)) {
            return null;
        }

        // If we found an image field, we can return it
        if ($field->getType() == MediaImageFieldType::TYPE || $field->getType() == MediaFieldType::TYPE) {
            return $field;
        } else {

            // If this field is nestable, continue resolving.
            $nestedFieldType = $this->fieldTypeManager->getFieldType($field->getType());
            if ($nestedFieldType instanceof NestableFieldTypeInterface) {
                return $this->resolveFileFieldPath($nestedFieldType::getNestableFieldable($field), $path);
            }
        }

        return null;
    }

    /**
     * Pre-Signs an upload action for the given filename and field
     * configuration.
     *
     * @param string $filename
     * @param array $bucket_settings
     * @param string $allowed_file_types
     *
     * @return PreSignedUrl
     * @throws \Exception
     */
    public function createPreSignedUploadUrl(string $filename, array $bucket_settings, string $allowed_file_types = '*')
    {

        // Check if file type is allowed.
        $filenameparts = explode('.', $filename);
        if (count($filenameparts) < 2) {
            throw new \InvalidArgumentException(
                'Filename must include a file type extension.'
            );
        }

        $filenameextension = array_pop($filenameparts);

        $filenameextension_supported = false;

        foreach (explode(
                     ',',
                     str_replace(' ', '', $allowed_file_types)
                 ) as $extension) {
            if ($extension === '*') {
                $filenameextension_supported = true;
            }
            if ($filenameextension === strtolower($extension)) {
                $filenameextension_supported = true;
            }
        }

        if (!$filenameextension_supported) {
            throw new \InvalidArgumentException(
                'File type "' . $filenameextension . '" not supported'
            );
        }

        $uuid = (string)Uuid::uuid1();

        // Return pre-signed url
        $s3Client = new S3Client(
            [
                'version' => 'latest',
                'region' => $bucket_settings['region'] ?? 'us-east-1',
                'endpoint' => $bucket_settings['endpoint'],
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $bucket_settings['key'],
                    'secret' => $bucket_settings['secret'],
                ],
            ]
        );

        // Set the upload file path to an optional path + filename.
        $filePath = $uuid.'/'.$filename;

        if (!empty($bucket_settings['path'])) {
            $path = trim($bucket_settings['path'], "/ \t\n\r\0\x0B");

            if (!empty($path)) {
                $filePath = $path . '/' . $filePath;
            }
        }

        $command = $s3Client->getCommand(
            'PutObject',
            [
                'Bucket' => $bucket_settings['bucket'],
                'Key' => $filePath,
            ]
        );

        return new PreSignedUrl(
            (string)$s3Client->createPresignedRequest($command, '+5 minutes')
                ->getUri(),
            $uuid,
            $filename
        );
    }

    /**
     * Wrapper around createPreSignedUploadUrl to get settings from field
     * settings.
     *
     * @param string $filename
     * @param Fieldable $fieldable
     * @param string $field_path
     *
     * @return PreSignedUrl
     * @throws \Exception
     */
    public function createPreSignedUploadUrlForFieldPath(string $filename, Fieldable $fieldable, string $field_path)
    {
        if (!$field = $this->resolveFileFieldPath($fieldable, $field_path)) {
            throw new \InvalidArgumentException(
                'Field "' . $field_path . '" not found in fieldable.'
            );
        }

        // Check if config is available.
        if (!property_exists(
                $field->getSettings(),
                'bucket'
            ) || empty($field->getSettings()->bucket)) {
            throw new \InvalidArgumentException('Invalid field definition.');
        }

        // Check if config is available.
        $allowed_field_types = '*';
        if (property_exists(
                $field->getSettings(),
                'file_types'
            ) && !empty($field->getSettings()->file_types)) {
            $allowed_field_types = $field->getSettings()->file_types;
        }

        return $this->createPreSignedUploadUrl(
            $filename,
            $field->getSettings()->bucket,
            $allowed_field_types
        );
    }

    /**
     * Delete on object from s3 storage.
     *
     * @param string $uuid
     * @param string $filename
     * @param array $bucket_settings
     *
     * @return \Aws\Result
     */
    public function deleteObject(string $uuid, string $filename, array $bucket_settings)
    {
        $s3Client = new S3Client(
            [
                'version' => 'latest',
                'region' => $bucket_settings['region'] ?? 'us-east-1',
                'endpoint' => $bucket_settings['endpoint'],
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $bucket_settings['key'],
                    'secret' => $bucket_settings['secret'],
                ],
            ]
        );

        // Set the upload file path to an optional path + filename.
        $filePath = $filename;

        if (!empty($bucket_settings['path'])) {
            $path = trim($bucket_settings['path'], "/ \t\n\r\0\x0B");

            if (!empty($path)) {
                $filePath = $path . '/' . $filePath;
            }
        }

        return $s3Client->deleteObject(
            [
                'Bucket' => $bucket_settings['bucket'],
                'Key' => $filePath,
            ]
        );
    }

    /**
     * List all objects from an s3 storage.
     *
     * @param Fieldable $fieldable
     * @param string $field_path
     * @param string $secret
     *
     * @return array
     */
    public function listObjects(Fieldable $fieldable, string $field_path, $secret)
    {
        if (!$field = $this->resolveFileFieldPath($fieldable, $field_path)) {
            throw new \InvalidArgumentException(
                'Field "' . $field_path . '" not found in fieldable.'
            );
        }

        // Check if config is available.
        if (!property_exists(
                $field->getSettings(),
                'bucket'
            ) || empty($field->getSettings()->bucket)) {
            throw new \InvalidArgumentException('Invalid field definition.');
        }

        $bucket_settings = $field->getSettings()->bucket;

        if (!empty($bucket_settings)) {
            $s3Client = new S3Client(
                [
                    'version' => 'latest',
                    'region' => $bucket_settings['region'] ?? 'us-east-1',
                    'endpoint' => $bucket_settings['endpoint'],
                    'use_path_style_endpoint' => true,
                    'credentials' => [
                        'key' => $bucket_settings['key'],
                        'secret' => $bucket_settings['secret'],
                    ],
                ]
            );

            $path = '';
            if (!empty($bucket_settings['path'])) {
                $path = trim($bucket_settings['path'], "/ \t\n\r\0\x0B");

            }

            try {
                $listAllObjectsFromS3 = [
                    'Bucket' => $bucket_settings['bucket'],
                    'Prefix' => $path
//                    'MaxKeys' => 20
                ];
                $objects = $s3Client->getPaginator('ListObjects', $listAllObjectsFromS3);

                $uuid_regex = '/[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}/';
                $listAllObjects = [];
                foreach ($objects as $object) {
                    if($object['Contents']) {
                        foreach ($object->search('Contents[].{Key: Key, size: Size, EType: EType}') as $contents) {
                            $key = $contents['Key'];
                            unset($contents['Key']);
                            $split = explode('/', $key, 3);
                            if (count($split) === 3 && preg_match($uuid_regex, $split[1])) {
                                $filename = $split[2];
                                $contents['url'] = $this->getImageUrls($bucket_settings, $key);
                                $contents['uuid'] = $split[1];
                                $contents['filename'] = $filename;
                                $contents['type'] = explode('.', $filename)[1];
                                $contents['checksum'] = $this->computeHash($filename, $secret);
                                $listAllObjects[] = $contents;
                            }
                        }
                    }
                }

                return $listAllObjects;

            } catch (S3Exception $e) {
                throw new BadRequestHttpException($e->getMessage() . PHP_EOL);
            }
        }
    }

    private function getImageUrls($bucket_settings, $key) {
        $optionsOriginal = base64_encode(json_encode([
            'bucket' => $bucket_settings['bucket'],
            'key' => $key
        ]));
        $optionsThumbnail = base64_encode(json_encode([
            'bucket' => $bucket_settings['bucket'],
            'key' => $key,
            'edits' => [
                'toFormat' => 'jpeg',
                'jpeg' => [
                    'quality' => 77
                ],
                'resize' => [
                    'fit' => 'inside',
                    'width' => 300,
                    'height' => 300
                ]
            ]
        ]));

        return [
            'original' => $this->thumbnailService.'/'.$optionsOriginal,
            'thumbnail' => $this->thumbnailService.'/'.$optionsThumbnail
        ];
    }

    private function computeHash($filename, $secret)
    {
        return urlencode(base64_encode(hash_hmac('sha256', $filename, $secret, true)));
    }
}
