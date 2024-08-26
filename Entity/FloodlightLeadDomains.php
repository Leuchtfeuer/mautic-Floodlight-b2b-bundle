<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\Validator\Mapping\ClassMetadata as ValidatorClassMetadata;

class FloodlightLeadDomains
{
    private ?int $id = null;
    private ?Lead $lead;
    private string $domain;
    private int $count;
    private \DateTime $dateAdded;
    private \DateTime $dateModified;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('fl_lead_domains')
            ->setCustomRepositoryClass(FloodlightLeadDomainsRepository::class)
            ->addIndex(['lead_id'], 'lead_domains_lead_id')
            ->addIndex(['domain'], 'lead_domains_domain');

        $builder->addBigIntIdField();
        $builder->addLead(true, 'CASCADE');
        $builder->createField('domain', 'string')
            ->columnName('domain')
            ->build();
        $builder->createField('count', 'integer')
            ->columnName('count')
            ->build();
        $builder->addDateAdded();
        $builder->createField('dateModified', 'datetime')
            ->columnName('date_modified')
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
    }

    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('fl_lead_domains')
            ->addListProperties(
                [
                    'id',
                    'lead',
                    'domain',
                    'count',
                    'dateAdded',
                    'dateModified',
                ]
            )
            ->addProperties(
                [
                    'id',
                    'domain',
                    'lead',
                    'count',
                    'dateAdded',
                    'dateModified',
                ]
            )
            ->build();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setLead(Lead $lead): void
    {
        $this->lead = $lead;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): void
    {
        $this->count = $count;
    }

    public function addCount(): void
    {
        ++$this->count;
    }

    public function getDateAdded(): \DateTime
    {
        return $this->dateAdded;
    }

    public function setDateAdded(\DateTime $dateAdded): void
    {
        $this->dateAdded = $dateAdded;
    }

    public function getDateModified(): \DateTime
    {
        return $this->dateModified;
    }

    public function setDateModified(\DateTime $dateModified): void
    {
        $this->dateModified = $dateModified;
    }
}
