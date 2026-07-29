<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Validator;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidFloodlightCredentialsValidator extends ConstraintValidator
{
    public function __construct(private FloodlightService $floodlightService)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
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

        try {
            $isValid = $this->floodlightService->validateApiCredentials($accessKeyId, $secretKey);

            if (!$isValid) {
                $this->context->buildViolation($constraint->message)
                    ->addViolation();
            }
        } catch (\Exception) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
