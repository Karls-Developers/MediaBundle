<?php
namespace Karls\MediaBundle\Form;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UniteCMS\StorageBundle\Form\StorageFileType as BaseStorageFileType;

class StorageFileType extends BaseStorageFileType
{

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'karls_media_file';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return BaseStorageFileType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars['assets'] = [
            [ 'css' => 'main.css', 'package' => 'KarlsMediaBundle' ],
            [ 'js' => 'main.js', 'package' => 'KarlsMediaBundle' ],
        ];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults(
            [
                'tag' => 'karls-media-file-field',
                'file-types' => '*',
            ]
        );
        $resolver->setRequired(['file-types']);
    }

}
