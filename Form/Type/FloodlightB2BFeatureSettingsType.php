<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class FloodlightB2BFeatureSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('strict_gdpr', YesNoButtonGroupType::class, [
            'label' => 'leuchtfeuerfloodlightb2b.form.config.floodlight.strict_gdpr_mode',
            'attr'  => [
                'tooltip' => 'leuchtfeuerfloodlightb2b.form.config.floodlight.strict_gdpr_mode.tooltip',
            ],
            'data' => false,
        ]);
    }
}
