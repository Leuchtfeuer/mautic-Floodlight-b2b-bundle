# Floodlight for B2B by Leuchtfeuer

CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* Usage
* Author

INTRODUCTION
------------
The Floodlight for B2B is one plugin of ABM solution, it is responsible to detect and qualify companies from anonymous leads in Mautic, using the Floodlight for B2B service.

REQUIREMENTS
------------
- Mautic 5.0 or higher
- PHP 8.0 or higher

INSTALLATION
------------
1. Add `LeuchtfeuerFloodlightB2BBundle` in the `plugins` directory.
2. Go to `Plugins` page
3. Click on the `Install/Upgrade Plugins` or if you have access to the shell, execute `php bin\console mautic:plugins:reload` to install the plugins.
4. Click button to install `LeuchtfeuerFloodlightB2BBundle`.
5. Enable the plugin and configure the plugin with the API key. 

USAGE
-----
Once the plugin is installed and configured, it will automatically start detecting and saving the companies of your anonymous leads whenever they execute a pagehit.

The plugin also allows you to run two commands:

1. leuchtfeuer:floodlight:enrich --by-domain (also available as leuchtfeuer:floodlight:enrich:domain)
   - This command enriches companies that have been saved manually (there won't be more than two tries to enrich these companies)
2. leuchtfeuer:floodlight:enrich --by-address (also available as leuchtfeuer:floodlight:enrich:address)
   - This command makes sure all the lookups are done again which have the status retry (as Floodlight could not provide the company data on the first try)

The standalone command leuchtfeuer:floodlight:enrich will not work without the `--by-domain` or `--by-address` options.


CONFIGURATION
-------------
Enable the plugin and configure the plugin with the API key.

### API URL Configuration

The plugin connects to the Floodlight B2B API. The API URL can be configured:

1.  **Default:** If no other configuration is provided, it defaults to `https://api.floodlight-b2b.com`.
2.  **Via Environment Variable (Recommended for Testing/Development):**
    You can override the API URL by setting the `FLOODLIGHT_API_URL` environment variable. This is the highest priority. This is particularly useful for pointing the plugin to a mock server (like Mockoon) for tests.

    Example for running tests against a local Mockoon server on port 3002:
    ```bash
    FLOODLIGHT_API_URL="http://localhost:3002" composer test plugins/LeuchtfeuerFloodlightB2BBundle/Tests/
    ```

### Running Mockoon for Local Testing

To use a local Mockoon server for testing:

1.  Navigate to the Mockoon configuration directory within the plugin:
    ```bash
    cd plugins/LeuchtfeuerFloodlightB2BBundle/Tests/Mockoon
    ```
2.  Install the necessary dependencies (if you haven't already):
    ```bash
    npm install
    ```
    (This typically installs `mockoon-cli` and any other local dependencies.)
3.  Start the Mockoon server with the predefined mock data:
    ```bash
    npx mockoon-cli start --data ./floodlight_mockoon.json
    ```
    This will usually start the mock server on `http://localhost:3002`. You can then use the `FLOODLIGHT_API_URL` environment variable as shown above to direct the plugin's API calls to your local Mockoon instance.


AUTHOR
------
Leuchtfeuer Digital Marketing GmbH