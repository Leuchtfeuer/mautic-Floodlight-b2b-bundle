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
     * Verifiziere den Floodlight API-Key
     *
     * @return JsonResponse
     */
    public function verifyFloodlightApiKeyAction(Request $request, FloodlightService $floodlightService, ValidationCredentials $credentials): JsonResponse
    {

        //$apiKey = $request->get('apikey');
        $apiKey = $request->query->get('apiKey');
        $credentials->setApiKey($apiKey);
        $apiClient = $floodlightService->getAPIClient();
        $apiClient->setCredentials($credentials);
        return $floodlightService->validateApiKey();

        /*
        if (empty($apiKey)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'API-Schlüssel darf nicht leer sein',
            ]);
        }

        try {
            $isValid = $this->floodlightService->verifyApiKey($apiKey);
            
            if ($isValid) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'API-Schlüssel wurde erfolgreich verifiziert!',
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Ungültiger API-Schlüssel. Bitte überprüfen Sie Ihre Eingabe.',
                ]);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Fehler bei der Überprüfung: ' . $e->getMessage(),
            ], 500);
        }
        */
    }
} 