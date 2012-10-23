Sledgehammer Core
------------------

Core facilitates the base and initialisation of the Sledgehammer Framework.

Scope
------
* Framework initialisation (+Module detection & initialisation)
* A collection of global functions (that should be included in PHP, imho)
* Debugging & Error-reporting and Profiling functionality.


Classes
--------
* AutoLoader   :  Detects classes and interfaces in any php file and load them when needed. no more includes.
* ErrorHandler : An error reporting solution.
* Object       : A more strict base class with improved error messages.
* Dump/dump()  : A colorfull `var_dump`, with copy-pastable array format.
* Database     : PDO Database class enhanced with logging/profiling and improved error/warning detection.
* SQL          : Generating complex queries in a chainable.
* Collection   : Enhanced Array/Iterator handling.
* Text         : chainable UTF-8 string functions.
* URL          : Read and generate urls in a OOP style.
* CSVIterator  :  Easy reading/writing of csv files.

Constants
---------
+ ENVIRONMENT     : The current environment ("development", "staging" or "production") detected based on `$_SERVER['APPLICATION_ENV']`  
+ E\_MAX          : Maximum errorlevel, because `E_ALL` doesn't include `E_STRICT` messages.  
+ PATH            : The absolute (server)path of the project folder  
+ TMP_DIR         : The absolute (server)path of the tmp/cache folder  
+ APPLICATION_DIR : The absolute (server)path of the application folder  
+ MODULE_DIR      : The absolute (server)path of the sledgehammer (module) folder


Misc
------
`$_SERVER['SERVER_ADMIN']` is used by the ErrorHander as the e-mail address in non-development modes.

= Outside the scope =

* MVC classes
* Helper/View classes focussed on html generation. (unless for debuggin purpesses )


= C# influence =

Collection is inspired by LINQ extension methods. the Text and URL and classes are inspired by C#s String and Uri classes)


## Installation

To start using Sledgehammer you have to import `sledgehammer_core` to a folder called "$project/sledgehammer/core":
`git clone git://github.com/sledgehammer/core.git sledgehammer/core`
Alternatively, you can add it as a submodule:
`git submodule add git://github.com/sledgehammer/core.git sledgehammer/core`

From your index/bootstrap.php add the following code:

```
include("sledgehammer/core/bootstrap.php");
```

To test whether everything is working correctly, you can try the following convenient function: `dump($var);`

As an added bonus, all errors, warnings and notices will now be handled by the Sledgehammer ErrorHandler.

API Documentation can be generated through the introspection-tool "DevUtils": https://github.com/sledgehammer/devutils


## Configuration

If no `ENVIRONMENT` constant has been defined, Sledgehammer will look at the value of `$_SERVER['APPLICATION_ENV']`. If this isn't found either, the default fallback is "production"

You can set the `APPLICATION_ENV` by adding the following to either your `.htaccess` or `httpd.conf`:

```
SetEnv APPLICATION_ENV development
```

You can force an environment by defining the ENVIRONMENT constant before including "sledgehammer/core/bootstrap.php"

```
define('ENVIRONMENT', 'development');
```

The errorhandler sends error reports per email to the address configured in `\Sledgehammer\Framework::$errorHandler->email`.
By default the email from `$_SERVER['SERVER_ADMIN']` is used.

### Static files
Serve static files from modules by adding a line to yout rewrite/index.php.
```
include("sledgehammer/core/render_public_folders.php");
```

### AutoLoader
If your application already uses an autoloader, you can configure the AutoLoader to suppress warning if a class isn't found.
```
\Sledgehammer\Framework::$autoLoader->standalone = false;
```