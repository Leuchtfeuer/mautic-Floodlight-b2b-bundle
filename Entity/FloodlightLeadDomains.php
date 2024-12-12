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

    private ?string $domain = null;

    private ?string $type;

    private ?string $ipHash;

    private int $count;

    private \DateTime $dateAdded;

    private ?\DateTime $dateModified = null;

    private ?Lead $contact;

    private ?\DateTime $enrichmentDate = null;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('f4b_lead_ip_lookups')
            ->setCustomRepositoryClass(FloodlightLeadDomainsRepository::class)
            ->addIndex(['contact_id'], 'lead_domains_lead_id')
            ->addIndex(['domain'], 'lead_domains_domain');

        $builder->addBigIntIdField();
        $builder->addContact(true, 'CASCADE');
        $builder->createField('domain', 'string')
            ->columnName('domain')
            ->nullable()
            ->build();

        $builder->createField('count', 'integer')
            ->columnName('count')
            ->build();

        $builder->createField('ipHash', 'string')
            ->columnName('ip_hash')
            ->build();

        $builder->createField('type', 'string')
            ->columnName('type')
            ->nullable()
            ->build();

        $builder->addDateAdded();
        $builder->createField('dateModified', 'datetime')
            ->columnName('date_modified')
            ->nullable()
            ->build();

        $builder->createField('enrichmentDate', 'datetime')
            ->columnName('enrichment_date')
            ->nullable()
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
            'type',
            new \Symfony\Component\Validator\Constraints\NotBlank(
                [
                    'message' => 'mautic.core.name.required',
                ]
            )
        );
    }

    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('f4b_lead_domains')
            ->addListProperties(
                [
                    'id',
                    'contact',
                    'domain',
                    'type',
                    'count',
                    'dateAdded',
                    'dateModified',
                    'enrichmentDate',
                ]
            )
            ->addProperties(
                [
                    'id',
                    'domain',
                    'type',
                    'contact',
                    'count',
                    'dateAdded',
                    'dateModified',
                    'enrichmentDate',
                ]
            )
            ->build();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContact(): ?Lead
    {
        return $this->contact;
    }

    public function setContact(Lead $contact): void
    {
        $this->contact = $contact;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): void
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

    public function getDateModified(): ?\DateTime
    {
        return $this->dateModified;
    }

    public function setDateModified(?\DateTime $dateModified): void
    {
        $this->dateModified = $dateModified;
    }

    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    public function setIpHash(string $ipHash): void
    {
        $this->ipHash = $ipHash;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getEnrichmentDate(): ?\DateTime
    {
        return $this->enrichmentDate;
    }

    public function setEnrichmentDate(?\DateTime $enrichmentDate): void
    {
        $this->enrichmentDate = $enrichmentDate;
    }
}
