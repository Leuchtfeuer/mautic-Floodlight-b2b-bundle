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
- Mautic 5.2 or higher
- PHP 8.1 or higher
- jeremykendall/php-domain-parser ^6.3

INSTALLATION
------------
1. Add `LeuchtfeuerFloodlightB2BBundle` in the `plugins` directory.

2. Install the required php-domain-parser dependency:

   `composer require jeremykendall/php-domain-parser:^6.3`

3. Go to `Plugins` page
4. Click on the `Install/Upgrade Plugins` or if you have access to the shell, execute `php bin\console mautic:plugins:reload` to install the plugins.
5. Click button to install `LeuchtfeuerFloodlightB2BBundle`.
6. Enable the plugin and configure the plugin with the API key.
7. Install the patch which enables you to name the leads identified by floodlight according to the following logic: 'anonymous {timestamp} {domain}' --> https://github.com/mautic/mautic/pull/15153
8. If required, install the patch which makes sure that when lead name (firstname and lastname) ist not available, displaying email will be preferred over displaying company name --> https://github.com/mautic/mautic/pull/15320

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


AUTHOR
------
Leuchtfeuer Digital Marketing GmbH
