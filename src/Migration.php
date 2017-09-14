<?php

namespace Ox6d617474\WordPress\Migrations;

use Phinx\Migration\AbstractMigration;

abstract class Migration extends AbstractMigration
{
    /**
     * @var WordPressAdapter
     */
    protected $adapter;

    final protected function getPackage()
    {
        return $this->adapter->getPackage();
    }

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        // Implementation
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        // Implementation
    }

    /**
     * {@inheritdoc}
     */
    final public function getName()
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}
