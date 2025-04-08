/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

Mautic.verifyFloodlightApiKey = function (btn) {
    var apikey = mQuery('#integration_config_apiKeys_api_key').val();
    
    if (!apikey || apikey.trim() === '') {
        mQuery('#integration_config_apiKeys_verify_floodlight_api_key').siblings('.api-validation-message').remove();
        
        mQuery('#integration_config_apiKeys_verify_floodlight_api_key').after(
            '<div class="api-validation-message text-danger mt-2">No API key provided</div>'
        );
        return;
    }
    
    this.makeValidationFloodlightRequest(apikey);
};

Mautic.makeValidationFloodlightRequest = function(apikey) {
    var query = "action=plugin:leuchtfeuerFloodlightB2B:verifyFloodlightApiKey&apiKey=" + apikey;
    
    // Entferne alle vorherigen Validierungsmeldungen
    mQuery('#integration_config_apiKeys_verify_floodlight_api_key').siblings('.api-validation-message').remove();
    
    mQuery.ajax({
        url: mauticAjaxUrl,
        type: "GET",
        data: query,
        dataType: "json",
        success: function(message, textStatus, jqXHR) {
            // Erfolgsfall - Status 200
            mQuery('#integration_config_apiKeys_verify_floodlight_api_key').after(
                '<div class="api-validation-message text-success mt-2">'+message.message+'</div>'
            );
        },
        error: function(jqXHR, textStatus, errorThrown) {
            try {
                // Versuche die JSON-Response zu parsen
                var response = JSON.parse(jqXHR.responseText);
                
                if (jqXHR.status === 429) {
                    // Quota überschritten (429)
                    mQuery('#integration_config_apiKeys_verify_floodlight_api_key').after(
                        '<div class="api-validation-message text-warning mt-2">'+response.message+'</div>'
                    );
                } 
                else if (jqXHR.status === 401) {
                    // Ungültiger API-Key (401)
                    mQuery('#integration_config_apiKeys_verify_floodlight_api_key').after(
                        '<div class="api-validation-message text-danger mt-2">'+response.message+'</div>'
                    );
                }
                else if (jqXHR.status === 400) {
                    // Bad Request - "Something went wrong with your validation"
                    mQuery('#integration_config_apiKeys_verify_floodlight_api_key').after(
                        '<div class="api-validation-message text-danger mt-2">'+response.message+'</div>'
                    );
                }
                else {
                    // Andere unerwartete Fehler
                    mQuery('#integration_config_apiKeys_verify_floodlight_api_key').after(
                        '<div class="api-validation-message text-danger mt-2">'+
                        (response.message || 'Something went wrong with your validation: ' + jqXHR.status) + '</div>'
                    );
                }
            } catch(e) {
                // Fallback, falls JSON-Parsing fehlschlägt
                mQuery('#integration_config_apiKeys_verify_floodlight_api_key').after(
                    '<div class="api-validation-message text-danger mt-2">Something went wrong with your validation: ' + jqXHR.status + '</div>'
                );
            }
        }
    });
}
