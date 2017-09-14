<?php

namespace Ox6d617474\WordPress\Migrations;

use Phinx\Config\Config;
use Phinx\Db\Adapter\AdapterFactory;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use WP_CLI;

final class Migrator
{
    /**
     * Absolute path to the package root
     *
     * @var string
     */
    private $root;

    /**
     * Package slug
     *
     * @var string
     */
    private $slug;

    /**
     * Package instance
     *
     * @var object
     */
    private $package;

    /**
     * Package type
     *
     * @var string
     */
    private $type;

    /**
     * Namespace for the migrations
     *
     * @var string
     */
    private $namespace;

    /**
     * Class constructor
     *
     * @param string $root    - Absolute path to the package root
     * @param object $package - Package instance
     * @param string $autorun - Autorun hook
     */
    public function __construct($root, $package, $autorun = false)
    {
        // Save references
        $this->root = $root;
        $this->slug = basename($root);
        $this->package = $package;
        $this->type = preg_match('/^' . preg_quote(WP_PLUGIN_DIR, '/') . '/', $root) ? 'plugin' : 'theme';

        // By default, the migration namespace is the package namespace suffixed with Migrations
        $namespace = implode('\\', array_slice(explode('\\', get_class($package)), 0, -1));
        $this->namespace = sprintf('%s\\Migrations', $namespace);

        // Set up the autorun hook if enabled
        if ($autorun !== false) {
            add_action($autorun, function() {
                // Never run migrations automatically on ajax, cron, or cli requests
                if ((defined('DOING_AJAX') && DOING_AJAX) || (defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI)) {
                    return;
                }

                $this->migrate();
            });
        }

        // Set up the wp-cli command
        if (defined('WP_CLI') && WP_CLI) {
            /** @var \WP_CLI\Dispatcher\CompositeCommand[] $commands */
            $commands = WP_CLI::get_root_command()->get_subcommands();
            if (isset($commands['migrations'])) {
                /*
                 * The command is already registered
                 *
                 * It may be registered by another instance of this library,
                 * or it might be a conflicting library or package.
                 *
                 * Check to see if the bound class has the Ox6d617474 constant.
                 * The FQN of the class may be different if the package that
                 * registered the command is using composer isolation.
                 *
                 */
                try {
                    /** @var \WP_CLI\Dispatcher\Subcommand[] $subcommands */
                    $subcommands = array_values($commands['migrations']->get_subcommands());
                    if (empty($subcommands[0])) {
                        // No subcommands means it's not us
                        throw new \Exception();
                    }

                    $class = new \ReflectionClass($subcommands[0]);
                    $invoke = $class->getProperty('when_invoked');
                    if (empty($invoke)) {
                        // No when_invoked property shouldn't happen, but bail if it does
                        throw new \Exception();
                    }

                    $invoke->setAccessible(true);
                    $closure = new \ReflectionFunction($invoke->getValue($subcommands[0]));
                    $static = $closure->getStaticVariables();
                    if (empty($static['callable'][0])) {
                        // Unable to find the bound class
                        throw new \Exception();
                    }

                    if (!defined(sprintf('%s::Ox6d617474', $static['callable'][0]))) {
                        // Bound class is missing the Ox6d617474 constant, so not us
                        throw new \Exception();
                    }

                    // We made it! Use the bound class
                    $classname = $static['callable'][0];
                } catch (\Exception $e) {
                    // There is a conflicting migration command
                    WP_CLI::warning('Conflicting migration command, registering \'0x6d617474 migrations\' command...');
                    WP_CLI::add_command('0x6d617474 migrations', Command::class);
                    $classname = Command::class;
                }
            } else {
                // Command not registered, so register it now
                WP_CLI::add_command('migrations', Command::class);
                $classname = Command::class;
            }

            call_user_func([$classname, 'register'], $this);
        }
    }

    /**
     * Namespace mutator
     * 
     * @param string $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * Perform migrations
     *
     * @return mixed
     */
    public function migrate($target = null)
    {
        return $this->execute('migrate', ['target' => $target]);
    }

    /**
     * Rollback migrations
     *
     * @return mixed
     */
    public function rollback($target = null, $date = null)
    {
        return $this->execute('rollback', ['target' => $target, 'date' => $date]);
    }

    /**
     * Check migration status
     *
     * @return mixed
     */
    public function status()
    {
        return $this->execute('status');
    }

    /**
     * Get the latest applied migration
     *
     * @return mixed
     */
    public function current()
    {
        return $this->execute('current');
    }

    /**
     * Get the list of applied versions
     *
     * @return mixed
     */
    public function versions()
    {
        return $this->execute('versions');
    }

    /**
     * Package root accessor
     *
     * @return string
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * Package slug accessor
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Package instance accessor
     *
     * @return object
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * Namespace accessor
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Package type accessor
     *
     * @return string
     */
    public function getPackageType()
    {
        return $this->type;
    }

    /**
     * Execute a given command in the Phinx application in the WordPress environment
     *
     * @param string $command - Command to execute
     * @param array  $args    - Arguments
     *
     * @return mixed
     */
    private function execute($command, $args = [])
    {
        $config = $this->generateConfig();

        if (!in_array($command, ['current', 'versions'])) {
            $app = new Application();
            $app->setAutoExit(false);

            $factory = AdapterFactory::instance();
            $factory->registerAdapter('wordpress', WordPressAdapter::class);
            $factory->registerWrapper('wordpress', WordPressWrapper::class);

            $runtimeArgs = ['phinx', $command, '-ewordpress', '-q'];
            foreach ($args as $arg => $value) {
                if ($value !== null) {
                    $runtimeArgs[] = sprintf('--%s=%s', $arg, $value);
                }
            }
            unset($args);

            /** @var \Phinx\Console\Command\AbstractCommand $cmd */
            $cmd = $app->get($command);
            $cmd->setConfig(new Config($config));
            $output = new BufferedOutput();
            $exit = $app->run(new ArgvInput($runtimeArgs), $output);

            // For the status command, non-zero exit code means available migrations
            if (strtolower($command) == 'status') {
                return $exit;
            }

            // Non-zero for all other commands means failure
            if ($exit != 0) {
                $log = trim($output->fetch());
                $log = implode("\n", array_map('trim', array_slice(explode("\n", $log), 0, -1)));
                $log = str_replace("\n", '', $log);
                $log = trim(preg_replace('/\[.+?\]/', "\n", $log));

                throw new \RuntimeException(sprintf('%s did not complete successfully: %s', $command, $log));
            }
        }

        $adapter = new WordPressAdapter(array_merge(
            $config['environments']['wordpress'],
            ['default_migration_table' => $config['environments']['default_migration_table']]
        ));

        $versions = $adapter->getVersions();
        if ($command === 'versions') {
            return $versions;
        }

        if (empty($versions)) {
            return 'NULL';
        }

        return $versions[count($versions) - 1];
    }

    /**
     * Generate a configuration for Phinx
     *
     * This has to be created at runtime because the contents are dynamic
     * to the WordPress instance and registered package
     *
     * @return array
     */
    private function generateConfig()
    {
        // Split WordPress DB host into host and port
        $host = preg_replace('/:[0-9]+$/', '', DB_HOST); // Remove port
        $port = preg_replace('/^.*:([0-9]+)$/', '$1', DB_HOST); // Isolate port

        return [
            'paths' => [
                'migrations' => [$this->namespace => sprintf('%s/migrations', $this->root)],
                'seeds' => sprintf('%s/seeds', $this->root),
            ],
            'environments' => [
                'default_migration_table' => 'package_migrations',
                'wordpress' => [
                    'host'      => $host,
                    'port'      => $port,
                    'user'      => DB_USER,
                    'pass'      => DB_PASSWORD,
                    'adapter'   => 'wordpress',
                    'wrapper'   => 'wordpress',
                    'name'      => DB_NAME,

                    // Custom
                    'package'   => $this->package,
                    'pkgtype'   => $this->type,
                    'slug'      => $this->slug,
                ],
            ],
        ];
    }
}
