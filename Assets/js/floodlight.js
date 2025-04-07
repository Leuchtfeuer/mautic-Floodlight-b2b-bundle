/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

Mautic.verifyFloodlightApiKey = function (btn) {
    //apiKeyValue bekommen
    //mQuery(btn).prop('disabled', true);
    //mQuery(btn).prop('disabled', false);
    console.log('verifyFloodlightApiKey');
    var apikey = mQuery('#integration_config_apiKeys_api_key').val();
    console.log('apiKeyValue: ' + apikey);
    this.makeValidationFloodlightRequest(apikey);

    //controller starten mit api key passed
    //response
    //make decision (3 cases)
    //An ui geben
    //ggf in Datenbank speichern


    //firewall vorhanden (bruteforce) (kann wirklich jeder brwowser floodlight aufrufen)
};

Mautic.makeValidationFloodlightRequest = function(apikey) {
    var query = "action=plugin:leuchtfeuerFloodlightB2B:verifyFloodlightApiKey&apiKey=" + apikey;
    mQuery.ajax({
        url: mauticAjaxUrl,
        type: "GET",
        data: query,
        dataType: "json",
        success: (response, status, error) => {
            if (error.status === 204) {
                console.log('request done 204')
            }
            else {
                console.log('request done success')
            }
        },
        error: (response) => {
            if (response.status === 403) {
                console.log('Plugin is not published');
            }
            else {
                console.log(response, status);
            }
        }
    });
}
