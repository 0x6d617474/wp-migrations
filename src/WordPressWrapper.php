<?php

namespace Ox6d617474\WordPress\Migrations;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\AdapterWrapper;
use Phinx\Db\Adapter\TimedOutputAdapter;

final class WordPressWrapper extends AdapterWrapper
{
    /**
     * @var WordPressAdapter
     */
    protected $adapter;

    /**
     * {@inheritdoc}
     */
    public function getAdapterType()
    {
        return $this->getAdapter()->getAdapterType();
    }

    /**
     * {@inheritdoc}
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        if ($adapter instanceof TimedOutputAdapter) {
            $adapter = $adapter->getAdapter();
        }

        return parent::setAdapter($adapter);
    }

    /**
     * Gets the database adapter.
     *
     * @throws \RuntimeException if the adapter has not been set
     *
     * @return WordPressAdapter
     */
    public function getAdapter()
    {
        return parent::getAdapter();
    }

    /**
     * Gets the package instance
     *
     * @return object
     */
    public function getPackage()
    {
        return $this->getAdapter()->getPackage();
    }
}
