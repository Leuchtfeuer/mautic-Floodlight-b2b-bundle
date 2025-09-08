<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity;

use Doctrine\ORM\Mapping\ClassMetadata;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Company;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata as ValidatorClassMetadata;

class FloodlightCompanyDomains
{
    private Company $company;

    private bool $isPrimaryDomain;

    private bool $isTelco;

    private ?string $domain = null;
    private \DateTime $dateAdded;
    private \DateTime $dateModified;

    //    private ?int $codeStatusRequest = null;

    public static function loadMetadata(ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('f4b_company_domains')
            ->setCustomRepositoryClass(FloodlightCompanyDomainsRepository::class)
            ->addIndex(['company_id'], 'company_domains_company_id')
            ->addIndex(['domain'], 'company_domains_domain');

        $builder->createManyToOne('company', Company::class)
            ->makePrimaryKey()
            ->addJoinColumn('company_id', 'id', false, false, 'CASCADE')
            ->build();

        $builder->createField('domain', 'string')
            ->columnName('domain')
            ->nullable()
            ->unique()
            ->makePrimaryKey()
            ->build();
        //        $builder->createField('codeStatusRequest', 'integer')
        //            ->columnName('code_status_request')
        //            ->nullable()
        //            ->build();
        $builder->createField('isPrimaryDomain', 'boolean')->columnName('is_primary_domain')->build();
        $builder->createField('isTelco', 'boolean')->columnName('is_telco')->nullable()->build();
        $builder->addDateAdded();
        $builder->createField('dateModified', 'datetime')->columnName('date_modified')->build();
    }

    public static function loadValidatorMetadata(ValidatorClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint(
            'domain',
            new Assert\NotBlank(
                [
                    'message' => 'mautic.core.name.required',
                ]
            )
        );
    }

    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('company_domains')
            ->addListProperties(
                [
                    'domain',
                    'is_primary_domain',
                    'dateAdded',
                    'dateModified',
                ]
            )
            ->addProperties(
                [
                    'domain',
                    'is_primary_domain',
                    'dateAdded',
                    'dateModified',
                ]
            )
            ->build();
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): void
    {
        $this->domain = $domain;
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

    public function isPrimaryDomain(): bool
    {
        return $this->isPrimaryDomain;
    }

    public function setIsPrimaryDomain(bool $isPrimaryDomain): void
    {
        $this->isPrimaryDomain = $isPrimaryDomain;
    }

    public function getIsPrimaryDomain(): bool
    {
        return $this->isPrimaryDomain;
    }

    public function setIsTelco(bool $isTelco): void
    {
        $this->isTelco = $isTelco;
    }

    public function getIsTelco(): bool
    {
        return $this->isTelco;
    }

    //    public function getCodeStatusRequest(): ?int
    //    {
    //        return $this->codeStatusRequest;
    //    }
    //
    //    public function setCodeStatusRequest(?int $codeStatusRequest): void
    //    {
    //        $this->codeStatusRequest = $codeStatusRequest;
    //    }
}
