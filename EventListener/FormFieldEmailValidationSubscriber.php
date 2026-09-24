<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener;

use Mautic\FormBundle\Event as Events;
use Mautic\FormBundle\Form\Type\FormFieldEmailType;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FreeEmailDomainHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormFieldEmailValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private FreeEmailDomainHelper $freeEmailDomainHelper,
        private Config $config,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD    => ['onFormBuilder', 0],
            FormEvents::ON_FORM_VALIDATE => ['onFormValidate', 0],
        ];
    }

    public function onFormBuilder(Events\FormBuilderEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $event->addValidator(
            'blockfreeemail.validation',
            [
                'eventName' => FormEvents::ON_FORM_VALIDATE,
                'fieldType' => 'email',
                'formType'  => FormFieldEmailType::class,
            ]
        );
    }

    public function onFormValidate(Events\ValidationEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $field = $event->getField();
        if ('email' !== $field->getType()) {
            return;
        }

        if (empty($field->getValidation()['blockfreeemail'])) {
            return;
        }

        $value  = (string) $event->getValue();
        $domain = strtolower((string) substr(strrchr($value, '@'), 1));

        if ('' === $domain || !$this->freeEmailDomainHelper->isFreemailer($domain)) {
            return;
        }

        $event->failedValidation($this->translator->trans('leuchtfeuerfloodlightb2b.form.submission.email.freeprovider.invalid', [], 'validators'));
    }
}
