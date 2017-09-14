<?php

namespace Ox6d617474\WordPress\Migrations;

use WP_CLI;
use WP_CLI_Command;

/**
 * Interface with WordPress Migrations
 */
final class Command extends WP_CLI_Command
{
    /**
     * Constant to identify this class as a part of the library
     */
    const Ox6d617474 = true;

    /**
     * Singleton instance
     *
     * @var self
     */
    private static $instance;

    /**
     * Registered migrators
     *
     * @var Migrator[]
     */
    private static $migrators = [];

    /**
     * Class constructor
     */
    public function __construct()
    {
        if (empty(self::$instance)) {
            self::$instance = $this;
        }
    }

    /**
     * Register a migrator
     *
     * @param Migrator $migrator
     */
    public static function register($migrator)
    {
        $slug = strtolower($migrator->getSlug());
        if (isset(self::$migrators[$slug])) {
            WP_CLI::warning(sprintf('%s has already been registered. Overriding!', $slug));
        }

        self::$migrators[$slug] = $migrator;
    }

    /**
     * Display the list of registered packages
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     *
     * @subcommand list
     */
    public function showList($args, $assoc_args)
    {
        $format = empty($assoc_args['format']) ? 'table' : trim($assoc_args['format']);
        switch ($format) {
            case 'table':
            case 'json':
            case 'csv':
            case 'yaml':
            case 'count':
                break;
            default:
                WP_CLI::error('Invalid format');
        }

        $items = [];
        foreach (self::$migrators as $slug => $migrator) {
            $status = $this->getStatus($slug);
            if ($status !== false) {
                $items[] = [
                    'slug' => $slug,
                    'type' => $migrator->getPackageType(),
                    'status' => $status,
                    'current' => $migrator->current(),
                ];
            }
        }

        $fields = [
            'slug',
            'type',
            'status',
            'current',
        ];

        WP_CLI\Utils\format_items($format, $items, $fields);
    }

    /**
     * Display the applied migrations
     *
     * ## OPTIONS
     *
     * [<slug>...]
     * : The slug of the package to show
     *
     * [--format=<format>]
     *
     * @subcommand show
     */
    public function show($args, $assoc_args)
    {
        $format = empty($assoc_args['format']) ? 'table' : trim($assoc_args['format']);
        switch ($format) {
            case 'table':
            case 'json':
            case 'csv':
            case 'yaml':
            case 'count':
                break;
            default:
                WP_CLI::error('Invalid format');
        }

        $items = [];

        foreach ($args as $slug) {
            $versions = $this->getVersions($slug);
            if ($versions !== false) {
                $items[] = [
                    'slug' => $slug,
                    'versions' => $versions,
                ];
            }
        }

        $fields = [
            'slug',
            'versions',
        ];

        WP_CLI\Utils\format_items($format, $items, $fields);
    }

    /**
     * Check migration status.
     *
     * ## OPTIONS
     *
     * [<slug>...]
     * : The slug of the package to check
     *
     * [--all]
     * : Run checks for all packages
     *
     * @subcommand status
     */
    public function status($args, $assoc_args)
    {
        $migrate_all = WP_CLI\Utils\get_flag_value($assoc_args, 'all');
        if ($migrate_all) {
            foreach (self::$migrators as $slug => $migrator) {
                $status = $this->getStatus($slug);
                if ($status !== false) {
                    WP_CLI::log(sprintf('%s : %s', $slug, $status));
                }
            }

            return;
        }

        if (empty($args)) {
            WP_CLI::error('Please specify one or more packages, or use --all.');
        }

        foreach ($args as $slug) {
            $status = $this->getStatus($slug);
            if ($status !== false) {
                WP_CLI::log(sprintf('%s : %s', $slug, $status));
            }
        }
    }

    /**
     * Perform migrations.
     *
     * ## OPTIONS
     *
     * [<slug>...]
     * : The slug of the package to migrate
     *
     * [--all]
     * : Run migrations for all packages
     *
     * [--target=<target>]
     * : Run migrations until target
     *
     * @subcommand migrate
     */
    public function migrate($args, $assoc_args)
    {
        $target = isset($assoc_args['target']) ? $assoc_args['target'] : null;

        $migrate_all = WP_CLI\Utils\get_flag_value($assoc_args, 'all');
        if ($migrate_all) {
            foreach (self::$migrators as $slug => $migrator) {
                $version = $this->performMigration($slug, $target);
                if ($version === true) {
                    WP_CLI::success(sprintf('%s already up-to-date', $slug));
                } elseif ($version === false) {
                    // Do nothing
                } else {
                    WP_CLI::success(sprintf('%s migrated to %s', $slug, $version));
                }
            }

            WP_CLI::success('Migrations complete');

            return;
        }

        if (empty($args)) {
            WP_CLI::error('Please specify one or more packages, or use --all.');
        }

        foreach ($args as $slug) {
            $version = $this->performMigration($slug, $target);
            if ($version === true) {
                WP_CLI::success(sprintf('%s already up-to-date', $slug));
            } elseif ($version === false) {
                // Do nothing
            } else {
                WP_CLI::success(sprintf('%s migrated to %s', $slug, $version));
            }
        }
    }

    /**
     * Perform rollbacks.
     *
     * ## OPTIONS
     *
     * [<slug>...]
     * : The slug of the package to rollback
     *
     * [--all]
     * : Run rollbacks for all packages
     *
     * [--target=<target>]
     * : Run rollbacks until target
     *
     * [--date=<date>]
     * : Run rollbacks until date
     *
     * @subcommand rollback
     */
    public function rollback($args, $assoc_args)
    {
        $target = isset($assoc_args['target']) ? $assoc_args['target'] : null;
        $date = isset($assoc_args['date']) ? $assoc_args['date'] : null;

        $migrate_all = WP_CLI\Utils\get_flag_value($assoc_args, 'all');
        if ($migrate_all) {
            foreach (self::$migrators as $slug => $migrator) {
                $version = $this->performRollback($slug, $target, $date);
                if ($version !== false) {
                    WP_CLI::success(sprintf('%s rolled back to %s', $slug, $version));
                }
            }

            WP_CLI::success('Rollbacks complete');

            return;
        }

        if (empty($args)) {
            WP_CLI::error('Please specify one or more packages, or use --all.');
        }

        foreach ($args as $slug) {
            $version = $this->performRollback($slug, $target, $date);
            if ($version !== false) {
                WP_CLI::success(sprintf('%s rolled back to %s', $slug, $version));
            }
        }
    }

    /**
     * Create a new migration for a package
     *
     * ## OPTIONS
     *
     * <slug>
     * : The slug of the package to create a migration for
     *
     * <name>
     * : The name of the migration
     */
    public function create($args, $assoc_args)
    {
        list($slug, $name) = $args;

        if (!isset(self::$migrators[$slug])) {
            WP_CLI::error(sprintf('%s is not registered to migrate', $slug));
        }

        $migrator = self::$migrators[$slug];

        $migrations_path = sprintf('%s/migrations', $migrator->getRoot());
        if (!file_exists($migrations_path)) {
            mkdir($migrations_path);
        }

        $name = str_replace('-', '_', $name);

        $filename = sprintf('%s_%s.php', date('YmdHis'), $name);
        $classname = implode('', array_map('ucwords', explode('_', $name)));

        $template = file_get_contents(sprintf('%s/migration_template.txt', __DIR__));
        $template = str_replace('{{class}}', $classname, $template);
        $template = str_replace('{{namespace}}', $migrator->getNamespace(), $template);
        $template = str_replace('{{vendor}}', __NAMESPACE__, $template);

        file_put_contents(sprintf('%s/%s', $migrations_path, $filename), $template);

        WP_CLI::success('Migration file create successfully');
    }

    /**
     * Get the status string for a package
     *
     * @param string $slug
     *
     * @return bool|string
     */
    private function getStatus($slug)
    {
        $slug = strtolower($slug);
        if (isset(self::$migrators[$slug])) {
            $migrator = self::$migrators[$slug];
            try {
                $code = $migrator->status();
                switch ($code) {
                    case 0:
                        $status = 'up-to-date';
                        break;
                    case 1:
                    case 2:
                        $status = 'migrations available';
                        break;
                    default:
                        $status = 'unknown';
                }

                return $status;
            } catch (\Exception $ex) {
                WP_CLI::warning(sprintf('%s encountered an error during status check: %s', $slug, $ex->getMessage()));

                return false;
            }
        }

        WP_CLI::warning(sprintf('%s is not registered to migrate', $slug));

        return false;
    }

    /**
     * Get the list of versions for a package
     *
     * @param string $slug
     *
     * @return bool|array
     */
    private function getVersions($slug)
    {
        $slug = strtolower($slug);
        if (isset(self::$migrators[$slug])) {
            $migrator = self::$migrators[$slug];
            try {
                $versions = $migrator->versions();

                return $versions;
            } catch (\Exception $ex) {
                WP_CLI::warning(sprintf('%s encountered an error during version check: %s', $slug, $ex->getMessage()));

                return false;
            }
        }

        WP_CLI::warning(sprintf('%s is not registered to migrate', $slug));

        return false;
    }

    /**
     * Perform a migration for a package
     *
     * @param string $slug
     * @param int    $target
     *
     * @return bool|string
     */
    private function performMigration($slug, $target = null)
    {
        $slug = strtolower($slug);
        if (isset(self::$migrators[$slug])) {
            $migrator = self::$migrators[$slug];
            try {
                $code = $migrator->status();
                if ($code === 0) {
                    return true;
                }
                $version = $migrator->migrate($target);

                return $version;
            } catch (\Exception $ex) {
                WP_CLI::warning(sprintf('%s encountered an error during migration: %s', $slug, $ex->getMessage()));

                return false;
            }
        }

        WP_CLI::warning(sprintf('%s is not registered to migrate', $slug));

        return false;
    }

    /**
     * Perform a rollback for a package
     *
     * @param string $slug
     * @param int    $target
     * @param int    $date
     *
     * @return bool|string
     */
    private function performRollback($slug, $target = null, $date = null)
    {
        $slug = strtolower($slug);
        if (isset(self::$migrators[$slug])) {
            $migrator = self::$migrators[$slug];
            try {
                $version = $migrator->rollback($target, $date);

                return $version;
            } catch (\Exception $ex) {
                WP_CLI::warning(sprintf('%s encountered an error during rollback: %s', $slug, $ex->getMessage()));

                return false;
            }
        }

        WP_CLI::warning(sprintf('%s is not registered to migrate', $slug));

        return false;
    }
}
