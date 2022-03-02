# Tk Installers :boom:

__Project:__ [uom/tk-installers](http://packagist.org/packages/uom/tk-installers)
__Web:__ <http://www.tropotek.com/>  
__Authors:__ Michael Mifsud <http://www.tropotek.com/>  
__Reference:__ <https://getcomposer.org/doc/articles/custom-installers.md>

This lib is used by composer when using the update/install command.

## Contents

- [Installation](#installation)
- [Introduction](#introduction)


## Installation

Available on Packagist ([uom/tk-installers](https://github.com/fvas-elearning/tk-installers))
and as such installable via [Composer](http://getcomposer.org/).

```bash
composer require uom/tk-installers
```

Or add the following to your composer.json file:

```json
"uom/tk-installers": "~3.0"
```

If you do not use Composer, you can grab the code from GitHub, and use any
PSR-0 compatible autoloader to load the classes.

## Introduction

The main aim of this project is to allow for the Tk libs to build packages that install
to other directories other than the `vendor` directory.

Tk projects also contain:

- `assets` A folder to store media, css and Javascript packages if required
- `plugins` A folder for plugins that some sites may want to implement.
- `theme` A folder for your site themes

The InitProject Event object is used when the composer update/install command is
run. This first checks for a config.php and a .htaccess and if they do not exist it then creates them from
the .htaccess.in and config.php.in if they are readable. It also creates a data folder and makes it writable.


