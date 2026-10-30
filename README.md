# Floodlight for B2B by Leuchtfeuer

## Overview
The Floodlight for B2B is an add-on to Mautic's Account Based Marketing (ABM) capabilities (which is another Leuchtfeuer plugin).
Floodlight for B2B is responsible for enriching company properties, and optionally for detecting companies from anonymous leads in Mautic - which empowers company qualification and segmentation.
This plugin requires a (free or paid) subscription with the Floodlight for B2B API service (https://floodlight-b2b.com)

## Features
- Detects and qualifies companies from anonymous leads using the Floodlight for B2B service.
- Supports reverse proxy setups, including Cloudflare and others.
- Provides domain and keyword blacklisting to exclude unwanted companies.
- Provides commands to enrich companies and retry failed lookups.
- Brings a Mautic dashboard widget to visualize the use of API credits
- Optionally disallows free email providers on form email fields.

## Requirements
> [!TIP]
> Other releases of this plugin may cover different Mautic versions!
- Mautic 7.x (min. 7.0)

## Version Support
- PHP 8.2 or higher
- jeremykendall/php-domain-parser ^6.3

## Installation
> [!TIP]
> You can find a written and video tutorial on https://floodlight-b2b.com/installing-the-floodlight-for-b2b-mautic-plugin/

### Composer
This plugin is on https://packagist.org/packages/leuchtfeuer/mautic-floodlight-b2b-bundle.

You can very simply require it in composer.json ("leuchtfeuer/mautic-floodlight-b2b-bundle")

### Manual Installation
The plugin can be installed manually, following the usual steps:
- Download the plugin
- Unzip to the Mautic `plugins` directory
- Rename folder to `LeuchtfeuerFloodlightB2BBundle`
- Install the required php-domain-parser dependency: `composer require jeremykendall/php-domain-parser:^6.3`
- In the Mautic backend, go to the `Plugins` page as an administrator
- Click on the `Install/Upgrade Plugins` button to install the Plugin.

OR

- If you have shell access, execute `php bin/console cache:clear` and `php bin/console mautic:plugins:reload` to install the plugins.

### Installation without Composer
For Mautic installations without Composer, every GitHub release provides a self-contained ZIP named `LeuchtfeuerFloodlightB2BBundle-<version>.zip` (e.g. `LeuchtfeuerFloodlightB2BBundle-7.1.0.zip`). This ZIP already contains all Composer requirements (php-domain-parser), so no `composer require` step is needed:
- Download the ZIP from the GitHub release
- Unzip to the Mautic `plugins` directory (the folder is already named `LeuchtfeuerFloodlightB2BBundle`)
- In the Mautic backend, go to the `Plugins` page as an administrator and click on the `Install/Upgrade Plugins` button — or, with shell access, execute `php bin/console cache:clear` and `php bin/console mautic:plugins:reload`

Optional patches:
- If required, install the patch which makes sure that when lead name (firstname and lastname) is not available, displaying email will be preferred over displaying company name --> https://github.com/mautic/mautic/pull/15320

## Configuration
Enable the plugin and configure it with the `API Access Key ID` and the `API Secret Key`.

## Usage
Once the plugin is installed and configured, it will automatically start detecting and saving the companies of your anonymous leads whenever they execute a pagehit.

The plugin also allows you to run two commands:

1. `leuchtfeuer:floodlight:enrich-companies`
   - This command enriches companies that have been saved manually (there won't be more than two tries to enrich these companies)
2. `leuchtfeuer:floodlight:lookup-retries`
   - This command makes sure all the lookups are done again which have the status retry (as Floodlight could not provide the company data on the first try)

The standalone command `leuchtfeuer:floodlight:enrich` will not work without the `--by-domain` or `--by-address` options.

Floodlight also supports reverse proxy setups including Cloudflare and others.

### Blacklisting Domains
When installing the plugin, the files `f4b_domain_blacklist.php` and `f4b_domain_keyword_blacklist.php` will be copied to the config directory of your Mautic installation.

The file `f4b_domain_blacklist.php` is for blocking entire domains, the file `f4b_domain_keyword_blacklist.php` is for blocking domains which contain certain keywords.
To add your own domains or keywords, just add a new line with your domain/keyword in these files.

Note:
* Making changes in the blacklist files in the config directory of the plugin will not work, as the blacklist files from the config directory of your Mautic installation will be used.
* Be careful with the `f4b_domain_keyword_blacklist.php` file. If you add a keyword like `apple`, the domains `pineapple.com`, `applepie.com`, etc. will also be blacklisted.

### Disallow Freemailers on Form Email Fields
Email fields in the Mautic Form Builder get an additional "Disallow freemailers" option (independent of the "Required" option). When enabled, submissions with an email address from a common free/consumer email provider (e.g. Gmail, Yahoo) are rejected the same way as submissions with no email or an invalid email syntax.

The list of free email providers is bundled with the plugin (`Config/f4b_free_email_domains.json`, based on https://github.com/Kikobeats/free-email-domains) and updated with each plugin release. It is not editable at runtime. This list is MIT-licensed; see `THIRD_PARTY_LICENSES.md` for the license text.

## Future Ideas
* periodic refresh of  enriched data, from Plugin to API Service
* better handling of “try again” status (would be ok to try 10 times or so, but we currently don’t have such a counter - we merely set 1970 at first try, current date at second try)

## Credits
- @Leuchtfeuer

## Author
Leuchtfeuer Digital Marketing GmbH

Please raise any issues in GitHub.
For all other things, please email mautic-plugins@Leuchtfeuer.com
