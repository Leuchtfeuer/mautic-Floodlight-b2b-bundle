console.log('Test:', typeof Mautic);

Mautic.validateAndSubmitFloodlightForm = function(form, clickedButton) {
    console.log('validateAndSubmitFloodlightForm called'); // DEBUG
    
    var accessKeyId = mQuery('#integration_config_apiKeys_api_access_key_id').val();
    var secretKey = mQuery('#integration_config_apiKeys_api_secret_key').val();
    
    console.log('AccessKeyId:', accessKeyId); // DEBUG
    console.log('SecretKey:', secretKey); // DEBUG
    
    // Entferne alle vorherigen Validierungsmeldungen
    mQuery('.api-validation-message').remove();
    
    // Validiere, dass beide Felder ausgefüllt sind
    if (!accessKeyId || accessKeyId.trim() === '' || !secretKey || secretKey.trim() === '') {
        Mautic.showFloodlightValidationMessage('Both API Access Key ID and API Secret Key are required', 'danger');
        return false;
    }
    
    // AJAX-Validierung gegen den Floodlight-Endpunkt
    var query = "action=plugin:leuchtfeuerFloodlightB2B:verifyFloodlightApiKey&apiAccessKeyId=" + encodeURIComponent(accessKeyId) + "&apiSecretKey=" + encodeURIComponent(secretKey);
    
    console.log('AJAX Query:', query); // DEBUG
    console.log('AJAX URL:', mauticAjaxUrl); // DEBUG
    
    mQuery.ajax({
        url: mauticAjaxUrl,
        type: "GET",
        data: query,
        dataType: "json",
        success: function(message, textStatus, jqXHR) {
            console.log('AJAX Success:', message); // DEBUG
            Mautic.submitFloodlightForm(form, clickedButton);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log('AJAX Error:', jqXHR.status, textStatus, errorThrown); // DEBUG
            Mautic.showFloodlightValidationMessage('Your credentials are invalid', 'danger');
        }
    });
    
    return false;
};

// Zeigt die einfache Fehlermeldung an
Mautic.showFloodlightValidationMessage = function(message, type) {
    var messageClass = type === 'danger' ? 'text-danger' : 'text-success';
    var messageHtml = '<div class="api-validation-message ' + messageClass + ' mt-2">' + message + '</div>';
    
    // Füge die Nachricht nach dem letzten API-Feld hinzu
    mQuery('#integration_config_apiKeys_api_secret_key').closest('.form-group').after(messageHtml);
};

// Führt das tatsächliche Form-Submit aus nach erfolgreicher Validierung
Mautic.submitFloodlightForm = function(form, clickedButton) {
    console.log('submitFloodlightForm called'); // DEBUG
    
    // Entferne temporär den Submit-Event-Handler
    mQuery(form).off('submit.floodlight');
    
    // Normales Form-Submit durchführen
    mQuery(form).submit();
};

// Erweitere die bestehende integrationsConfigOnLoad Funktion
(function() {
    // Speichere die ursprüngliche Funktion
    var originalConfigOnLoad = Mautic.integrationsConfigOnLoad;
    
    // Überschreibe mit erweiterter Funktion
    Mautic.integrationsConfigOnLoad = function() {
        // Führe die ursprüngliche Funktion aus
        if (originalConfigOnLoad) {
            originalConfigOnLoad();
        }
        
        // Prüfe, ob wir im Floodlight-Modal sind
        if (mQuery('.modal-title:contains("Floodlight")').length > 0) {
            console.log('Floodlight modal detected - registering validation');
            Mautic.setupFloodlightValidation();
        }
    };
})();

// Setup für Floodlight-spezifische Validierung
Mautic.setupFloodlightValidation = function() {
    var saveButtons = mQuery('.modal-form-buttons .btn-save, .modal-form-buttons .btn-apply');
    
    // WICHTIG: Entferne ALLE anderen Event-Handler von diesen Buttons
    saveButtons.off();
    
    // Registriere unseren Handler mit höchster Priorität
    saveButtons.on('click.floodlight', function(event) {
        console.log('Floodlight save button clicked');
        
        // Stoppe ALLE Event-Verarbeitung sofort
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation(); // Das ist der wichtige Teil!
        
        var clickedButton = mQuery(this);
        
        // Speichere ursprüngliche Button-Attribute
        var originalOnClick = clickedButton.attr('onclick');
        var originalDataDismiss = clickedButton.attr('data-dismiss');
        
        // Entferne alle Attribute, die das Modal schließen könnten
        clickedButton.removeAttr('onclick');
        clickedButton.removeAttr('data-dismiss');
        clickedButton.removeAttr('data-toggle');
        
        // Führe Validierung durch
        Mautic.validateFloodlightCredentials(clickedButton, originalOnClick, originalDataDismiss);
        
        return false;
    });
};

// Validierung für Floodlight-Credentials
Mautic.validateFloodlightCredentials = function(clickedButton, originalOnClick, originalDataDismiss) {
    console.log('Validating credentials...');
    
    var accessKeyId = mQuery('#integration_config_apiKeys_api_access_key_id').val();
    var secretKey = mQuery('#integration_config_apiKeys_api_secret_key').val();
    
    console.log('AccessKeyId:', accessKeyId);
    console.log('SecretKey:', secretKey);
    
    // Entferne vorherige Meldungen
    mQuery('.api-validation-message').remove();
    
    // Felder-Validierung
    if (!accessKeyId || !secretKey) {
        Mautic.showFloodlightError('Both API Access Key ID and API Secret Key are required');
        // Stelle Button-Attribute wieder her
        Mautic.restoreButtonAttributes(clickedButton, originalOnClick, originalDataDismiss);
        return;
    }
    
    // AJAX-Validierung
    var query = "action=plugin:leuchtfeuerFloodlightB2B:verifyFloodlightApiKey&apiAccessKeyId=" + encodeURIComponent(accessKeyId) + "&apiSecretKey=" + encodeURIComponent(secretKey);
    
    console.log('Sending AJAX:', query);
    
    mQuery.ajax({
        url: mauticAjaxUrl,
        type: "GET",
        data: query,
        dataType: "json",
        success: function(response) {
            console.log('AJAX Success - credentials valid');
            // Credentials gültig - jetzt das Modal richtig schließen
            Mautic.executeFloodlightSave(clickedButton, originalOnClick, originalDataDismiss);
        },
        error: function(jqXHR) {
            console.log('AJAX Error - credentials invalid');
            // Credentials ungültig - Fehler anzeigen, Modal offen lassen
            Mautic.showFloodlightError('Your credentials are invalid');
            // Button-Attribute wieder herstellen für erneute Versuche
            Mautic.restoreButtonAttributes(clickedButton, originalOnClick, originalDataDismiss);
        }
    });
};

// Führe das echte Speichern durch
Mautic.executeFloodlightSave = function(clickedButton, originalOnClick, originalDataDismiss) {
    console.log('Executing original save action');
    
    // Stelle ursprüngliche Attribute wieder her
    Mautic.restoreButtonAttributes(clickedButton, originalOnClick, originalDataDismiss);
    
    // Entferne unseren Handler temporär
    clickedButton.off('click.floodlight');
    
    // Simuliere den ursprünglichen Click
    if (originalOnClick) {
        eval(originalOnClick);
    } else {
        // Fallback: Form submit
        mQuery('form[name="integration_config"]').submit();
    }
};

// Stelle Button-Attribute wieder her
Mautic.restoreButtonAttributes = function(clickedButton, originalOnClick, originalDataDismiss) {
    if (originalOnClick) {
        clickedButton.attr('onclick', originalOnClick);
    }
    if (originalDataDismiss) {
        clickedButton.attr('data-dismiss', originalDataDismiss);
    }
};

// Zeige Fehlermeldung
Mautic.showFloodlightError = function(message) {
    mQuery('.api-validation-message').remove();
    var errorHtml = '<div class="api-validation-message text-danger mt-2">' + message + '</div>';
    mQuery('#integration_config_apiKeys_api_secret_key').closest('.form-group').after(errorHtml);
};

