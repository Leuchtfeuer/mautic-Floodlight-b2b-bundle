<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Validator;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\CredentialsValidationResult;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidFloodlightCredentialsValidator extends ConstraintValidator
{
    public function __construct(private FloodlightService $floodlightService)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        assert($constraint instanceof ValidFloodlightCredentials);

        if (!is_array($value)) {
            return;
        }

        $accessKeyId = isset($value['api_access_key_id']) ? trim($value['api_access_key_id']) : '';
        $secretKey   = isset($value['api_secret_key']) ? trim($value['api_secret_key']) : '';

        if (empty($accessKeyId)) {
            return;
        }

        $currentApiSecretKey = $constraint->currentApiSecretKey;
        if (empty($secretKey) && !empty($currentApiSecretKey)) {
            $secretKey = $currentApiSecretKey;
        }

        $message = match ($this->floodlightService->validateApiCredentials($accessKeyId, $secretKey)) {
            CredentialsValidationResult::Valid        => null,
            CredentialsValidationResult::Invalid      => $constraint->message,
            CredentialsValidationResult::Unverifiable => $constraint->unverifiableMessage,
        };

        if (null !== $message) {
            $this->context->buildViolation($message)
                ->addViolation();
        }
    }
}
