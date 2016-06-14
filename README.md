# shapefile
ShapeFile library for PHP

[![Build Status](https://travis-ci.org/phpmyadmin/shapefile.svg?branch=master)](https://travis-ci.org/phpmyadmin/shapefile)
[![codecov.io](https://codecov.io/github/phpmyadmin/shapefile/coverage.svg?branch=master)](https://codecov.io/github/phpmyadmin/shapefile?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phpmyadmin/shapefile/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phpmyadmin/shapefile/?branch=master)
[![Packagist](https://img.shields.io/packagist/dt/phpmyadmin/shapefile.svg)](https://packagist.org/packages/phpmyadmin/shapefile)

## Features

Curretntly the 2D and 3D variants except MultiPatch of the ShapeFile format as
defined in http://www.esri.com/library/whitepapers/pdfs/shapefile.pdf. The
library currently supports reading and editing of ShapeFiles and the Associated
information (DBF file). There are a lot of things that can be improved in the
code, if you are interested in developing, helping with the documentation,
making translations or offering new ideas please contact us.

## Installation

Please use [Composer][1] to install:
    
``` 
composer require phpmyadmin/shapefile
``` 

## History

This library is based on BytesFall ShapeFiles library written by Ovidio (ovidio
AT users.sourceforge.net). The library has been embedded in phpMyAdmin for
years and slowly developed there. At one point people started to use our
version rather than the original library and that was the point we decided to
make it separate package.

[1]:https://getcomposer.org/

