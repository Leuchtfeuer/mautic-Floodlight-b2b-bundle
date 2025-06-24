<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Controller;

use http\Env\Response;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Connection\ValidationCredentials;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Services\FloodlightService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxController extends CommonAjaxController
{

    /**
     * Verifiziere die Floodlight API-Credentials
     *
     * @return JsonResponse
     */
    public function verifyFloodlightApiKeyAction(Request $request, FloodlightService $floodlightService, ValidationCredentials $credentials): JsonResponse
    {
        $accessKeyId = $request->query->get('apiAccessKeyId');
        $secretKey = $request->query->get('apiSecretKey');
        
        $credentials->setAccessKeyId($accessKeyId);
        $credentials->setSecretKey($secretKey);
        
        $apiClient = $floodlightService->getAPIClient();
        $apiClient->setCredentials($credentials);
        
        return $floodlightService->validateApiKey();
    }
} 