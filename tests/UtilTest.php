<?php
/**
 * phpMyAdmin ShapeFile library
 * <https://github.com/phpmyadmin/shapefile/>.
 *
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

namespace UtilTest;

use PhpMyAdmin\ShapeFile\Util;

class UtilTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test data loading.
     *
     * @param string $type     Data type
     * @param mixed  $data     Data to parse
     * @param mixed  $expected Expected result
     *
     *
     * @dataProvider data
     */
    public function testLoadData($type, $data, $expected)
    {
        $this->assertEquals(
            $expected,
            Util::loadData($type, $data)
        );
    }

    /**
     * Data provider for loadData tests.
     *
     * @return array
     */
    public function data()
    {
        return array(
            array('N', '', false),
            array('N', false, false),
            array('N', "\x01\x02\x03\x04", 0x01020304),
        );
    }

    /**
     * Test for byte order changes.
     */
    public function testSwap()
    {
        $this->assertEquals(
            "\x01\x02\x03\x04",
            Util::swap("\x04\x03\x02\x01")
        );
    }
}
