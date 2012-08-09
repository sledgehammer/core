Sledgehammer Core
------------------

Core facilitates the base and initialisation of the Sledgehammer Framework.

Scope
------
* Framework initialisation (+Module detection & initialisation)
* A collection of global functions (that should be included in php, imho)
* Debugging & Error-reporting and Profiling functionality.


Classes
--------
* AutoLoader   :  Detects classes and interfaces in any php file and load them when needed. no more includes.
* ErrorHandler : An error reporting solution.
* Object       : A more strict base class with improved error messages.
* Dump/dump()  : A colorfull var_dump, with copy-pastable array format.
* Database     : PDO Database class enhanced with logging/profiling and improved error/warning detection.
* SQL          : Generating complex queries in a chainable.
* Collection   : Enhanced Array/Iterator handling.
* Text         : chainable UTF-8 string functions.
* URL          : Read and generate urls in a OOP style.
* CSVIterator  :  Easy reading/writing of csv files.

Constants
---------
ENVIRONMENT : The current environment ("development", "staging" or "production") detected based on $_SERVER['APPLICATION_ENV']
E_MAX       : Maximum errorlevel, because E_ALL doesn't include E_STRICT messages.
PATH        : The absolute (server)path of the project folder
TMP_DIR     : The absolute (server)path of the tmp/cache folder
APPLICATION_DIR : The absolute (server)path of the application folder
MODULE_DIR  : The absolute (server)path of the sledgehammer (module) folder


Misc
------
$_SERVER['SERVER_ADMIN'] is used by the ErrorHander as the e-mail address in non-development modes.

= Outside the scope =

* MVC classes
* Helper/View classes focussed on html generation. (unless for debuggin purpesses )


= C# influence =

Collection is inspired by LINQ extension methods. the Text and URL and classes are inspired by C#s String and Uri classes)


## Installation


Om van sledgehammer gebruik te maken importeer je sledgehammer_core naar de “$project/sledgehammer/core” map.
`git clone git://github.com/sledgehammer/core.git sledgehammer/core`
Of voeg deze toe als gitmodule:
`git submodule add git://github.com/sledgehammer/core.git sledgehammer/core`

Vanuit je index/bootstrap.php voeg je de volgende code toe:

```
include("sledgehammer/core/bootstrap.php");
```

Om te testen of alles werkt kun je de handige dump functie uitproberen:
dump($var);

Daarnaast worden errors, warnings en notices nu door de Sledgehammer ErrorHandler afgehandeld.

API Documentatie kan worden gegenereerd met de introspectie tool "DevUtils" zie: https://github.com/sledgehammer/devutils


## Configuration

Als er geen ENVIRONMENT constante is ingesteld, wordt er gekeken naar de $_SERVER['APPLICATION_ENV'] waarde, anders wordt "production" gekozen
De APPLICATION_ENV kun je instellen door: "SetEnv APPLICATION_ENV development" in de .htaccess of in de httpd.conf te zetten.

You can force an environment by defining the ENVIRONMENT constant before including "sledgehammer/core/bootstrap.php"

```
define('ENVIRONMENT', 'development');
```

The errorhandler sends error reports per email to the address configured in `\Sledgehammer\Framework::$errorHandler->email`.
By default the email from  $_SERVER['SERVER_ADMIN'] is used.

### Static files
Serve static files from modules by adding a line to yout rewrite/index.php.
```
include("sledgehammer/core/render_public_folders.php");
```

### AutoLoader
If your application already uses an autoloader, you can configure the AutoLoader to suppress warning if a class issn't found.
```
\Sledgehammer\Framework::$autoLoader->standalone = false;
```