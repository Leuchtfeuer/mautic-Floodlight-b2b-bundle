<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Form\Type;

use Mautic\FormBundle\Event\FormEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Validator\ValidFloodlightCredentials;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class FloodlightB2BAuthType extends AbstractType
{
    private ?string $currentApiSecretKey = null;

    public function __construct(private Config $config)
    {
        // Als allererstes das Passwort aus der Datenbank laden
        $this->loadCurrentApiSecretKey();
    }

    private function loadCurrentApiSecretKey(): void
    {
        try {
            $apiKeys = $this->config->getApiKeys();
            $currentsecretkey = $this->config->getCurrentSecretKey();
            $this->currentApiSecretKey = $apiKeys['api_secret_key'] ?? null;
        } catch (\Exception) {
            $this->currentApiSecretKey = null;
        }
    }

    public function getCurrentApiSecretKey(): ?string
    {
        return $this->currentApiSecretKey;
    }

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

        $hasExistingPassword = !empty($this->currentApiSecretKey);
        $isRequired = !$hasExistingPassword;
        $constraints = [];
        if ($isRequired) {
            $constraints[] = new NotBlank();
        } else {
            $constraints[] = new Callback(
                function ($validateMe, ExecutionContextInterface $context) use ($hasExistingPassword): void {
                    if (empty($validateMe) && !$hasExistingPassword) {
                        $context->buildViolation('mautic.core.value.required')->addViolation();
                    }
                }
            );
        }

        $builder->add(
            'api_secret_key',
            PasswordType::class,
            [
                'label'       => 'leuchtfeuerfloodlightb2b.form.config.floodlight.api_secret_key',
                'label_attr'  => ['class' => 'control-label'],
                'attr'        => [
                    'class' => 'form-control',
                    'placeholder' => $hasExistingPassword ? 
                        'Aktuelles Passwort beibehalten (leer lassen)' : 
                        'API Secret Key eingeben'
                ],
                'required'    => $isRequired,
                'constraints' => $constraints,
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'integration' => null,
                'constraints' => [
                    new ValidFloodlightCredentials([
                        'currentApiSecretKey' => $this->currentApiSecretKey
                    ]),
                ],
            ]
        );
    }
}