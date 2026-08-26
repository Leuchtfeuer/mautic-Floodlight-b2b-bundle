# Floodlight for B2B by Leuchtfeuer

## Overview
The Floodlight for B2B is one plugin of the ABM solution. It is responsible for detecting and qualifying companies from anonymous leads in Mautic, using the Floodlight for B2B service.

## Features
- Detects and qualifies companies from anonymous leads using the Floodlight for B2B service.
- Supports reverse proxy setups, including Cloudflare and others.
- Provides domain and keyword blacklisting to exclude unwanted companies.
- Provides commands to enrich companies and retry failed lookups.

## Requirements
> [!TIP]
> Other releases of this plugin may cover different Mautic versions!
- Mautic 7.x (min. 7.0)

## Version Support
- PHP 8.2 or higher
- jeremykendall/php-domain-parser ^6.3

## Installation
### Composer
This plugin can be installed through composer. It also requires the `jeremykendall/php-domain-parser` dependency:

```
composer require jeremykendall/php-domain-parser:^6.3
```

### Manual Installation
Alternatively, it can be installed manually, following the usual steps:
- Download the plugin
- Unzip to the Mautic `plugins` directory
- Rename folder to `LeuchtfeuerFloodlightB2BBundle`
- Install the required php-domain-parser dependency: `composer require jeremykendall/php-domain-parser:^6.3`
- In the Mautic backend, go to the `Plugins` page as an administrator
- Click on the `Install/Upgrade Plugins` button to install the Plugin.

OR

- If you have shell access, execute `php bin/console cache:clear` and `php bin/console mautic:plugins:reload` to install the plugins.

Optional patches:
- Install the patch which enables you to name the leads identified by floodlight according to the following logic: `anonymous {timestamp} {domain}` --> https://github.com/mautic/mautic/pull/15153
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

## Credits
- @Leuchtfeuer

## Author
Leuchtfeuer Digital Marketing GmbH

Please raise any issues in GitHub.
For all other things, please email mautic-plugins@Leuchtfeuer.com
