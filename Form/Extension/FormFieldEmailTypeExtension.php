<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Form\Extension;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\FormBundle\Form\Type\FormFieldEmailType;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

class FormFieldEmailTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private Config $config,
    ) {
    }

    /** @phpstan-ignore-next-line */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $builder->add(
            'blockfreeemail',
            YesNoButtonGroupType::class,
            [
                'label' => 'leuchtfeuerfloodlightb2b.form.field.type.blockfreeemail',
                'attr'  => [
                    'tooltip' => 'leuchtfeuerfloodlightb2b.form.field.type.blockfreeemail.tooltip',
                ],
                'data' => $options['data']['blockfreeemail'] ?? false,
            ]
        );
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormFieldEmailType::class];
    }
}
