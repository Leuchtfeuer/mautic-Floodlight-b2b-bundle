<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Form\Type;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Validator\ValidFloodlightCredentials;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class FloodlightB2BAuthType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'api_access_key_id',
            TextType::class,
            [
                'label'       => 'leuchtfeuerfloodlightb2b.form.config.floodlight.api_access_key_id',
                'label_attr'  => ['class' => 'control-label'],
                'attr'        => ['class' => 'form-control'],
                'required'    => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ]
        );

        $builder->add(
            'api_secret_key',
            PasswordType::class,
            [
                'label'       => 'leuchtfeuerfloodlightb2b.form.config.floodlight.api_secret_key',
                'label_attr'  => ['class' => 'control-label'],
                'attr'        => ['class' => 'form-control'],
                'required'    => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'integration' => null,
                'constraints' => [
                    new ValidFloodlightCredentials(),
                ],
            ]
        );
    }
}
