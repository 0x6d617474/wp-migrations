<?php

namespace Ox6d617474\WordPress\Migrations;

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Table;
use Phinx\Migration\MigrationInterface;

final class WordPressAdapter extends MysqlAdapter
{
    /**
     * WordPress package instance
     *
     * @var object
     */
    private $package = null;

    /**
     * WordPress package slug
     *
     * @var string
     */
    private $slug = null;

    /**
     * WordPress package type
     *
     * @var string
     */
    private $pkgtype = null;

    /**
     * Gets the package instance
     *
     * @return object
     */
    public function getPackage()
    {
        return $this->package;
    }

    /**
     * {@inheritdoc}
     */
    public function setSchemaTableName($schemaTableName)
    {
        global $table_prefix;

        $this->schemaTableName = sprintf('%s%s', $table_prefix, $schemaTableName);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        if (isset($options['package'])) {
            $this->package = $options['package'];
        }

        if (isset($options['pkgtype'])) {
            $this->pkgtype = $options['pkgtype'];
        }

        if (isset($options['slug'])) {
            $this->slug = $options['slug'];
        }

        return parent::setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function createSchemaTable()
    {
        try {
            $options = [
                'id' => true,
            ];

            $table = new Table($this->getSchemaTableName(), $options, $this);
            $drivername = $this->getConnection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $version = $this->getConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION);

            if ($drivername === 'mysql' && version_compare($version, '5.6.0', '>=')) {
                $table
                    ->addColumn('version', 'biginteger', ['limit' => 14])
                    ->addColumn('migration_name', 'string', ['limit' => 100, 'default' => null, 'null' => true])
                    ->addColumn('package_slug', 'string', ['limit' => 100, 'default' => null, 'null' => true])
                    ->addColumn('package_type', 'string', ['limit' => 10, 'default' => null, 'null' => true])
                    ->addColumn('start_time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                    ->addColumn('end_time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                    ->addColumn('breakpoint', 'boolean', ['default' => false])
                    ->save();
            } else {
                $table
                    ->addColumn('version', 'biginteger')
                    ->addColumn('migration_name', 'string', ['limit' => 100, 'default' => null, 'null' => true])
                    ->addColumn('package_slug', 'string', ['limit' => 100, 'default' => null, 'null' => true])
                    ->addColumn('package_type', 'string', ['limit' => 10, 'default' => null, 'null' => true])
                    ->addColumn('start_time', 'timestamp')
                    ->addColumn('end_time', 'timestamp')
                    ->addColumn('breakpoint', 'boolean', ['default' => false])
                    ->save();
            }
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException('There was a problem creating the schema table: ' . $exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        if (strcasecmp($direction, MigrationInterface::UP) === 0) {
            // up
            $sql = sprintf(
                "INSERT INTO %s (%s, %s, %s, %s, %s, %s, %s) VALUES ('%s', '%s', '%s', '%s', %s, '%s', '%s');",
                $this->getSchemaTableName(),
                $this->quoteColumnName('version'),
                $this->quoteColumnName('migration_name'),
                $this->quoteColumnName('start_time'),
                $this->quoteColumnName('end_time'),
                $this->quoteColumnName('breakpoint'),
                $this->quoteColumnName('package_slug'),
                $this->quoteColumnName('package_type'),
                $migration->getVersion(),
                substr($migration->getName(), 0, 100),
                $startTime,
                $endTime,
                $this->castToBool(false),
                $this->slug,
                $this->pkgtype
            );

            $this->query($sql);
        } else {
            // down
            $sql = sprintf(
                "DELETE FROM %s WHERE %s = '%s' AND %s = '%s' AND %s = '%s' LIMIT 1",
                $this->getSchemaTableName(),
                $this->quoteColumnName('version'),
                $migration->getVersion(),
                $this->quoteColumnName('package_slug'),
                $this->slug,
                $this->quoteColumnName('package_type'),
                $this->pkgtype
            );

            $this->query($sql);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersionLog()
    {
        $result = [];
        $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY version ASC', $this->getSchemaTableName()));
        foreach ($rows as $version) {
            if ($version['package_slug'] !== $this->slug || $version['package_type'] !== $this->pkgtype) {
                continue;
            }
            $result[$version['version']] = $version;
        }

        return $result;
    }

    /**
     * Uninstall the package's migrations
     */
    public function uninstall()
    {
        $sql = sprintf(
            "DELETE FROM %s WHERE %s = '%s' AND %s = '%s'",
            $this->getSchemaTableName(),
            $this->quoteColumnName('package_slug'),
            $this->slug,
            $this->quoteColumnName('package_type'),
            $this->pkgtype
        );

        $this->query($sql);
    }
}
