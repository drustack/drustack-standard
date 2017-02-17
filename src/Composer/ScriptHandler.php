<?php

/**
 * @file
 * Contains \DruStack\Standard\Composer\ScriptHandler.
 */

namespace DruStack\Standard\Composer;

use Composer\Installer\PackageEvent;
use Composer\Script\Event;
use Composer\Semver\Comparator;
use Composer\Semver\Constraint\Constraint;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Provides static functions for composer script events.
 *
 * @see https://getcomposer.org/doc/articles/scripts.md
 */
class ScriptHandler
{
    protected static $packageToCleanup = [
        'behat/mink' => '/(tests|driver-testsuite)$/',
        'behat/mink-browserkit-driver' => '/(tests)$/',
        'behat/mink-goutte-driver' => '/(tests)$/',
        'doctrine/cache' => '/(tests)$/',
        'doctrine/collections' => '/(tests)$/',
        'doctrine/common' => '/(tests)$/',
        'doctrine/inflector' => '/(tests)$/',
        'doctrine/instantiator' => '/(tests)$/',
        'egulias/email-validator' => '/(documentation|tests)$/',
        'fabpot/goutte' => '/(Goutte\/Tests)$/',
        'guzzlehttp/promises' => '/(tests)$/',
        'guzzlehttp/psr7' => '/(tests)$/',
        'jcalderonzumba/gastonjs' => '/(docs|examples|tests)$/',
        'jcalderonzumba/mink-phantomjs-driver' => '/(tests)$/',
        'masterminds/html5' => '/(test)$/',
        'mikey179/vfsStream' => '/(src\/test)$/',
        'paragonie/random_compat' => '/(tests)$/',
        'phpdocumentor/reflection-docblock' => '/(tests)$/',
        'phpunit/php-code-coverage' => '/(tests)$/',
        'phpunit/php-mock-objects' => '/(tests)$/',
        'phpunit/php-timer' => '/(tests)$/',
        'phpunit/php-token-stream' => '/(tests)$/',
        'phpunit/phpunit' => '/(tests)$/',
        'sebastian/comparator' => '/(tests)$/',
        'sebastian/diff' => '/(tests)$/',
        'sebastian/environment' => '/(tests)$/',
        'sebastian/exporter' => '/(tests)$/',
        'sebastian/global-state' => '/(tests)$/',
        'sebastian/recursion-context' => '/(tests)$/',
        'stack/builder' => '/(tests)$/',
        'symfony-cmf/routing' => '/(Test|Tests)$/',
        'symfony/browser-kit' => '/(Tests)$/',
        'symfony/class-loader' => '/(Tests)$/',
        'symfony/console' => '/(Tests)$/',
        'symfony/css-selector' => '/(Tests)$/',
        'symfony/debug' => '/(Tests)$/',
        'symfony/dependency-injection' => '/(Tests)$/',
        'symfony/dom-crawler' => '/(Tests)$/',
        // @see \Drupal\Tests\Component\EventDispatcher\ContainerAwareEventDispatcherTest
        // 'symfony/event-dispatcher' => '/(Tests)$/',
        'symfony/http-foundation' => '/(Tests)$/',
        'symfony/http-kernel' => '/(Tests)$/',
        'symfony/process' => '/(Tests)$/',
        'symfony/psr-http-message-bridge' => '/(Tests)$/',
        'symfony/routing' => '/(Tests)$/',
        'symfony/serializer' => '/(Tests)$/',
        'symfony/translation' => '/(Tests)$/',
        'symfony/validator' => '/(Tests|Resources)$/',
        'symfony/yaml' => '/(Tests)$/',
        'twig/twig' => '/(doc|ext|test)$/',
    ];

    /**
     * Add vendor classes to Composer's static classmap.
     */
    public static function preAutoloadDump(Event $event)
    {
        // We need the root package so we can add our classmaps to its loader.
        $package = $event->getComposer()->getPackage();
        // We need the local repository so that we can query and see if it's likely
        // that our files are present there.
        $repository = $event->getComposer()->getRepositoryManager()->getLocalRepository();
        // This is, essentially, a null constraint. We only care whether the package
        // is present in vendor/ yet, but findPackage() requires it.
        $constraint = new Constraint('>', '');
        // Check for our packages, and then optimize them if they're present.
        if ($repository->findPackage('symfony/http-foundation', $constraint)) {
            $autoload = $package->getAutoload();
            $autoload['classmap'] = array_merge($autoload['classmap'], [
                'vendor/symfony/http-foundation/Request.php',
                'vendor/symfony/http-foundation/ParameterBag.php',
                'vendor/symfony/http-foundation/FileBag.php',
                'vendor/symfony/http-foundation/ServerBag.php',
                'vendor/symfony/http-foundation/HeaderBag.php',
            ]);
            $package->setAutoload($autoload);
        }
        if ($repository->findPackage('symfony/http-kernel', $constraint)) {
            $autoload = $package->getAutoload();
            $autoload['classmap'] = array_merge($autoload['classmap'], [
                'vendor/symfony/http-kernel/HttpKernel.php',
                'vendor/symfony/http-kernel/HttpKernelInterface.php',
                'vendor/symfony/http-kernel/TerminableInterface.php',
            ]);
            $package->setAutoload($autoload);
        }
    }

    /**
     * Ensures that .htaccess and web.config files are present in Composer root.
     *
     * @param \Composer\Script\Event $event a Event object to get the configured composer vendor directories from
     */
    public static function ensureHtaccess(Event $event)
    {
        // The current working directory for composer scripts is where you run
        // composer from.
        $vendor_dir = $event->getComposer()->getConfig()->get('vendor-dir');

        // Prevent access to vendor directory on Apache servers.
        $htaccess_file = $vendor_dir.'/.htaccess';
        if (!file_exists($htaccess_file)) {
            $lines = <<<EOT
# Deny all requests from Apache 2.4+.
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>

# Deny all requests from Apache 2.0-2.2.
<IfModule !mod_authz_core.c>
  Deny from all
</IfModule>

# Turn off all options we don't need.
Options -Indexes -ExecCGI -Includes

# Set the catch-all handler to prevent scripts from being executed.
SetHandler Drupal_Security_Do_Not_Remove_See_SA_2006_006
<Files *>
  # Override the handler again if we're run later in the evaluation list.
  SetHandler Drupal_Security_Do_Not_Remove_See_SA_2013_003
</Files>

# If we know how to do it safely, disable the PHP engine entirely.
<IfModule mod_php5.c>
  php_flag engine off
</IfModule>
EOT;
            file_put_contents($htaccess_file, $lines."\n");
        }

        // Prevent access to vendor directory on IIS servers.
        $webconfig_file = $vendor_dir.'/web.config';
        if (!file_exists($webconfig_file)) {
            $lines = <<<EOT
<configuration>
  <system.webServer>
    <authorization>
      <deny users="*">
    </authorization>
  </system.webServer>
</configuration>
EOT;
            file_put_contents($webconfig_file, $lines."\n");
        }
    }

    /**
     * Remove possibly problematic test files from vendored projects.
     *
     * @param \Composer\Installer\PackageEvent $event a PackageEvent object to get the configured composer vendor directories from
     */
    public static function vendorTestCodeCleanup(PackageEvent $event)
    {
        $io = $event->getIO();
        $op = $event->getOperation();
        $installation_manager = $event->getComposer()->getInstallationManager();

        $package = $op->getJobType() == 'update'
            ? $op->getTargetPackage()
            : $op->getPackage();
        $install_path = $installation_manager->getInstallPath($package);

        $message = sprintf('    Processing <comment>%s</comment>', $package->getPrettyName());
        if ($io->isVeryVerbose()) {
            $io->write($message);
        }

        $paths = [];
        if (!preg_match('/^drupal-(core|profile|module|theme)$/', $package->getType())) {
            if (isset(static::$packageToCleanup[$package->getName()])) {
                $finder = new Finder();
                $finder
                    ->directories()
                    ->in($install_path)
                    ->path(static::$packageToCleanup[$package->getName()]);

                foreach ($finder as $file) {
                    $paths[] = $file->getRealpath();
                }
            }
        }

        foreach ($paths as $path) {
            $fs = new Filesystem();
            if ($fs->exists($path)) {
                $fs->remove($path);

                $message = sprintf("      <info>Removing directory '%s'</info>", $path);
                if ($io->isVeryVerbose()) {
                    $io->write($message);
                }
            }
        }

        if ($io->isVeryVerbose()) {
            // Add a new line to separate this output from the next package.
            $io->write('');
        }
    }

    /**
     * Install requirement files.
     */
    public static function installRequirementsFile(Event $event)
    {
        $fs = new Filesystem();
        $root = getcwd().'/web';

        // Prepare .htaccess for installation.
        if ($fs->exists($root.'/.htaccess.example')
            && !$fs->exists($root.'/.htaccess')) {
            $fs->copy(
                $root.'/.htaccess.example',
                $root.'/.htaccess'
            );
            $fs->chmod($root.'/.htaccess', 0644);
            $event->getIO()->write('Create a .htaccess file with chmod 0644');
        }

        // Prepare web.config for installation.
        if ($fs->exists($root.'/web.config.example')
            && !$fs->exists($root.'/web.config')) {
            $fs->copy(
                $root.'/web.config.example',
                $root.'/web.config'
            );
            $fs->chmod($root.'/web.config', 0644);
            $event->getIO()->write('Create a web.config file with chmod 0644');
        }

        // Prepare sites/default/settings.php for installation.
        if ($fs->exists($root.'/sites/default/default.settings.php')
            && !$fs->exists($root.'/sites/default/settings.php')) {
            $fs->copy(
                $root.'/sites/default/default.settings.php',
                $root.'/sites/default/settings.php'
            );
            $fs->chmod($root.'/sites/default/settings.php', 0666);
            $event->getIO()->write('Create a sites/default/settings.php file with chmod 0666');
        }

        // Prepare sites/default/services.yml for installation.
        if ($fs->exists($root.'/sites/default/default.services.yml')
            && !$fs->exists($root.'/sites/default/services.yml')) {
            $fs->copy(
                $root.'/sites/default/default.services.yml',
                $root.'/sites/default/services.yml'
            );
            $fs->chmod($root.'/sites/default/services.yml', 0666);
            $event->getIO()->write('Create a sites/default/services.yml file with chmod 0666');
        }

        // Create the files directory with chmod 0777.
        if (!$fs->exists($root.'/sites/default/files')) {
            $oldmask = umask(0);
            $fs->mkdir($root.'/sites/default/files', 0777);
            umask($oldmask);
            $event->getIO()->write('Create a sites/default/files directory with chmod 0777');
        }
    }

    /**
     * Checks if the installed version of Composer is compatible.
     *
     * Composer 1.0.0 and higher consider a `composer install` without having a
     * lock file present as equal to `composer update`. We do not ship with a lock
     * file to avoid merge conflicts downstream, meaning that if a project is
     * installed with an older version of Composer the scaffolding of Drupal will
     * not be triggered. We check this here instead of in drupal-scaffold to be
     * able to give immediate feedback to the end user, rather than failing the
     * installation after going through the lengthy process of compiling and
     * downloading the Composer dependencies.
     *
     * @see https://github.com/composer/composer/pull/5035
     */
    public static function checkComposerVersion(Event $event)
    {
        $composer = $event->getComposer();
        $io = $event->getIO();

        $version = $composer::VERSION;

        // If Composer is installed through git we have no easy way to determine if
        // it is new enough, just display a warning.
        if ($version === '@package_version@') {
            $io->writeError('<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>');
        } elseif (Comparator::lessThan($version, '1.0.0')) {
            $io->writeError('<error>Drupal-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.');
            exit(1);
        }
    }
}
