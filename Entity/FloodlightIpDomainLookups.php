<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity;

use Doctrine\ORM\Mapping\ClassMetadata;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Symfony\Component\Validator\Mapping\ClassMetadata as ValidatorClassMetadata;

class FloodlightIpDomainLookups
{
    private ?int $id = null;
    private string $ip;
    private string $domain;

    private string $type;

    private \DateTime $dateAdded;

    public static function loadMetadata(ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('fl_ip_domain_lookups')
            ->setCustomRepositoryClass(FloodlightIpDomainLookupsRepository::class)
            ->addIndex(['ip_address'], 'ip_domain_lookups_ip')
            ->addIndex(['domain'], 'ip_domain_lookups_domain');

        $builder->addId();

        $builder->createField('ip', 'string')->columnName('ip_address')->build();
        $builder->createField('domain', 'string')->columnName('domain')->build();
        $builder->createField('type', 'string')->columnName('type')->build();
        $builder->addDateAdded();
    }

    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('fl_lead_domains')
            ->addListProperties(
                [
                    'id',
                    'ip_address',
                    'domain',
                    'type',
                    'dateAdded',
                ]
            )
            ->addProperties(
                [
                    'id',
                    'ip_address',
                    'domain',
                    'type',
                    'dateAdded',
                ]
            )
            ->build();
    }

    public static function loadValidatorMetadata(ValidatorClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint(
            'domain',
            new \Symfony\Component\Validator\Constraints\NotBlank(
                [
                    'message' => 'mautic.core.name.required',
                ]
            )
        );

        $metadata->addPropertyConstraint(
            'ip',
            new \Symfony\Component\Validator\Constraints\NotBlank(
                [
                    'message' => 'mautic.core.name.required',
                ]
            )
        );

        $metadata->addPropertyConstraint(
            'type',
            new \Symfony\Component\Validator\Constraints\NotBlank(
                [
                    'message' => 'mautic.core.name.required',
                ]
            )
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): void
    {
        $this->ip = $ip;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getDateAdded(): \DateTime
    {
        return $this->dateAdded;
    }

    public function setDateAdded(\DateTime $dateAdded): void
    {
        $this->dateAdded = $dateAdded;
    }
}
