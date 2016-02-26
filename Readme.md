# Sledgehammer Core

[![Build Status](https://travis-ci.org/sledgehammer/core.svg)](https://travis-ci.org/sledgehammer/core)

A general purpose PHP toolkit, with a focus on debugging.

- Improved error reporting with Sledgehammer\Core\Debug\ErrorHandler.
- Improved var_dump() with dump().
- Improved PDO compatible database connection with Sledgehammer\Core\Database\Connection.

## Resources

* [API Documentation](http://sledgehammer.github.com/api/)
* [Sledgehammer Wiki](http://github.com/sledgehammer/sledgehammer/wiki)
* [Issue tracker](https://github.com/sledgehammer/sledgehammer/issues)
* [Roadmap / Backlog](https://trello.com/board/sledgehammer-framework/4ec77591eb9c5577726d94fb)

## Scope

* Debugging, error-reporting, loggin and profiling functionality.
* A collection of global functions (that should be included in PHP, imho)
* Generic utility classes 

## Classes

* [Autoloader](http://sledgehammer.github.com/api/class-Sledgehammer.Autoloader.html)    : Detects classes and interfaces in any php file and load them when needed. no more includes.
* [ErrorHandler](http://sledgehammer.github.com/api/class-Sledgehammer.ErroHandler.html) : An error reporting solution.
* [Object](http://sledgehammer.github.com/api/class-Sledgehammer.Object.html)            : A more strict base class with improved error messages.
* [Dump/dump()](http://sledgehammer.github.com/api/class-Sledgehammer.Dump.html)         : A colorfull `var_dump`, with copy-pastable array format.
* [Database](http://sledgehammer.github.com/api/class-Sledgehammer.Database.html)        : PDO Database class enhanced with logging/profiling and improved error/warning detection.
* [Sql](http://sledgehammer.github.com/api/class-Sledgehammer.Sql.html)                  : Generating complex queries in a chainable.
* [Collection](http://sledgehammer.github.com/api/class-Sledgehammer.Collection.html)    : Enhanced Array/Iterator handling.
* [Text](http://sledgehammer.github.com/api/class-Sledgehammer.Text.html)                : Chainable UTF-8 string functions.
* [Url](http://sledgehammer.github.com/api/class-Sledgehammer.Url.html)                  : Read and generate urls in a OOP style.
* [Cache/cache()](http://sledgehammer.github.com/api/class-Sledgehammer.Cache.html)      : Easy caching api using closures.
* [Json](http://sledgehammer.github.com/api/class-Sledgehammer.Json.html)                : Reliable JSON encoding and decoding.
* [DebugR](http://sledgehammer.github.com/api/class-Sledgehammer.DebugR.html)            : Sending debugging information alongside XMLHttpRequests. 
* [more...](http://sledgehammer.github.com/api/package-Core.html)


## Constants

* \Sledgehammer\ENVIRONMENT : The current environment ("development", "staging" or "production") based on `$_SERVER['APPLICATION_ENV']`  
* \Sledgehammer\PATH        : The absolute (server)path of the project folder  
* \Sledgehammer\TMP_DIR     : The absolute (server)path of the tmp/cache folder  

## Installation

Use [composer](http://getcomposer.org/) to install sledgehammer modules.

```
$ composer.phar require sledgehammer/core:*
```

Just `include('vendor/autoload.php');` and the Sledgehammer Framework (and other installed composer libraries) can be used.

You can try the [dump](http://sledgehammer.github.com/api/function-dump.html) function: `dump($var);` to check if the installation is successful.

## Configuration

If no `\Sledgehammer\ENVIRONMENT` constant has been defined, Sledgehammer will look at the value of `$_SERVER['APPLICATION_ENV']`. If this isn't found either, the default fallback is "production"

You can set the `APPLICATION_ENV` by adding `SetEnv APPLICATION_ENV development` to the `.htaccess` or `httpd.conf`:

You can force an environment by defining the \Sledgehammer\ENVIRONMENT constant before including "vendor/autoload.php"

## ErrorHandler

Add `\Sledgehammer\Core\Debug\ErrorHandler::enable();` to allow the Sledgehammer ErrorHandler to handle the errors, warnings, notices and uncaught exceptions.

The errorhandler sends error reports per email to the address configured in `ErrorHandler::instance()->email`.
By default the emailaddress specified in `$_SERVER['SERVER_ADMIN']` is used.

### Static files
Serve static files from modules by adding a line to your rewrite/index.php.
```
require("vendor/sledgehammer/core/src/render_public_folders.php");
```

### Autoloader

The Autoloader Kicks in when the Composer Autoloader was unable to load the class.
The Sledgehammer\Core\Autoloader tries to diagnose the issue and loads the class when it can.