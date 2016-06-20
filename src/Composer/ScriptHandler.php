<?php

namespace DruStack\Standard\Composer;

use Composer\Installer\PackageEvent;
use Composer\Script\Event;
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
        'behat/mink' => ['tests', 'driver-testsuite'],
        'behat/mink-browserkit-driver' => ['tests'],
        'behat/mink-goutte-driver' => ['tests'],
        'doctrine/cache' => ['tests'],
        'doctrine/collections' => ['tests'],
        'doctrine/common' => ['tests'],
        'doctrine/inflector' => ['tests'],
        'doctrine/instantiator' => ['tests'],
        'egulias/email-validator' => ['documentation', 'tests'],
        'fabpot/goutte' => ['Goutte/Tests'],
        'guzzlehttp/promises' => ['tests'],
        'guzzlehttp/psr7' => ['tests'],
        'jcalderonzumba/gastonjs' => ['docs', 'examples', 'tests'],
        'jcalderonzumba/mink-phantomjs-driver' => ['tests'],
        'masterminds/html5' => ['test'],
        'mikey179/vfsStream' => ['src/test'],
        'paragonie/random_compat' => ['tests'],
        'phpdocumentor/reflection-docblock' => ['tests'],
        'phpunit/php-code-coverage' => ['tests'],
        'phpunit/php-timer' => ['tests'],
        'phpunit/php-token-stream' => ['tests'],
        'phpunit/phpunit' => ['tests'],
        'phpunit/php-mock-objects' => ['tests'],
        'sebastian/comparator' => ['tests'],
        'sebastian/diff' => ['tests'],
        'sebastian/environment' => ['tests'],
        'sebastian/exporter' => ['tests'],
        'sebastian/global-state' => ['tests'],
        'sebastian/recursion-context' => ['tests'],
        'stack/builder' => ['tests'],
        'symfony/browser-kit' => ['Tests'],
        'symfony/class-loader' => ['Tests'],
        'symfony/console' => ['Tests'],
        'symfony/css-selector' => ['Tests'],
        'symfony/debug' => ['Tests'],
        'symfony/dependency-injection' => ['Tests'],
        'symfony/dom-crawler' => ['Tests'],
        // @see \Drupal\Tests\Component\EventDispatcher\ContainerAwareEventDispatcherTest
        // 'symfony/event-dispatcher' => ['Tests'],
        'symfony/http-foundation' => ['Tests'],
        'symfony/http-kernel' => ['Tests'],
        'symfony/process' => ['Tests'],
        'symfony/psr-http-message-bridge' => ['Tests'],
        'symfony/routing' => ['Tests'],
        'symfony/serializer' => ['Tests'],
        'symfony/translation' => ['Tests'],
        'symfony/validator' => ['Tests', 'Resources'],
        'symfony/yaml' => ['Tests'],
        'symfony-cmf/routing' => ['Test', 'Tests'],
        'twig/twig' => ['doc', 'ext', 'test'],
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
     * @param \Composer\Script\Event $event
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
     * @param \Composer\Installer\PackageEvent $event A PackageEvent object to get the configured composer vendor directories from.
     */
    public static function vendorTestCodeCleanup(PackageEvent $event)
    {
        $vendor_dir = $event->getComposer()->getConfig()->get('vendor-dir');
        $io = $event->getIO();
        $op = $event->getOperation();
        if ($op->getJobType() == 'update') {
            $package = $op->getTargetPackage();
        } else {
            $package = $op->getPackage();
        }
        $package_key = static::findPackageKey($package->getName());
        $message = sprintf('    Processing <comment>%s</comment>', $package->getPrettyName());
        if ($io->isVeryVerbose()) {
            $io->write($message);
        }
        if ($package_key) {
            foreach (static::$packageToCleanup[$package_key] as $path) {
                $dir_to_remove = $vendor_dir.'/'.$package_key.'/'.$path;
                $print_message = $io->isVeryVerbose();
                if (is_dir($dir_to_remove)) {
                    if (static::deleteRecursive($dir_to_remove)) {
                        $message = sprintf("      <info>Removing directory '%s'</info>", $path);
                    } else {
                        // Always display a message if this fails as it means something has
                        // gone wrong. Therefore the message has to include the package name
                        // as the first informational message might not exist.
                        $print_message = true;
                        $message = sprintf("      <error>Failure removing directory '%s'</error> in package <comment>%s</comment>.", $path, $package->getPrettyName());
                    }
                } else {
                    // If the package has changed or the --prefer-dist version does not
                    // include the directory this is not an error.
                    $message = sprintf("      Directory '%s' does not exist", $path);
                }
                if ($print_message) {
                    $io->write($message);
                }
            }

            if ($io->isVeryVerbose()) {
                // Add a new line to separate this output from the next package.
                $io->write('');
            }
        }
    }

    /**
     * Install requirement files.
     */
    public static function installRequirementsFile(Event $event)
    {
        $fs = new Filesystem();
        $root = getcwd().'/web';

        // Prepare the settings file for installation
        if ($fs->exists($root.'/sites/default/default.settings.php')
            && !$fs->exists($root.'/sites/default/settings.php')) {
            $fs->copy(
                $root.'/sites/default/default.settings.php',
                $root.'/sites/default/settings.php'
            );
            $fs->chmod($root.'/sites/default/settings.php', 0666);
            $event->getIO()->write('Create a sites/default/settings.php file with chmod 0666');
        }

        // Prepare the services file for installation
        if ($fs->exists($root.'/sites/default/default.services.yml')
            && !$fs->exists($root.'/sites/default/services.yml')) {
            $fs->copy(
                $root.'/sites/default/default.services.yml',
                $root.'/sites/default/services.yml'
            );
            $fs->chmod($root.'/sites/default/services.yml', 0666);
            $event->getIO()->write('Create a sites/default/services.yml file with chmod 0666');
        }

        // Create the files directory with chmod 0777
        if (!$fs->exists($root.'/sites/default/files')) {
            $oldmask = umask(0);
            $fs->mkdir($root.'/sites/default/files', 0777);
            umask($oldmask);
            $event->getIO()->write('Create a sites/default/files directory with chmod 0777');
        }
    }

    /**
     * Inject metadata into all .info files for a given project.
     *
     * @see drush_pm_inject_info_file_metadata()
     */
    public static function generateInfoMetadata(PackageEvent $event)
    {
        $op = $event->getOperation();
        $installation_manager = $event->getComposer()->getInstallationManager();

        $package = $op->getJobType() == 'update'
            ? $op->getTargetPackage()
            : $op->getPackage();
        $install_path = $installation_manager->getInstallPath($package);

        if (in_array($package->getType(), ['drupal-profile', 'drupal-module', 'drupal-theme'])) {
            $project = preg_replace('/^.*\//', '', $package->getName());
            $version = preg_replace(
                ['/^dev-(.*)/', '/^([0-9]*)\.([0-9]*\.[0-9]*)/'],
                ['$1-dev', '$1.x-$2'],
                $package->getPrettyVersion()
            );
            $branch = preg_replace('/^([0-9]*\.x-[0-9]*).*$/', '$1', $version);
            $datestamp = preg_match('/-dev$/', $version)
                ? time()
                : $package->getReleaseDate()->getTimestamp();

            // Compute the rebuild version string for a project.
            $version = static::computeRebuildVersion($install_path, $branch) ?: $version;

            // Generate version information for `.info` files in ini format.
            $finder = new Finder();
            $finder->in($install_path)
                ->files()
                ->name('*.info')
                ->notContains('datestamp =');
            foreach ($finder as $file) {
                file_put_contents(
                    $file->getRealpath(),
                    static::generateInfoIniMetadata($version, $project, $datestamp),
                    FILE_APPEND
                );
            }

            // Generate version information for `.info.yml` files in YAML format.
            $finder = new Finder();
            $finder->in($install_path)
                ->files()
                ->name('*.info.yml')
                ->notContains('datestamp :');
            foreach ($finder as $file) {
                file_put_contents(
                    $file->getRealpath(),
                    static::generateInfoYamlMetadata($version, $project, $datestamp),
                    FILE_APPEND
                );
            }
        }
    }

    /**
     * Find the array key for a given package name with a case-insensitive search.
     *
     * @param string $package_name The package name from composer. This is always already lower case.
     *
     * @return null|string The string key, or NULL if none was found.
     */
    protected static function findPackageKey($package_name)
    {
        $package_key = null;
        // In most cases the package name is already used as the array key.
        if (isset(static::$packageToCleanup[$package_name])) {
            $package_key = $package_name;
        } else {
            // Handle any mismatch in case between the package name and array key.
            // For example, the array key 'mikey179/vfsStream' needs to be found
            // when composer returns a package name of 'mikey179/vfsstream'.
            foreach (static::$packageToCleanup as $key => $dirs) {
                if (strtolower($key) === $package_name) {
                    $package_key = $key;
                    break;
                }
            }
        }

        return $package_key;
    }

    /**
     * Helper method to remove directories and the files they contain.
     *
     * @param string $path The directory or file to remove. It must exist.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    protected static function deleteRecursive($path)
    {
        if (is_file($path) || is_link($path)) {
            return unlink($path);
        }
        $success = true;
        $dir = dir($path);
        while (($entry = $dir->read()) !== false) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            $entry_path = $path.'/'.$entry;
            $success = static::deleteRecursive($entry_path) && $success;
        }
        $dir->close();

        return rmdir($path) && $success;
    }

    /**
     * Helper function to compute the rebulid version string for a project.
     *
     * This does some magic in Git to find the latest release tag along
     * the branch we're packaging from, count the number of commits since
     * then, and use that to construct this fancy alternate version string
     * which is useful for the version-specific dependency support in Drupal
     * 7 and higher.
     *
     * NOTE: A similar function lives in git_deploy and in the drupal.org
     * packaging script (see DrupalorgProjectPackageRelease.class.php inside
     * drupalorg/drupalorg_project/plugins/release_packager). Any changes to the
     * actual logic in here should probably be reflected in the other places.
     *
     * @see drush_pm_git_drupalorg_compute_rebuild_version()
     */
    protected static function computeRebuildVersion($install_path, $branch)
    {
        $version = '';
        $branch_preg = preg_quote($branch);

        $process = new Process("cd $install_path; git describe --tags");
        $process->run();
        if ($process->isSuccessful()) {
            $last_tag = strtok($process->getOutput(), "\n");
            // Make sure the tag starts as Drupal formatted (for eg.
            // 7.x-1.0-alpha1) and if we are on a proper branch (ie. not master)
            // then it's on that branch.
            if (preg_match('/^(?<drupalversion>'.$branch_preg.'\.\d+(?:-[^-]+)?)(?<gitextra>-(?<numberofcommits>\d+-)g[0-9a-f]{7})?$/', $last_tag, $matches)) {
                if (isset($matches['gitextra'])) {
                    // If we found additional git metadata (in particular, number of commits)
                    // then use that info to build the version string.
                    $version = $matches['drupalversion'].'+'.$matches['numberofcommits'].'dev';
                } else {
                    // Otherwise, the branch tip is pointing to the same commit as the
                    // last tag on the branch, in which case we use the prior tag and
                    // add '+0-dev' to indicate we're still on a -dev branch.
                    $version = $last_tag.'+0-dev';
                }
            }
        }

        return $version;
    }

    /**
     * Generate version information for `.info` files in ini format.
     *
     * @see _drush_pm_generate_info_ini_metadata()
     */
    protected static function generateInfoIniMetadata($version, $project, $datestamp)
    {
        $core = preg_replace('/^([0-9]).*$/', '$1.x', $version);
        $date = date('Y-m-d', $datestamp);
        $info = <<<METADATA

; Information add by composer on {$date}
core = "{$core}"
project = "{$project}"
version = "{$version}"
datestamp = "{$datestamp}"
METADATA;

        return $info;
    }

    /**
     * Generate version information for `.info.yml` files in YAML format.
     *
     * @see _drush_pm_generate_info_yaml_metadata()
     */
    protected static function generateInfoYamlMetadata($version, $project, $datestamp)
    {
        $core = preg_replace('/^([0-9]).*$/', '$1.x', $version);
        $date = date('Y-m-d', $datestamp);
        $info = <<<METADATA

# Information add by composer on {$date}
core: "{$core}"
project: "{$project}"
version: "{$version}"
datestamp: "{$datestamp}"
METADATA;

        return $info;
    }
}
