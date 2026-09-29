<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\LeadField;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\RebuildsCompanyTableAfterCustomFieldCleanup;

final class PluginInstallSubscriberTest extends MauticMysqlTestCase
{
    use RebuildsCompanyTableAfterCustomFieldCleanup;

    public function testOnPluginInstall(): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $this->useCleanupRollback = false;
        $this->client->request('GET', '/s/contacts/fields');
        $content = $this->client->getResponse()->getContent();
        $this->client->request('GET', '/s/contacts/fields/2');
        $content .= $this->client->getResponse()->getContent();
        $fields = [
            'f4b_companytimezone',
            'f4b_companyticker',
            'f4b_companytype',
            'companylogo_filename',
            'f4b_companytwitter_followers',
            'f4b_companylinkedin_handle',
            'f4b_companyfacebook_handle',
            'f4b_companytechnologies',
            'f4b_companyfunding_raised',
            'f4b_companyannual_revenue_range',
            'f4b_companymarketcap',
            'f4b_companyemployees_range',
            'f4b_companyrank_tranco',
            'f4b_companystate_code',
            'f4b_companydescription',
            'f4b_companydescription_long',
            'f4b_companyindustry_icb_sector',
            'f4b_companyindustry_icb_subIndustry',
            'f4b_companyindustry_icb_industry',
            'f4b_companyindustry_icb_industrygroup',
            'f4b_companyindustry_naicscode',
            'f4b_companylabels',
            'f4b_companyindustry_list',
            'f4b_companyindustry_linkedin',
            'f4b_companyname_alternatives',
            'f4b_companyname_legal',
            'f4b_companycrunchbase_handle',
            'f4b_companyfloodlight_id',
            'f4b_iplookup_whois_json',
            'f4b_enrichment_date',
        ];
        foreach ($fields as $field) {
            $this->assertStringContainsString($field, $content);
        }
    }

    public function testCheckPropertiesF4bCompanyType(): void
    {
        $this->baseTestFields(
            'f4b_companytype',
            ['Public', 'Private', 'Non-Profit', 'Government', 'public', 'private', 'non-profit', 'government']
        );
    }

    public function testCheckPropertiesF4bCompanyTechnologies(): void
    {
        $this->baseTestFields('f4b_companytechnologies', ['Mautic', 'mautic', 'Options', 'Add a value']);
    }

    public function testCheckPropertiesF4bCompanyAnnualRevenueRange(): void
    {
        $this->baseTestFields(
            'f4b_companyannual_revenue_range',
            ['$10B+', 'Select']
        );
    }

    public function testCheckPropertiesF4bCompanyFundingRaised(): void
    {
        $this->baseTestFields('f4b_companyfunding_raised', ['Number']);
    }

    public function testCheckPropertiesF4bCompanyFacebookHandle(): void
    {
        $this->baseTestFields('f4b_companyfacebook_handle', ['Text']);
    }

    public function testCheckPropertiesF4bCompanyLinkedinHandle(): void
    {
        $this->baseTestFields('f4b_companylinkedin_handle', ['Text']);
    }

    public function testCheckPropertiesF4bCompanyTwitterHandle(): void
    {
        $this->baseTestFields('f4b_companytwitter_handle', ['Text']);
    }

    /**
     * @param array<string> $finds
     */
    private function baseTestFields(string $alias, array $finds): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $this->useCleanupRollback = false;
        $resultFieldCompanyType   = $this->em->getRepository(LeadField::class)->findOneBy(['alias' => $alias]);
        $this->assertInstanceOf(LeadField::class, $resultFieldCompanyType);
        $this->client->request('GET', '/s/contacts/fields/edit/'.$resultFieldCompanyType->getId());
        $content = $this->client->getResponse()->getContent();
        foreach ($finds as $find) {
            $this->assertIsString($content);
            $this->assertStringContainsString($find, $content);
        }
    }
}
