# WordPress Migrations  

This package utilizes the [Phinx](https://phinx.org/) library to bring 
traditional migrations to WordPress for use in plugins and themes.  

## Usage  

### Installation  

```
composer require 0x6d617474/wp-migrations
```
### Integrating into your plugin/theme  

```
require_once sprintf('%s/vendor/autoload.php', $root);
$migrator = new Migrator($root, $instance, $hook);
$migrator->setNamespace('Custom\\Namespace\\Migrations');
```

`$root` is the absolute path to the root directory of your package. It should 
be where your `composer.json` file is. 
  
`$instance` is an instance of your plugin/theme object. It is accessible 
from within the migrations via the `$this->getPackage()` method. If you do 
not use an object to contain your plugin/theme, simply pass a `null` value.
  
`$hook` defines the WordPress hook where the migrations will run. The 
recommended values are `plugins_loaded` for plugins and `after_setup_theme` 
for themes. Set this value to `false` to disable automated migrations.  

Defining a namespace for your migrations helps to prevent conflicts with 
other packages utilizing migrations, and can be whatever makes sense for 
your project. By default, the namespace will be `<$instance namespace>\Migrations`.  

### Creating Migrations  

Migrations can be created manually by copying the included template, or 
automatically using the WP-CLI command. The template file is located at 
`vendor/0x6d617474/wp-migrations/src/migration_template.txt`. Files should 
be placed in a `migrations` directory at the root of your package, and named 
like so: `YYYYMMDDHHIISS_Your_Migration_Name`. The datestamp must be unique for 
each migration in your package.  

The included WP-CLI command `wp migrations create <slug> <name>` will create 
a new migration for you with the given name, where `<slug>` is the package 
slug. 

### Running Migrations  

Migrations can be run automatically be defining the `$hook` value for the 
Migrator (see above). Migrations can also be run manually in your code by 
calling the `migrate` method on the `$migrator` object.  

You can also run migrations for an individual package, or all packages 
utilizing the library via the `wp migrations migrate <slug|--all> [--target=target]` 
WP-CLI command. The optional `target` parameter specifies a target to break 
on, otherwise all unapplied migrations will be run.

### Running Rollbacks  

Rollbacks can be run manually in your code by calling the `rollback` method 
on the `$migrator` object.  

You can also run rollbacks for an individual package, or all packages 
utilizing the library via the `wp migrations rollback <slug|--all> [--target=target] [--date=date]` 
WP-CLI command. The optional `target` parameter specifies a target to roll back 
to, otherwise only the latest migration will be rolled back. Similarly, the 
optional `date` parameter specifies a target date to roll back to.

### Checking Status  

You can view the migration status of any or all packages with the `wp migrations status <slug|--all>` 
command. 

### Viewing Applied Migrations  

You can view the applied migrations of any package with the `wp migrations show <slug>` 
command. 

## Notes

**Dealing with failure**  

Migrations that fail to complete will throw an Exception. By default, these 
exceptions are not caught and will halt execution. The reason for this is that 
usually failed migrations are a serious issue and should be dealt with 
immediately (graceful recovery is usually not an option).  

If you'd like to handle exceptions caused by failed migrations, you can 
disable the automated migrate and run the migrations manually inside a 
try/catch block.

**Multisite WordPress**  

Migrations are fully functional in a multisite environment, and migrations are 
applied in isolation for each package for each site.  

If you would like to do a one-time migration for the network, the suggested 
approach is to define a migration that uses a `sitemeta` lock to check if 
work should be done.   

```
public function up()
{
    $lock = '_migration_lock_Blah';
    if (get_site_option($lock, false) === false) {
        // Do the migration
        update_site_option($lock, true)
    }
}

public function down()
{
    $lock = '_migration_lock_Blah';
    if (get_site_option($lock, false) === true) {
        // Do the rollback
        update_site_option($lock, false)
    }
}
``` 

**Composer Isolation**  

This package is compatible with the Composer Isolation package. You will 
likely need to update the `use` statement in your migrations if you apply 
isolation after creating a migration.  

Do to WP-CLI's way of registering commands, the first package to register 
the `migrations` command will have its Command class used by all packages. 
This may eventually lead to incompatibilities during major version upgrades 
of this package, but there's not a lot we can do about it.  

This only affects the WP-CLI commands. The runtime execution outside WP-CLI 
is fully isolated as normal.
