<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"CLASS", "PROPERTY", "METHOD", "ANNOTATION"})
 */
class ValidFloodlightCredentials extends Constraint
{
    public string $message              = 'leuchtfeuer.floodlight.validator.invalid_credentials';
    public ?string $currentApiSecretKey = null;

    /**
     * @param array<string, string|null> $options
     */
    public function __construct(?array $options = null)
    {
        parent::__construct($options ?? []);
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
