<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\Validator\Mapping\ClassMetadata as ValidatorClassMetadata;

class FloodlightLeadDomains
{
    public const STATUS_NO_DATA_FOUND = '404';
    public const STATUS_RETRY_LIMIT_EXCEEDED = 'retry limit exceeded';
    public const STATUS_IP_DOMAIN = 'ip domain';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_UNKNOWN_ERROR = 'unknown error';
    public const STATUS_BLACKLISTED = 'blacklisted';
    public const STATUS_RETRY = 'retry';

    /**
     * @var int
     */
    private $id;

    private ?string $domain = null;

    private ?string $lookupType;

    private ?string $ipHash;

    private int $count;

    private \DateTime $dateAdded;

    private ?\DateTime $dateModified = null;

    private ?Lead $contact;

    private ?\DateTime $dateLastLookup = null;

    private ?string $status = null;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('f4b_lead_ip_lookups')
            ->setCustomRepositoryClass(FloodlightLeadDomainsRepository::class)
            ->addIndex(['contact_id'], 'lead_domains_lead_id')
            ->addIndex(['domain'], 'lead_domains_domain')
            ->addIndex(['ip_hash'], 'lead_domains_ip_hash');

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

        $builder->createField('lookupType', 'string')
            ->columnName('lookup_type')
            ->nullable()
            ->build();

        $builder->addDateAdded();
        $builder->createField('dateModified', 'datetime')
            ->columnName('date_modified')
            ->nullable()
            ->build();

        $builder->createField('dateLastLookup', 'datetime')
            ->columnName('date_last_lookup')
            ->nullable()
            ->build();

        $builder->createField('status', 'string')
            ->columnName('status')
            ->nullable()
            ->build();

        //        $builder->createField('enrichmentDate', 'datetime')
        //            ->columnName('enrichment_date')
        //            ->nullable()
        //            ->build();
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
                    'dateLastLookup',
                    'status',
                ]
            )
            ->addProperties(
                [
                    'domain',
                    'type',
                    'contact',
                    'count',
                    'dateAdded',
                    'dateModified',
                    'dateLastLookup',
                    'status',
                ]
            )
            ->build();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
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

    public function getLookupType(): ?string
    {
        return $this->lookupType;
    }

    public function setLookupType(string $lookupType): void
    {
        $this->lookupType = $lookupType;
    }

    //    public function getEnrichmentDate(): ?\DateTime
    //    {
    //        return $this->enrichmentDate;
    //    }
    //
    //    public function setEnrichmentDate(?\DateTime $enrichmentDate): void
    //    {
    //        $this->enrichmentDate = $enrichmentDate;
    //    }

    public function getDateLastLookup(): ?\DateTime
    {
        return $this->dateLastLookup;
    }

    public function setDateLastLookup(?\DateTime $dateLastLookup): void
    {
        $this->dateLastLookup = $dateLastLookup;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }
}
