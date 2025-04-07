<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FloodlightB2BAuthType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder->add(
            'api_key',
            TextType::class,
            [
                'label'       => 'leuchtfeuerfloodlightb2b.form.config.floodlight.api_key',
                'label_attr'  => ['class' => 'control-label'],
                'attr'        => ['class' => 'form-control'],
                'required'    => true,
                'constraints' => [
                ],
            ]
        );

        $builder->add(
            'verify_floodlight_api_key',
            ButtonType::class,
            [
                'label' => 'mautic.plugin.floodlight.verify_api',
                'attr'  => [
                    'class'   => 'btn btn-primary',
                    'style'   => 'margin-bottom: 10px',
                    'onclick' => 'Mautic.verifyFloodlightApiKey(this)',
                ],
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'integration' => null,
            ]
        );
    }
}
