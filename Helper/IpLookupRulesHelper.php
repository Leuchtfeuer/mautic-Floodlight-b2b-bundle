<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper;

use Mautic\CoreBundle\Loader\ParameterLoader;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PageBundle\Entity\Hit;
use Mautic\PageBundle\Model\PageModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Entity\FloodlightLeadDomains;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status200Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status202Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status404Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\Strategy\Status429Strategy;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Model\FloodlightLeadDomainsModel;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IpLookupRulesHelper
{
    private ParameterLoader $parameterLoader;

    public function __construct(
        private PageModel $pageModel,
        private FloodlightLeadDomainsModel $leadDomainsModel,
        private FloodlightService $floodlightService,
        private Reporter $reporter,
        private Status200Strategy $status200Strategy,
        private Status202Strategy $status202Strategy,
        private Status404Strategy $status404Strategy,
        private Status429Strategy $status429Strategy,
        private Config $config,
    ) {
        $this->parameterLoader = new ParameterLoader();
    }

    public function check(Hit $hit): bool
    {
        if (!$this->checkLeadHasMoreThanOnePagehit($hit->getLead()->getId())) {
            return false;
        }

        if (!$this->checkIfWasRecent($hit->getLead()->getId())) {
            return false;
        }

        return true;
    }

    private function checkIfWasRecent(int $leadId): bool
    {
        $result = $this->leadDomainsModel->getRepository()->findOneBy(['contact' => $leadId], ['dateModified' => 'DESC']);

        if (empty($result)) {
            return true;
        }

        assert($result instanceof FloodlightLeadDomains);

        $hour = $this->parameterLoader->getParameterBag()->get('minimum_hour_to_new_entry', 2);
        if ($result->getDateModified() > (new \DateTime())->modify('-'.$hour.' hour')) {
            return false;
        }

        return true;
    }

    private function checkLeadHasMoreThanOnePagehit(int $leadId): bool
    {
        $result = $this->pageModel->getHitRepository()->findBy(['lead' => $leadId]);

        return count($result) > 1;
    }

    /**
     * @param mixed $data
     */
    private function checkIfInListOfValidRequestStatuses($data): bool
    {
        $validStatusRequest = [
            '404',
            '202',
            '200',
            '429',
            '401',
        ];

        if (
            false === $data
            || !is_array($data)
            || !in_array($data['statusRequestCode'], $validStatusRequest)
        ) {
            return false;
        }

        return true;
    }

    public function getAndProcessFloodlightApiData(string $remoteAddr, Lead $lead): void
    {
        // When there was a floodlight request which returned 429 response in the last 60 minutes, we use dns and whois lookup directly
        if ($this->config->checkIfLookupReturned429ResponseInLast60Minutes()) {
            $processData = $this->status429Strategy->prepareData($remoteAddr);
            $this->status429Strategy->process($processData, $lead);

            return;
        }

        $data = $this->floodlightService->get($remoteAddr);

        if (!is_array($data)) {
            return;
        }

        if (!$this->checkIfInListOfValidRequestStatuses($data)) {
            // invalidStatusStrategy
            // @TODO: should there be logged anything?
            return;
        }

        if (200 == $data['statusRequestCode']) {
            $processData = $this->status200Strategy->prepareData($data);
            $this->status200Strategy->process($processData, $lead);
        }

        if (202 == $data['statusRequestCode']) {
            $processData = $this->status202Strategy->prepareData($data);
            $this->status202Strategy->process($processData, $lead);
        }

        if (404 == $data['statusRequestCode']) {
            $processData = $this->status404Strategy->prepareData($data);
            $this->status404Strategy->process($processData, $lead);
        }

        if (429 == $data['statusRequestCode']) {
            $this->config->setLast429Timestamp();

            $processData = $this->status429Strategy->prepareData($remoteAddr);
            $this->status429Strategy->process($processData, $lead);
        }

        if (401 == $data['statusRequestCode']) {
            $this->reporter->notify('Floodlight API returned 401 Unauthorized. Please check your API credentials.');
        }
    }

    public function notifyNoDataFromGetDomain(): void
    {
        $this->reporter->notify('Domain for given IP not found');
    }

    public function setChannel(LoggerInterface|OutputInterface $output): void
    {
        $this->reporter->setChannel($output);
    }
}
