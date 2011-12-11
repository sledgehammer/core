
SledgeHammer Core
------------------

Core fasilitates the base and intialisation of the SledgeHammer Framework.

Scope
------
* Framework initialisation (+Module detection & initialisation)
* A collection of global functions (that should be included in php)
* Debugging & Error-reporting and Profiling functionality.


Classes
--------
* AutoLoader   :  Detects classes and interfaces in any php file and load them when needed. no more includes.
* ErrorHandler : An error reporting solution
* Object       : A more strict base class with improved error messages.
* Dump/dump()  : A colorfull var_dump, with copy-pastable array format.
* Database     : PDO Database class enhanced with logging/profiling and improved error/warning detection.
* SQL          : Generating complex queries in a chainable 
* Collection   : Enhanced Array/Iterator handling 
* Text         : chainable UTF-8 string functions. 
* URL          : Read and generate urls in a OOP style.
* CSVIterator  :  Easy reading/writing of csv files
* render_file(): Serve static files (Use render_public_folders.php to .

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
$_SERVER['SERVER_ADMIN'] wordt gebruikt als ErrorHander email in non-development modus.

= Outside the scope =

* MVC classes
* Helper/View classes focussed on html generation. (unless for debuggin purpesses )


= C# influence =

Collection is inspired by LINQ extension methods. the Text and URL and classes are inspired by C#s String and Uri classes)
