# Floodlight for B2B by Leuchtfeuer



INTRODUCTION
------------
The Floodlight for B2B is an add-on to Mautic's "account based marketing" capabilities (which is itself a Leuchtfeuer plugin set as of Mautic 5 and 6; intended to be part of the Mautic 7 core).
It is responsible for enriching company properties, and optionally for detecting companies from anonymous leads in Mautic - which empowers company qualification and segmentation.
This plugin requires the Floodlight for B2B API service.

REQUIREMENTS
------------
- Mautic 5.x
- PHP 8.0 or higher

INSTALLATION
------------
1. Add `LeuchtfeuerFloodlightB2BBundle` in the `plugins` directory.
2. Go to `Plugins` page
3. Click on the `Install/Upgrade Plugins` or if you have access to the shell, execute `php bin\console mautic:plugins:reload` to install the plugins.
4. Click button to install `LeuchtfeuerFloodlightB2BBundle`.
5. Enable the plugin and configure the plugin with the API key.
6. Install the patch which enables you to name the leads identified by floodlight according to the following logic: 'anonymous {timestamp} {domain}' --> https://github.com/mautic/mautic/pull/15153
7. If required, install the patch which makes sure that when lead name (firstname and lastname) ist not available, displaying email will be preferred over displaying company name --> https://github.com/mautic/mautic/pull/15320

CONFIGURATION
-------------
Enable the plugin and configure the plugin with the `API Access Key ID` and the `API Secret Key`.

USAGE
-----

Once the plugin is installed and configured, it will automatically start detecting and saving the companies of your anonymous leads whenever they execute a pagehit.

The plugin also allows you to run two commands:

1. leuchtfeuer:floodlight:enrich-companies
   - This command enriches companies that have been saved manually (there won't be more than two tries to enrich these companies)
2. leuchtfeuer:floodlight:lookup-retries
   - This command makes sure all the lookups are done again which have the status retry (as Floodlight could not provide the company data on the first try)

The standalone command leuchtfeuer:floodlight:enrich will not work without the `--by-domain` or `--by-address` options.

Floodlight also supports reverse proxy setups including Cloudflare and others.

BLACKLISTING DOMAINS
-----------
When installing the plugin the files `f4b_domain_blacklist.php` and `f4b_domain_keyword_blacklist.php` will be copied in the config directory of you Mautic installation.

The file `f4b_domain_blacklist.php` is for blocking entire domains, the file `f4b_domain_keyword_blacklist.php` is for blocking domains which contain certain keywords.
To add your own domains or keywords, just add a new line with your domain/keyword in these files.

Note:
* Making changes in the blacklist files in the config directory of the plugin will not work, as the blacklist files from the config directory of your Mautic installation will be used.
* Be carefull with the `f4b_domain_keyword_blacklist.php` file. If you add a keyword like `apple`, the domains `pineapple.com`, `applepie.com`, etc. will also be blacklisted.


DISALLOW FREEMAILERS ON FORM EMAIL FIELDS
-----------
Email fields in the Mautic Form Builder get an additional "Disallow freemailers" option (independent of the "Required" option). When enabled, submissions with an email address from a common free/consumer email provider (e.g. Gmail, Yahoo) are rejected the same way as submissions with no email or an invalid email syntax.

The list of free email providers is bundled with the plugin (`Config/f4b_free_email_domains.json`, based on https://github.com/Kikobeats/free-email-domains) and updated with each plugin release. It is not editable at runtime. This list is MIT-licensed; see `THIRD_PARTY_LICENSES.md` for the license text.

FUTURE IMPROVEMENTS
-------------------
* periodic renewal request, from Plugin to API Service
* better handling of “try again” status (would be ok to try 10 times or so, but we currently don’t have such a counter - we merely set 1970 at first try, current date at second try)

AUTHOR
------
Leuchtfeuer Digital Marketing GmbH
