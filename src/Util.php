<?php
/**
 * phpMyAdmin ShapeFile library
 * <https://github.com/phpmyadmin/shapefile/>
 *
 * Copyright 2006-2007 Ovidio <ovidio AT users.sourceforge.net>
 * Copyright 2016 Michal Čihař <michal@cihar.com>
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
 * http://www.gnu.org/copyleft/gpl.html.
 */
namespace ShapeFile;

class Util {
    private static $little_endian = null;

    public static function loadData($type, $data) {
        if (!$data) {
            return $data;
        }
        $tmp = unpack($type, $data);
        return current($tmp);
    }

    public static function swap($binValue) {
        $result = $binValue{strlen($binValue) - 1};
        for ($i = strlen($binValue) - 2; $i >= 0; $i--) {
            $result .= $binValue{$i};
        }

        return $result;
    }

    public static function packDouble($value) {
        $value = (double) $value;
        $bin = pack("d", $value);

        if (is_null(self::$little_endian)) {
            self::$little_endian = (pack('L', 1) == pack('V', 1));
        }

        if (self::$little_endian) {
            return $bin;
        } else {
            return self::swap($bin);
        }
    }
}
