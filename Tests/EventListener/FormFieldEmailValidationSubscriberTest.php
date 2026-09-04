<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\EventListener;

use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Event\ValidationEvent;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener\FormFieldEmailValidationSubscriber;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FreeEmailDomainHelper;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FormFieldEmailValidationSubscriberTest extends TestCase
{
    private MockObject&TranslatorInterface $translator;

    private MockObject&Config $config;

    private FormFieldEmailValidationSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translator  = $this->createMock(TranslatorInterface::class);
        $this->config      = $this->createMock(Config::class);
        $this->config->method('isPublished')->willReturn(true);

        $this->subscriber = new FormFieldEmailValidationSubscriber(
            $this->translator,
            new FreeEmailDomainHelper(),
            $this->config,
        );
    }

    public function testFreemailerIsRejectedWhenBlockingEnabled(): void
    {
        $field = new Field();
        $field->setType('email');
        $field->setValidation(['blockfreeemail' => 1]);

        $this->translator
            ->method('trans')
            ->with('leuchtfeuerfloodlightb2b.form.submission.email.freeprovider.invalid', [], 'validators')
            ->willReturn('Please provide a business email address.');

        $event = new ValidationEvent($field, 'someone@gmail.com');
        $this->subscriber->onFormValidate($event);

        self::assertFalse($event->isValid());
        self::assertSame('Please provide a business email address.', $event->getInvalidReason());
    }

    public function testBusinessEmailPasses(): void
    {
        $field = new Field();
        $field->setType('email');
        $field->setValidation(['blockfreeemail' => 1]);

        $event = new ValidationEvent($field, 'someone@leuchtfeuer.com');
        $this->subscriber->onFormValidate($event);

        self::assertTrue($event->isValid());
    }

    public function testFreemailerIsAllowedWhenBlockingDisabled(): void
    {
        $field = new Field();
        $field->setType('email');
        $field->setValidation(['blockfreeemail' => 0]);

        $event = new ValidationEvent($field, 'someone@gmail.com');
        $this->subscriber->onFormValidate($event);

        self::assertTrue($event->isValid());
    }

    public function testNonEmailFieldIsIgnored(): void
    {
        $field = new Field();
        $field->setType('text');
        $field->setValidation(['blockfreeemail' => 1]);

        $event = new ValidationEvent($field, 'someone@gmail.com');
        $this->subscriber->onFormValidate($event);

        self::assertTrue($event->isValid());
    }

    public function testFreemailerIsAllowedWhenPluginNotPublished(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isPublished')->willReturn(false);

        $subscriber = new FormFieldEmailValidationSubscriber(
            $this->translator,
            new FreeEmailDomainHelper(),
            $config,
        );

        $field = new Field();
        $field->setType('email');
        $field->setValidation(['blockfreeemail' => 1]);

        $event = new ValidationEvent($field, 'someone@gmail.com');
        $subscriber->onFormValidate($event);

        self::assertTrue($event->isValid());
    }
}
