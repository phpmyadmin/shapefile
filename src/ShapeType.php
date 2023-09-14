<?php

declare(strict_types=1);

namespace PhpMyAdmin\ShapeFile;

final class ShapeType
{
    public const NULL = 0;

    public const POINT = 1;

    public const POLY_LINE = 3;

    public const POLYGON = 5;

    public const MULTI_POINT = 8;

    public const POINT_Z = 11;

    public const POLY_LINE_Z = 13;

    public const POLYGON_Z = 15;

    public const MULTI_POINT_Z = 18;

    public const POINT_M = 21;

    public const POLY_LINE_M = 23;

    public const POLYGON_M = 25;

    public const MULTI_POINT_M = 28;

    public const MULTI_PATCH = 31;

    /** Shape types with a Z coordinate. */
    public const TYPES_WITH_Z = [self::POINT_Z, self::POLY_LINE_Z, self::POLYGON_Z, self::MULTI_POINT_Z];

    /** Shape types with a measure field. */
    public const MEASURED_TYPES = [
        self::POINT_Z,
        self::POLY_LINE_Z,
        self::POLYGON_Z,
        self::MULTI_POINT_Z,
        self::POINT_M,
        self::POLY_LINE_M,
        self::POLYGON_M,
        self::MULTI_POINT_M,
    ];

    public const NAMES = [
        self::NULL => 'Null Shape',
        self::POINT => 'Point',
        self::POLY_LINE => 'PolyLine',
        self::POLYGON => 'Polygon',
        self::MULTI_POINT => 'MultiPoint',
        self::POINT_Z => 'PointZ',
        self::POLY_LINE_Z => 'PolyLineZ',
        self::POLYGON_Z => 'PolygonZ',
        self::MULTI_POINT_Z => 'MultiPointZ',
        self::POINT_M => 'PointM',
        self::POLY_LINE_M => 'PolyLineM',
        self::POLYGON_M => 'PolygonM',
        self::MULTI_POINT_M => 'MultiPointM',
        self::MULTI_PATCH => 'MultiPatch',
    ];

    /** @psalm-return non-empty-string */
    public static function name(int $shapeType): string
    {
        return self::NAMES[$shapeType] ?? 'Shape ' . $shapeType;
    }
}
