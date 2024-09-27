<?php

declare(strict_types=1);

namespace PhpMyAdmin\ShapeFile;

enum ShapeType: int
{
    case Null = 0;

    case Point = 1;

    case PolyLine = 3;

    case Polygon = 5;

    case MultiPoint = 8;

    case PointZ = 11;

    case PolyLineZ = 13;

    case PolygonZ = 15;

    case MultiPointZ = 18;

    case PointM = 21;

    case PolyLineM = 23;

    case PolygonM = 25;

    case MultiPointM = 28;

    case MultiPatch = 31;

    case Unknown = -1;

    /** Shape types with a Z coordinate. */
    public const TYPES_WITH_Z = [self::PointZ, self::PolyLineZ, self::PolygonZ, self::MultiPointZ];

    /** Shape types with a measure field. */
    public const MEASURED_TYPES = [
        self::PointZ,
        self::PolyLineZ,
        self::PolygonZ,
        self::MultiPointZ,
        self::PointM,
        self::PolyLineM,
        self::PolygonM,
        self::MultiPointM,
    ];

    public const NAMES = [
        self::Unknown->value => 'Unknown Shape',
        self::Null->value => 'Null Shape',
        self::Point->value => 'Point',
        self::PolyLine->value => 'PolyLine',
        self::Polygon->value => 'Polygon',
        self::MultiPoint->value => 'MultiPoint',
        self::PointZ->value => 'PointZ',
        self::PolyLineZ->value => 'PolyLineZ',
        self::PolygonZ->value => 'PolygonZ',
        self::MultiPointZ->value => 'MultiPointZ',
        self::PointM->value => 'PointM',
        self::PolyLineM->value => 'PolyLineM',
        self::PolygonM->value => 'PolygonM',
        self::MultiPointM->value => 'MultiPointM',
        self::MultiPatch->value => 'MultiPatch',
    ];

    /** @psalm-return non-empty-string */
    public static function name(ShapeType $shapeType): string
    {
        return self::NAMES[$shapeType->value];
    }
}
