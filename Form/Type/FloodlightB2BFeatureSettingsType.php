<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class FloodlightB2BFeatureSettingsType extends AbstractType
{
    private readonly bool $ignoreSingleClickVisits;
    private readonly bool $ignoreTelcos;

    public function __construct(private readonly Config $config)
    {
        $this->ignoreSingleClickVisits = $this->config->getIgnoreSingleClickSettings();
        $this->ignoreTelcos            = $this->config->getIgnoreTelcosSettings();
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('ignore_single_click_visits', YesNoButtonGroupType::class, [
            'label' => 'leuchtfeuerfloodlightb2b.form.config.floodlight.ignore_single_click_visits',
            'attr'  => [
                'tooltip' => 'leuchtfeuerfloodlightb2b.form.config.floodlight.ignore_single_click_visits.tooltip',
            ],
            'data' => $this->ignoreSingleClickVisits,
        ]);

        $builder->add('ignore_telcos', YesNoButtonGroupType::class, [
            'label' => 'leuchtfeuerfloodlightb2b.form.config.floodlight.ignore_telcos',
            'data'  => $this->ignoreTelcos,
        ]);
    }
}
