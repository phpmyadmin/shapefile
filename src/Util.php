<?php

declare(strict_types=1);

/**
 * phpMyAdmin ShapeFile library
 * <https://github.com/phpmyadmin/shapefile/>.
 *
 * Copyright 2006-2007 Ovidio <ovidio AT users.sourceforge.net>
 * Copyright 2016 - 2017 Michal Čihař <michal@cihar.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, you can download one from
 * https://www.gnu.org/copyleft/gpl.html.
 */

namespace PhpMyAdmin\ShapeFile;

use function current;
use function pack;
use function sprintf;
use function strrev;
use function unpack;

class Util
{
    private static bool|null $littleEndian = null;

    private const SHAPE_NAMES = [
        0 => 'Null Shape',
        1 => 'Point',
        3 => 'PolyLine',
        5 => 'Polygon',
        8 => 'MultiPoint',
        11 => 'PointZ',
        13 => 'PolyLineZ',
        15 => 'PolygonZ',
        18 => 'MultiPointZ',
        21 => 'PointM',
        23 => 'PolyLineM',
        25 => 'PolygonM',
        28 => 'MultiPointM',
        31 => 'MultiPatch',
    ];

    /**
     * Reads data.
     *
     * @param string       $type type for unpack()
     * @param string|false $data Data to process
     */
    public static function loadData(string $type, string|false $data): mixed
    {
        if ($data === false || $data === '') {
            return false;
        }

        $tmp = unpack($type, $data);

        return $tmp === false ? false : current($tmp);
    }

    /**
     * Encodes double value to correct endianity.
     */
    public static function packDouble(float $value): string
    {
        $bin = pack('d', $value);

        if (self::$littleEndian === null) {
            self::$littleEndian = (pack('L', 1) === pack('V', 1));
        }

        if (self::$littleEndian) {
            return $bin;
        }

        return strrev($bin);
    }

    /**
     * Returns shape name.
     */
    public static function nameShape(int $type): string
    {
        return self::SHAPE_NAMES[$type] ?? sprintf('Shape %d', $type);
    }
}
