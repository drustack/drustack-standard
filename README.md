DruStack Standard Edition
=========================

[![Build Status](https://travis-ci.org/drustack/drustack-standard.svg?branch=master)](https://travis-ci.org/drustack/drustack-standard)
[![Latest Stable Version](https://poser.pugx.org/drustack/framework-standard-edition/v/stable.svg)](https://packagist.org/packages/drustack/framework-standard-edition)
[![Total Downloads](https://poser.pugx.org/drustack/framework-standard-edition/downloads.svg)](https://packagist.org/packages/drustack/framework-standard-edition)
[![License](https://poser.pugx.org/drustack/framework-standard-edition/license.svg)](https://packagist.org/packages/drustack/framework-standard-edition)

Welcome to the DruStack Standard Edition - a fully-functional Drupal application that you can use as the skeleton for your new applications.

This project template will managing your site dependencies with [Composer](https://getcomposer.org/). If you want to know how to use it as replacement for [Drush Make](https://github.com/drush-ops/drush/blob/master/docs/make.md) visit the [Documentation on drupal.org](https://www.drupal.org/node/2471553).

Usage
-----

First you need to [install composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx).

After that you can create the project:

    composer create-project drustack/framework-standard-edition:^8.2.0 MYPROJECT --no-interaction

With `composer require ...` you can download new dependencies to your installation.

    composer require drupal/devel:~8.0

The `composer create-project` command passes ownership of all files to the project that is created. You should create a new git repository, and commit all files not excluded by the .gitignore file.

What does the template do?
--------------------------

When installing the given `composer.json` some tasks are taken care of:

-   Drupal will be installed in the `web`-directory.
-   Autoloader is implemented to use the generated composer autoloader in `vendor/autoload.php`,
    instead of the one provided by Drupal (`web/vendor/autoload.php`).
-   Modules (packages of type `drupal-module`) will be placed in `web/modules/contrib/`
-   Theme (packages of type `drupal-theme`) will be placed in `web/themes/contrib/`
-   Profiles (packages of type `drupal-profile`) will be placed in `web/profiles/`
-   Creates default writable versions of `settings.php` and `services.yml`.
-   Creates `sites/default/files`-directory.
-   Latest version of drush is installed locally for use at `vendor/bin/drush`.
-   Latest version of DrupalConsole is installed locally for use at `vendor/bin/drupal`.

License
-------

-   Code released under [GPL-2.0+](https://github.com/drustack/drustack-standard/blob/master/LICENSE)
-   Docs released under [CC BY 4.0](http://creativecommons.org/licenses/by/4.0/)

Author Information
------------------

-   Wong Hoi Sing Edison
    -   <https://twitter.com/hswong3i>
    -   <https://github.com/hswong3i>

