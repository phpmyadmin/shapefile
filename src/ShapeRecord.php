<?php
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
declare(strict_types=1);

namespace PhpMyAdmin\ShapeFile;

/**
 * ShapeFile record class.
 */
class ShapeRecord
{
    private $SHPFile = null;
    private $DBFFile = null;
    private $ShapeFile = null;

    private $size = 0;
    private $read = 0;

    public $recordNumber = null;
    public $shapeType = null;

    public $lastError = '';

    public $SHPData = [];
    public $DBFData = [];

    /**
     * @param int $shapeType
     */
    public function __construct($shapeType)
    {
        $this->shapeType = $shapeType;
    }

    /**
     * Loads record from files.
     *
     * @param ShapeFile $ShapeFile
     * @param file      &$SHPFile  Opened SHP file
     * @param file      &$DBFFile  Opened DBF file
     */
    public function loadFromFile(&$ShapeFile, &$SHPFile, &$DBFFile)
    {
        $this->ShapeFile = $ShapeFile;
        $this->SHPFile = $SHPFile;
        $this->DBFFile = $DBFFile;
        $this->loadHeaders();

        /* No header read */
        if ($this->read == 0) {
            return;
        }

        switch ($this->shapeType) {
            case 0:
                $this->loadNullRecord();
                break;
            case 1:
                $this->loadPointRecord();
                break;
            case 21:
                $this->loadPointMRecord();
                break;
            case 11:
                $this->loadPointZRecord();
                break;
            case 3:
                $this->loadPolyLineRecord();
                break;
            case 23:
                $this->loadPolyLineMRecord();
                break;
            case 13:
                $this->loadPolyLineZRecord();
                break;
            case 5:
                $this->loadPolygonRecord();
                break;
            case 25:
                $this->loadPolygonMRecord();
                break;
            case 15:
                $this->loadPolygonZRecord();
                break;
            case 8:
                $this->loadMultiPointRecord();
                break;
            case 28:
                $this->loadMultiPointMRecord();
                break;
            case 18:
                $this->loadMultiPointZRecord();
                break;
            default:
                $this->setError(sprintf('The Shape Type "%s" is not supported.', $this->shapeType));
                break;
        }

        /* We need to skip rest of the record */
        while ($this->read < $this->size) {
            $this->loadData('V', 4);
        }

        /* Check if we didn't read too much */
        if ($this->read != $this->size) {
            $this->setError(sprintf('Failed to parse record, read=%d, size=%d', $this->read, $this->size));
        }

        if (ShapeFile::supportsDbase() && isset($this->DBFFile)) {
            $this->loadDBFData();
        }
    }

    /**
     * Saves record to files.
     *
     * @param file &$SHPFile     Opened SHP file
     * @param file &$DBFFile     Opened DBF file
     * @param int  $recordNumber Record number
     */
    public function saveToFile(&$SHPFile, &$DBFFile, $recordNumber)
    {
        $this->SHPFile = $SHPFile;
        $this->DBFFile = $DBFFile;
        $this->recordNumber = $recordNumber;
        $this->saveHeaders();

        switch ($this->shapeType) {
            case 0:
                // Nothing to save
                break;
            case 1:
                $this->savePointRecord();
                break;
            case 21:
                $this->savePointMRecord();
                break;
            case 11:
                $this->savePointZRecord();
                break;
            case 3:
                $this->savePolyLineRecord();
                break;
            case 23:
                $this->savePolyLineMRecord();
                break;
            case 13:
                $this->savePolyLineZRecord();
                break;
            case 5:
                $this->savePolygonRecord();
                break;
            case 25:
                $this->savePolygonMRecord();
                break;
            case 15:
                $this->savePolygonZRecord();
                break;
            case 8:
                $this->saveMultiPointRecord();
                break;
            case 28:
                $this->saveMultiPointMRecord();
                break;
            case 18:
                $this->saveMultiPointZRecord();
                break;
            default:
                $this->setError(sprintf('The Shape Type "%s" is not supported.', $this->shapeType));
                break;
        }
        if (ShapeFile::supportsDbase() && ! is_null($this->DBFFile)) {
            $this->saveDBFData();
        }
    }

    /**
     * Updates DBF data to match header.
     *
     * @param array $header DBF structure header
     */
    public function updateDBFInfo($header)
    {
        $tmp = $this->DBFData;
        unset($this->DBFData);
        $this->DBFData = [];
        foreach ($header as $value) {
            $this->DBFData[$value[0]] = (isset($tmp[$value[0]])) ? $tmp[$value[0]] : '';
        }
    }

    /**
     * Reads data.
     *
     * @param string $type  type for unpack()
     * @param int    $count number of bytes
     *
     * @return mixed
     */
    private function loadData($type, $count)
    {
        $data = $this->ShapeFile->readSHP($count);
        if ($data === false) {
            return false;
        }
        $this->read += strlen($data);

        return Util::loadData($type, $data);
    }

    /**
     * Loads metadata header from a file.
     */
    private function loadHeaders()
    {
        $this->shapeType = false;
        $this->recordNumber = $this->loadData('N', 4);
        if ($this->recordNumber === false) {
            return;
        }
        // We read the length of the record
        $this->size = $this->loadData('N', 4);
        if ($this->size === false) {
            return;
        }
        $this->size = $this->size * 2 + 8;
        $this->shapeType = $this->loadData('V', 4);
    }

    /**
     * Saves metadata header to a file.
     */
    private function saveHeaders()
    {
        fwrite($this->SHPFile, pack('N', $this->recordNumber));
        fwrite($this->SHPFile, pack('N', $this->getContentLength()));
        fwrite($this->SHPFile, pack('V', $this->shapeType));
    }

    private function loadPoint()
    {
        $data = [];

        $data['x'] = $this->loadData('d', 8);
        $data['y'] = $this->loadData('d', 8);

        return $data;
    }

    private function loadPointM()
    {
        $data = $this->loadPoint();

        $data['m'] = $this->loadData('d', 8);

        return $data;
    }

    private function loadPointZ()
    {
        $data = $this->loadPoint();

        $data['z'] = $this->loadData('d', 8);
        $data['m'] = $this->loadData('d', 8);

        return $data;
    }

    private function savePoint($data)
    {
        fwrite($this->SHPFile, Util::packDouble($data['x']));
        fwrite($this->SHPFile, Util::packDouble($data['y']));
    }

    private function savePointM($data)
    {
        fwrite($this->SHPFile, Util::packDouble($data['x']));
        fwrite($this->SHPFile, Util::packDouble($data['y']));
        fwrite($this->SHPFile, Util::packDouble($data['m']));
    }

    private function savePointZ($data)
    {
        fwrite($this->SHPFile, Util::packDouble($data['x']));
        fwrite($this->SHPFile, Util::packDouble($data['y']));
        fwrite($this->SHPFile, Util::packDouble($data['z']));
        fwrite($this->SHPFile, Util::packDouble($data['m']));
    }

    private function loadNullRecord()
    {
        $this->SHPData = [];
    }

    private function loadPointRecord()
    {
        $this->SHPData = $this->loadPoint();
    }

    private function loadPointMRecord()
    {
        $this->SHPData = $this->loadPointM();
    }

    private function loadPointZRecord()
    {
        $this->SHPData = $this->loadPointZ();
    }

    private function savePointRecord()
    {
        $this->savePoint($this->SHPData);
    }

    private function savePointMRecord()
    {
        $this->savePointM($this->SHPData);
    }

    private function savePointZRecord()
    {
        $this->savePointZ($this->SHPData);
    }

    private function loadBBox()
    {
        $this->SHPData['xmin'] = $this->loadData('d', 8);
        $this->SHPData['ymin'] = $this->loadData('d', 8);
        $this->SHPData['xmax'] = $this->loadData('d', 8);
        $this->SHPData['ymax'] = $this->loadData('d', 8);
    }

    private function loadMultiPointRecord()
    {
        $this->SHPData = [];
        $this->loadBBox();

        $this->SHPData['numpoints'] = $this->loadData('V', 4);

        for ($i = 0; $i < $this->SHPData['numpoints']; ++$i) {
            $this->SHPData['points'][] = $this->loadPoint();
        }
    }

    /**
     * @param string $type
     */
    private function loadMultiPointMZRecord($type)
    {
        /* The m dimension is optional, depends on bounding box data */
        if ($type == 'm' && ! $this->ShapeFile->hasMeasure()) {
            return;
        }

        $this->SHPData[$type . 'min'] = $this->loadData('d', 8);
        $this->SHPData[$type . 'max'] = $this->loadData('d', 8);

        for ($i = 0; $i < $this->SHPData['numpoints']; ++$i) {
            $this->SHPData['points'][$i][$type] = $this->loadData('d', 8);
        }
    }

    private function loadMultiPointMRecord()
    {
        $this->loadMultiPointRecord();

        $this->loadMultiPointMZRecord('m');
    }

    private function loadMultiPointZRecord()
    {
        $this->loadMultiPointRecord();

        $this->loadMultiPointMZRecord('z');
        $this->loadMultiPointMZRecord('m');
    }

    private function saveMultiPointRecord()
    {
        fwrite($this->SHPFile, pack('dddd', $this->SHPData['xmin'], $this->SHPData['ymin'], $this->SHPData['xmax'], $this->SHPData['ymax']));

        fwrite($this->SHPFile, pack('V', $this->SHPData['numpoints']));

        for ($i = 0; $i < $this->SHPData['numpoints']; ++$i) {
            $this->savePoint($this->SHPData['points'][$i]);
        }
    }

    /**
     * @param string $type
     */
    private function saveMultiPointMZRecord($type)
    {
        fwrite($this->SHPFile, pack('dd', $this->SHPData[$type . 'min'], $this->SHPData[$type . 'max']));

        for ($i = 0; $i < $this->SHPData['numpoints']; ++$i) {
            fwrite($this->SHPFile, Util::packDouble($this->SHPData['points'][$i][$type]));
        }
    }

    private function saveMultiPointMRecord()
    {
        $this->saveMultiPointRecord();

        $this->saveMultiPointMZRecord('m');
    }

    private function saveMultiPointZRecord()
    {
        $this->saveMultiPointRecord();

        $this->saveMultiPointMZRecord('z');
        $this->saveMultiPointMZRecord('m');
    }

    private function loadPolyLineRecord()
    {
        $this->SHPData = [];
        $this->loadBBox();

        $this->SHPData['numparts'] = $this->loadData('V', 4);
        $this->SHPData['numpoints'] = $this->loadData('V', 4);

        $numparts = $this->SHPData['numparts'];
        $numpoints = $this->SHPData['numpoints'];

        for ($i = 0; $i < $numparts; ++$i) {
            $this->SHPData['parts'][$i] = $this->loadData('V', 4);
        }

        $part = 0;
        for ($i = 0; $i < $numpoints; ++$i) {
            if ($part + 1 < $numparts && $i == $this->SHPData['parts'][$part + 1]) {
                ++$part;
            }
            if (! isset($this->SHPData['parts'][$part]['points']) || ! is_array($this->SHPData['parts'][$part]['points'])) {
                $this->SHPData['parts'][$part] = ['points' => []];
            }
            $this->SHPData['parts'][$part]['points'][] = $this->loadPoint();
        }
    }

    /**
     * @param string $type
     */
    private function loadPolyLineMZRecord($type)
    {
        /* The m dimension is optional, depends on bounding box data */
        if ($type == 'm' && ! $this->ShapeFile->hasMeasure()) {
            return;
        }

        $this->SHPData[$type . 'min'] = $this->loadData('d', 8);
        $this->SHPData[$type . 'max'] = $this->loadData('d', 8);

        $numparts = $this->SHPData['numparts'];
        $numpoints = $this->SHPData['numpoints'];

        $part = 0;
        for ($i = 0; $i < $numpoints; ++$i) {
            if ($part + 1 < $numparts && $i == $this->SHPData['parts'][$part + 1]) {
                ++$part;
            }
            $this->SHPData['parts'][$part]['points'][$i][$type] = $this->loadData('d', 8);
        }
    }

    private function loadPolyLineMRecord()
    {
        $this->loadPolyLineRecord();

        $this->loadPolyLineMZRecord('m');
    }

    private function loadPolyLineZRecord()
    {
        $this->loadPolyLineRecord();

        $this->loadPolyLineMZRecord('z');
        $this->loadPolyLineMZRecord('m');
    }

    private function savePolyLineRecord()
    {
        fwrite($this->SHPFile, pack('dddd', $this->SHPData['xmin'], $this->SHPData['ymin'], $this->SHPData['xmax'], $this->SHPData['ymax']));

        fwrite($this->SHPFile, pack('VV', $this->SHPData['numparts'], $this->SHPData['numpoints']));

        $part_index = 0;
        for ($i = 0; $i < $this->SHPData['numparts']; ++$i) {
            fwrite($this->SHPFile, pack('V', $part_index));
            $part_index += count($this->SHPData['parts'][$i]['points']);
        }

        foreach ($this->SHPData['parts'] as $partData) {
            foreach ($partData['points'] as $pointData) {
                $this->savePoint($pointData);
            }
        }
    }

    /**
     * @param string $type
     */
    private function savePolyLineMZRecord($type)
    {
        fwrite($this->SHPFile, pack('dd', $this->SHPData[$type . 'min'], $this->SHPData[$type . 'max']));

        foreach ($this->SHPData['parts'] as $partData) {
            foreach ($partData['points'] as $pointData) {
                fwrite($this->SHPFile, Util::packDouble($pointData[$type]));
            }
        }
    }

    private function savePolyLineMRecord()
    {
        $this->savePolyLineRecord();

        $this->savePolyLineMZRecord('m');
    }

    private function savePolyLineZRecord()
    {
        $this->savePolyLineRecord();

        $this->savePolyLineMZRecord('z');
        $this->savePolyLineMZRecord('m');
    }

    private function loadPolygonRecord()
    {
        $this->loadPolyLineRecord();
    }

    private function loadPolygonMRecord()
    {
        $this->loadPolyLineMRecord();
    }

    private function loadPolygonZRecord()
    {
        $this->loadPolyLineZRecord();
    }

    private function savePolygonRecord()
    {
        $this->savePolyLineRecord();
    }

    private function savePolygonMRecord()
    {
        $this->savePolyLineMRecord();
    }

    private function savePolygonZRecord()
    {
        $this->savePolyLineZRecord();
    }

    private function adjustBBox($point)
    {
        // Adjusts bounding box based on point
        $directions = [
            'x',
            'y',
            'z',
            'm',
        ];
        foreach ($directions as $direction) {
            if (! isset($point[$direction])) {
                continue;
            }
            $min = $direction . 'min';
            $max = $direction . 'max';
            if (! isset($this->SHPData[$min]) || ($this->SHPData[$min] > $point[$direction])) {
                $this->SHPData[$min] = $point[$direction];
            }
            if (! isset($this->SHPData[$max]) || ($this->SHPData[$max] < $point[$direction])) {
                $this->SHPData[$max] = $point[$direction];
            }
        }
    }

    /**
     * Sets dimension to 0 if not set.
     *
     * @param array  $point     Point to check
     * @param string $dimension Dimension to check
     *
     * @return array
     */
    private function fixPoint($point, $dimension)
    {
        if (! isset($point[$dimension])) {
            $point[$dimension] = 0.0; // no_value
        }

        return $point;
    }

    /**
     * Adjust point and bounding box when adding point.
     *
     * @param array $point Point data
     *
     * @return array Fixed point data
     */
    private function adjustPoint($point)
    {
        $type = $this->shapeType / 10;
        if ($type >= 2) {
            $point = $this->fixPoint($point, 'm');
        } elseif ($type >= 1) {
            $point = $this->fixPoint($point, 'z');
            $point = $this->fixPoint($point, 'm');
        }

        return $point;
    }

    /**
     * Adds point to a record.
     *
     * @param array $point     Point data
     * @param int   $partIndex Part index
     */
    public function addPoint($point, $partIndex = 0)
    {
        $point = $this->adjustPoint($point);
        switch ($this->shapeType) {
            case 0:
                //Don't add anything
                return;
            case 1:
            case 11:
            case 21:
                //Substitutes the value of the current point
                $this->SHPData = $point;
                break;
            case 3:
            case 5:
            case 13:
            case 15:
            case 23:
            case 25:
                //Adds a new point to the selected part
                $this->SHPData['parts'][$partIndex]['points'][] = $point;
                $this->SHPData['numparts'] = count($this->SHPData['parts']);
                $this->SHPData['numpoints'] = 1 + (isset($this->SHPData['numpoints']) ? $this->SHPData['numpoints'] : 0);
                break;
            case 8:
            case 18:
            case 28:
                //Adds a new point
                $this->SHPData['points'][] = $point;
                $this->SHPData['numpoints'] = 1 + (isset($this->SHPData['numpoints']) ? $this->SHPData['numpoints'] : 0);
                break;
            default:
                $this->setError(sprintf('The Shape Type "%s" is not supported.', $this->shapeType));

                return;
        }
        $this->adjustBBox($point);
    }

    /**
     * Deletes point from a record.
     *
     * @param int $pointIndex Point index
     * @param int $partIndex  Part index
     */
    public function deletePoint($pointIndex = 0, $partIndex = 0)
    {
        switch ($this->shapeType) {
            case 0:
                //Don't delete anything
                break;
            case 1:
            case 11:
            case 21:
                //Sets the value of the point to zero
                $this->SHPData['x'] = 0.0;
                $this->SHPData['y'] = 0.0;
                if (in_array($this->shapeType, [11, 21])) {
                    $this->SHPData['m'] = 0.0;
                }
                if (in_array($this->shapeType, [11])) {
                    $this->SHPData['z'] = 0.0;
                }
                break;
            case 3:
            case 5:
            case 13:
            case 15:
            case 23:
            case 25:
                //Deletes the point from the selected part, if exists
                if (isset($this->SHPData['parts'][$partIndex]) && isset($this->SHPData['parts'][$partIndex]['points'][$pointIndex])) {
                    $count = count($this->SHPData['parts'][$partIndex]['points']) - 1;
                    for ($i = $pointIndex; $i < $count; ++$i) {
                        $this->SHPData['parts'][$partIndex]['points'][$i] = $this->SHPData['parts'][$partIndex]['points'][$i + 1];
                    }
                    unset($this->SHPData['parts'][$partIndex]['points'][count($this->SHPData['parts'][$partIndex]['points']) - 1]);

                    $this->SHPData['numparts'] = count($this->SHPData['parts']);
                    --$this->SHPData['numpoints'];
                }
                break;
            case 8:
            case 18:
            case 28:
                //Deletes the point, if exists
                if (isset($this->SHPData['points'][$pointIndex])) {
                    $count = count($this->SHPData['points']) - 1;
                    for ($i = $pointIndex; $i < $count; ++$i) {
                        $this->SHPData['points'][$i] = $this->SHPData['points'][$i + 1];
                    }
                    unset($this->SHPData['points'][count($this->SHPData['points']) - 1]);

                    --$this->SHPData['numpoints'];
                }
                break;
            default:
                $this->setError(sprintf('The Shape Type "%s" is not supported.', $this->shapeType));
                break;
        }
    }

    /**
     * Returns length of content.
     *
     * @return int
     */
    public function getContentLength()
    {
        // The content length for a record is the length of the record contents section measured in 16-bit words.
        // one coordinate makes 4 16-bit words (64 bit double)
        switch ($this->shapeType) {
            case 0:
                $result = 0;
                break;
            case 1:
                $result = 10;
                break;
            case 21:
                $result = 10 + 4;
                break;
            case 11:
                $result = 10 + 8;
                break;
            case 3:
            case 5:
                $count = count($this->SHPData['parts']);
                $result = 22 + 2 * $count;
                for ($i = 0; $i < $count; ++$i) {
                    $result += 8 * count($this->SHPData['parts'][$i]['points']);
                }
                break;
            case 23:
            case 25:
                $count = count($this->SHPData['parts']);
                $result = 22 + (2 * 4) + 2 * $count;
                for ($i = 0; $i < $count; ++$i) {
                    $result += (8 + 4) * count($this->SHPData['parts'][$i]['points']);
                }
                break;
            case 13:
            case 15:
                $count = count($this->SHPData['parts']);
                $result = 22 + (4 * 4) + 2 * $count;
                for ($i = 0; $i < $count; ++$i) {
                    $result += (8 + 8) * count($this->SHPData['parts'][$i]['points']);
                }
                break;
            case 8:
                $result = 20 + 8 * count($this->SHPData['points']);
                break;
            case 28:
                $result = 20 + (2 * 4) + (8 + 4) * count($this->SHPData['points']);
                break;
            case 18:
                $result = 20 + (4 * 4) + (8 + 8) * count($this->SHPData['points']);
                break;
            default:
                $result = false;
                $this->setError(sprintf('The Shape Type "%s" is not supported.', $this->shapeType));
                break;
        }

        return $result;
    }

    private function loadDBFData()
    {
        $this->DBFData = @dbase_get_record_with_names($this->DBFFile, $this->recordNumber);
        unset($this->DBFData['deleted']);
    }

    private function saveDBFData()
    {
        if (count($this->DBFData) == 0) {
            return;
        }
        unset($this->DBFData['deleted']);
        if ($this->recordNumber <= dbase_numrecords($this->DBFFile)) {
            if (! dbase_replace_record($this->DBFFile, array_values($this->DBFData), $this->recordNumber)) {
                $this->setError('I wasn\'t possible to update the information in the DBF file.');
            }
        } else {
            if (! dbase_add_record($this->DBFFile, array_values($this->DBFData))) {
                $this->setError('I wasn\'t possible to add the information to the DBF file.');
            }
        }
    }

    /**
     * Sets error message.
     *
     * @param string $error
     */
    public function setError($error)
    {
        $this->lastError = $error;
    }

    /**
     * Returns shape name.
     *
     * @return string
     */
    public function getShapeName()
    {
        return Util::nameShape($this->shapeType);
    }
}
