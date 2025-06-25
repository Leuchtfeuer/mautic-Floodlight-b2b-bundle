<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"CLASS", "PROPERTY", "METHOD", "ANNOTATION"})
 */
class ValidFloodlightCredentials extends Constraint
{
    public $message = 'leuchtfeuer.floodlight.validator.invalid_credentials';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}