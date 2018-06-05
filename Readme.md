# Sledgehammer Core

[![Build Status](https://travis-ci.org/sledgehammer/core.svg)](https://travis-ci.org/sledgehammer/core)

A general purpose PHP toolkit, with a focus on debugging.

- Improved error reporting with Sledgehammer\Core\Debug\ErrorHandler.
- Improved var_dump() with dump().
- Improved PDO compatible database connection with Sledgehammer\Core\Database\Connection.

## Resources

- [API Documentation](http://sledgehammer.github.com/api/)
- [Sledgehammer Wiki](http://github.com/sledgehammer/sledgehammer/wiki)
- [Issue tracker](https://github.com/sledgehammer/sledgehammer/issues)
- [Roadmap / Backlog](https://trello.com/board/sledgehammer-framework/4ec77591eb9c5577726d94fb)

## Scope

- Debugging, error-reporting, loggin and profiling functionality.
- A collection of global functions (that should be included in PHP, imho)
- Generic utility classes

## Classes

- [Autoloader](http://sledgehammer.github.io/api/class-Sledgehammer.Core.Debug.Autoloader.html) : Detects classes and interfaces in any php file and load them when needed. no more includes.
- [ErrorHandler](http://sledgehammer.github.io/api/class-Sledgehammer.Core.Debug.ErrorHandler.html) : An error reporting solution.
- [Base](http://sledgehammer.github.com/api/class-Sledgehammer.Base.html) : A more strict base class with improved error messages.
- [Sledgehammer/dump()](http://sledgehammer.github.io/api/function-Sledgehammer.dump.html) : A colorful `var_dump`, with copy-pastable array format.
- [Database](http://sledgehammer.github.com/api/class-Sledgehammer.Database.html) : PDO Database class enhanced with logging/profiling and improved error/warning detection.
- [Sql](http://sledgehammer.github.io/api/class-Sledgehammer.Core.Database.Sql.html) : Generating complex queries in a chainable.
- [Collection](http://sledgehammer.github.io/api/class-Sledgehammer.Core.Collection.html) : Enhanced Array/Iterator handling.
- [Text](http://sledgehammer.github.io/api/class-Sledgehammer.Core.Text.html) : Chainable UTF-8 string functions.
- [Url](http://sledgehammer.github.io/api/class-Sledgehammer.Core.Url.html) : Read and generate urls in a OOP style.
- [Sledgehammer/cache()](http://sledgehammer.github.io/api/function-Sledgehammer.cache.html) : Easy caching api using closures.
- [Json](http://sledgehammer.github.io/api/class-Sledgehammer.Core.Json.html) : Reliable JSON encoding and decoding.
- [DebugR](http://sledgehammer.github.io/api/class-Sledgehammer.Core.Debug.DebugR.html) : Sending debugging information alongside XMLHttpRequests.
- [more...](http://sledgehammer.github.io/api/namespace-Sledgehammer.Core.html)

## Installation

Use [composer](http://getcomposer.org/) to install sledgehammer modules.

```
$ composer.phar require sledgehammer/core:*
```

Just `include('vendor/autoload.php');` and the Sledgehammer Framework (and other installed composer libraries) can be used.

You can try the [dump](http://sledgehammer.github.com/api/function-dump.html) function: `dump($var);` to check if the installation is successful.

## ErrorHandler

Add `\Sledgehammer\Core\Debug\ErrorHandler::enable();` to allow the Sledgehammer ErrorHandler to handle the errors, warnings, notices and uncaught exceptions.

The errorhandler can send error reports per email to the address configured in `ErrorHandler->email`.

### Static files

Serve static files from modules by adding a line to your rewrite/index.php.

```
require("vendor/sledgehammer/core/src/render_public_folders.php");
```

### Autoloader

```php
\Sledgehammer\Core\Autoloader::enable();
```

Enables the autoloader, which kicks in when the Composer Autoloader was unable to load the class.
The Sledgehammer\Core\Autoloader tries to diagnose the issue and loads the class when it can.
