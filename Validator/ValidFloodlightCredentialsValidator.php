<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Validator;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidFloodlightCredentialsValidator extends ConstraintValidator
{
    private $floodlightService;

    public function __construct(FloodlightService $floodlightService)
    {
        $this->floodlightService = $floodlightService;
    }

    public function validate($value, Constraint $constraint)
    {
        // $value ist das ganze Form-Data-Array - direkt die apiKeys Daten
        if (!is_array($value)) {
            return;
        }
        
        // Die Felder sind direkt im Array, nicht in einem Sub-Array
        $accessKeyId = isset($value['api_access_key_id']) ? trim($value['api_access_key_id']) : '';
        $secretKey = isset($value['api_secret_key']) ? trim($value['api_secret_key']) : '';

        // Wenn beide Felder leer sind, keine Validierung durchführen
        if (empty($accessKeyId) && empty($secretKey)) {
            return;
        }

        // Wenn nur eines der Felder leer ist, Fehler
        if (empty($accessKeyId) || empty($secretKey)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Validiere die Credentials gegen die API
        try {
            $isValid = $this->floodlightService->validateApiCredentials($accessKeyId, $secretKey);
            
            if (!$isValid) {
                $this->context->buildViolation($constraint->message)
                    ->addViolation();
            }
        } catch (\Exception $e) {
            // Bei einem Fehler (Netzwerk, etc.) als ungültig betrachten
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}