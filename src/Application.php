<?php

namespace Ox6d617474\WordPress\Migrations;

use Phinx\Console\Command;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public function __construct($version = '1.0.0')
    {
        parent::__construct('WordPress Migrations', $version);

        $this->addCommands([
            new Command\Migrate,
            new Command\Rollback,
            new Command\Status,
        ]);
    }
}
