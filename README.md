# shapefile
ShapeFile library for PHP

[![Test-suite](https://github.com/phpmyadmin/shapefile/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/phpmyadmin/shapefile/actions/workflows/tests.yml?query=branch%3Amaster)
[![codecov.io](https://codecov.io/github/phpmyadmin/shapefile/coverage.svg?branch=master)](https://codecov.io/github/phpmyadmin/shapefile?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phpmyadmin/shapefile/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phpmyadmin/shapefile/?branch=master)
[![Packagist](https://img.shields.io/packagist/dt/phpmyadmin/shapefile.svg)](https://packagist.org/packages/phpmyadmin/shapefile)

## Features

This library supports the 2D and 3D variants, except MultiPatch, of the ShapeFile format as
defined in https://www.esri.com/library/whitepapers/pdfs/shapefile.pdf. It can read and edit ShapeFiles and the associated
information (DBF file). There are a lot of things that can be improved in the
code, if you are interested in developing, helping with the documentation,
making translations or offering new ideas please contact us.

## Installation

Please use [Composer][1] to install:

```sh
composer require phpmyadmin/shapefile
```

To be able to read and write the associated DBF file you need the ``dbase``
extension:

```sh
pecl install dbase
echo "extension=dbase.so" > /etc/php8/conf.d/dbase.ini
```

## Documentation

API documentation is available at
<https://develdocs.phpmyadmin.net/shapefile/>.

## Usage

To read a shape file:

```php
use PhpMyAdmin\ShapeFile\ShapeFile;
use PhpMyAdmin\ShapeFile\ShapeType;

$shp = new ShapeFile(ShapeType::Null);
$shp->loadFromFile('path/file.*');
```

## History

This library is based on BytesFall ShapeFiles library written by Ovidio (ovidio
AT users.sourceforge.net). It has been embedded in phpMyAdmin for
years and slowly developed there. At one point people started to use our
version rather than the original library and that was when we decided to
make it a separate package.

[1]:https://getcomposer.org/
