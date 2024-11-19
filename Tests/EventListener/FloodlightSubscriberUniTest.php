<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\EventListener;

use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PageBundle\Event\PageHitEvent;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightCompanyDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightIpDomainLookupsRepository;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\EventListener\FloodlightSubscriber;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\LeuchtfeuerFloodlightB2BIntegration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FloodlightSubscriberUniTest extends TestCase
{
    private Config $config;

    private $ipLookupRulesHelper;

    private $floodlightCompanyDomainsModel;

    private $floodlightIpDomainLookupsModel;

    private $floodlightService;

    private $logger;

    private $fieldHelper;

    private $companyModel;

    public function setUp(): void
    {
        $this->config                         = $this->setConfig();
        $this->ipLookupRulesHelper            = $this->setipLookupRulesHelper();
        $this->floodlightCompanyDomainsModel  = $this->setFloodlightCompanyDomainModel();
        $this->floodlightIpDomainLookupsModel = $this->setFloodlightIpDomainLookupModel();
        $this->floodlightService              = $this->setFloodlightService();
        $this->logger                         = $this->setLogger();
        $this->fieldHelper                    = $this->setFieldHelper();
        $this->companyModel                   = $this->setCompanyModel();
    }

    public function testOnPageHit(): void
    {
        $floodlightSubscriber = new FloodlightSubscriber(
            $this->ipLookupRulesHelper,
            $this->config,
            $this->floodlightCompanyDomainsModel,
            $this->floodlightIpDomainLookupsModel,
            $this->floodlightService,
            $this->logger,
            $this->fieldHelper,
            $this->companyModel
        );
        $pageHitEvent = $this->setPageHitEvent();
        $floodlightSubscriber->onPageHit($pageHitEvent);
    }

    private function setConfig(bool $isPublished  = true, bool $isConfigured = true, bool $isProTear= true): Config
    {
        $IntegrationsHelper = $this->createMock(IntegrationsHelper::class);
        $integration        = $this->createMock(\Mautic\PluginBundle\Entity\Integration::class);
        $integration->expects($this->any())->method('isPublished')->willReturn($isPublished);
        $integration->expects($this->exactly(4))->method('getApiKeys')->willReturn(['api_key'=>'pro_123']);
        $integration->expects($this->once())->method('getIsPublished')->willReturn($isPublished);
        $integrationFloodlight = new LeuchtfeuerFloodlightB2BIntegration();
        $integrationFloodlight->setIntegrationConfiguration($integration);
        $IntegrationsHelper->expects($this->exactly(5))->method('getIntegration')->willReturn($integrationFloodlight);

        return new Config($IntegrationsHelper);
    }

    private function setPageHitEvent(): PageHitEvent
    {
        $hit          = $this->createMock(\Mautic\PageBundle\Entity\Hit::class);
        $pageHitEvent = $this->createMock(PageHitEvent::class);
        $lead         = $this->createMock(\Mautic\LeadBundle\Entity\Lead::class);
        $lead->expects($this->any())->method('getId')->willReturn(1);
        $hit->expects($this->once())->method('getLead')->willReturn($lead);
        $pageHitEvent->expects($this->exactly(3))->method('getHit')->willReturn($hit);

        $server = new \Symfony\Component\HttpFoundation\ServerBag();
        $server->set('REMOTE_ADDR', '127.0.0.1');
        $request         = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $request->server = $server;
        $pageHitEvent->expects($this->exactly(2))->method('getRequest')->willReturn($request);

        return $pageHitEvent;
    }

    private function setipLookupRulesHelper()
    {
        $ipLookupRulesHelper = $this->createMock(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\IpLookupRulesHelper::class);
        $ipLookupRulesHelper->expects($this->once())->method('check')->willReturn(true);
        $dataDomain = json_decode($this->dataJsonFromFloodlight(), true);
        $ipLookupRulesHelper->expects($this->once())->method('getDomain')->willReturn([
            'status'  => 'valid',
            'domain'  => 'google.com',
            'details' => ['floodlight' => new \MauticPlugin\LeuchtfeuerFloodlightB2BBundle\DTO\FloodlightDTO($dataDomain)],
        ]);
        $ipLookupRulesHelper->expects($this->exactly(2))->method('isIp')->willReturn(true);
        $ipLookupRulesHelper->expects($this->once())->method('evaluate')->willReturn(['domain' => 'google.com', 'result' => 'valid', 'status' => 'valid']);

        //        $ipLookupRulesHelper->expects($this->once())->method('checkMoreThan2')->willReturn(true);
        return $ipLookupRulesHelper;
    }

    private function setFloodlightCompanyDomainModel()
    {
        $flCompanyDomainsModel    = $this->createMock(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightCompanyDomainsModel::class);
        $FloodlightCompanyDomains = new FloodlightCompanyDomains();
        $flCompanyDomainsModel->expects($this->any())->method('getEntity')->willReturn($FloodlightCompanyDomains);
        $FloodlightCompanyDomainsRepository = $this->createMock(FloodlightCompanyDomains::class);
        $flCompanyDomainsModel->expects($this->any())->method('getRepository')->willReturn($FloodlightCompanyDomainsRepository);

        $floodlightCompanyDomains1 = new FloodlightCompanyDomains();
        $floodlightCompanyDomains1->setCompany(new \Mautic\LeadBundle\Entity\Company());
        $floodlightCompanyDomains1->setDomain('google.com');

        $floodlightCompanyDomains2 = new FloodlightCompanyDomains();
        $floodlightCompanyDomains2->setCompany(new \Mautic\LeadBundle\Entity\Company());
        $floodlightCompanyDomains2->setDomain('google.pt');

        $flCompanyDomainsModel->expects($this->once())->method('save')->willReturn(
            [
                $floodlightCompanyDomains1,
                $floodlightCompanyDomains2,
            ]
        );

        return $flCompanyDomainsModel;
    }

    private function setFloodlightIpDomainLookupModel(bool $isNew = true)
    {
        $floodlightIpDomainLookupsModel = $this->createMock(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightIpDomainLookupsModel::class);
        //        $floodlightIpDomainLookupsModel->expects($this->once())->method('save')->willReturn([]);
        $repository = $this->createMock(FloodlightIpDomainLookupsRepository::class);
        $repository->expects($this->exactly(2))->method('isNew')->willReturn($isNew);
        $floodlightIpDomainLookupsModel->expects($this->exactly(2))->method('getRepository')->willReturn($repository);

        return $floodlightIpDomainLookupsModel;
    }

    public function setFloodlightService()
    {
        $floodlightService = $this->createMock(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService::class);
        $floodlightService->expects($this->exactly(2))->method('get')->willReturn(json_decode($this->dataJsonFromFloodlight(), true));

        return $floodlightService;
    }

    private function setLogger()
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->any())->method('error');

        return $logger;
    }

    private function setFieldHelper()
    {
        return $this->createMock(\MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\FieldHelper::class);
    }

    private function setCompanyModel()
    {
        $companyModel = $this->createMock(\Mautic\LeadBundle\Model\CompanyModel::class);
        $companyModel->expects($this->any())->method('getEntity')->willReturn(new \Mautic\LeadBundle\Entity\Company());

        return $companyModel;
    }

    private function dataJsonFromFloodlight()
    {
        return '{
  "ip_hash": "d01ca441ca0b204738175c5f364ea0c210855417",
  "confidence": 18,
  "companyfloodlight_id": "eril33uvgcii9xvsxqkfuoyb",
  "primarydomain": "b-tu.de",
  "companyname": "Brandenburgische Technische Universitt Cottbus-Senftenberg",
  "companyname_legal": "Brandenburg University of Technology",
  "companywebsite": "http://www.b-tu.de/",
  "companyindustry": "higher education",
  "companyindustry_linkedin": "higher education",
  "companyindustry_list": [
    "General Retailers",
    "Specialized Consumer Services"
  ],
  "companylabels": [
    "Education",
    "Training",
    "Universities",
    "B2C"
  ],
  "companyindustry_naics": "6113",
  "companyindustry_icb_industrygroup": "Retail",
  "companyindustry_icb_industry": "General Retailers",
  "companyindustry_icb_subindustry": "Specialized Consumer Services",
  "companyindustry_icb_sector": "Consumer Services",
  "companydescription_long": "We are a young university dedicated to face current scientific challenges in the fields of energy efficiency, cyber-physical systems, bioengineering, environment, health, smart regions and heritage. We are offering over 70 Bachelor, Master, PhD and international programs. 26 percent of our 7.600 students come from over a hundred different countries. Our students are enjoying the international and familiar atmosphere. The central campus is in between Berlin and Dresden, 120 kilometers apart from each city and well connected by train",
  "companyfounding_year": 2013,
  "companyaddress1": "Platz Der Deutschen Einheit 1",
  "companycity": "Cottbus",
  "companystate_code": "BB",
  "companycountry": "Germany",
  "companycountry_code": "DE",
  "companyrank_tranco": 104100,
  "companynumber_of_employees": 3001,
  "companyemployees_range": "2000+",
  "companyannual_revenue_range": "$10B+",
  "company_technologies": [
    "moodle",
    "my_time",
    "karriere",
    "linked_in",
    "tiny_url",
    "twitter",
    "linktree",
    "kontakt_io",
    "oracle_commerce",
    "slack",
    "g_suite",
    "microsoft",
    "google_drive",
    "oracle_9_i",
    "cisco_web_ex",
    "ansys",
    "auto_cad",
    "simulink",
    "solidworks",
    "boomtown",
    "freshdesk",
    "freshservice",
    "unix",
    "j_boss",
    "fedora",
    "windows_7",
    "windows_xp",
    "oracle_linux",
    "adobe",
    "typo_3",
    "you_tube",
    "wordpress",
    "microsoft_visio",
    "ed_x",
    "lab_view",
    "google_workspace",
    "microsoft_office",
    "matomo_formerly_piwik",
    "php",
    "java",
    "blisk",
    "adobe_stock",
    "ruby_on_rails",
    "google_maps_platform"
  ],
  "companyfacebook_handle": "btucs",
  "companylinkedin_handle": "school/btu-cottbus-senftenberg",
  "companytwitter_handle": "BTU_CS",
  "companytwitter_followers": 2938,
  "companylogo": "http://logo.bigpicture.io/logo/b-tu.de"
}';
    }
}
