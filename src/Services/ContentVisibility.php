<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use DateTimeImmutable;
use DateTimeZone;

final class ContentVisibility
{
    /**
     * SQL predicate for content that is publicly visible.
     * Published content is live immediately. Scheduled content is live only when due.
     */
    public static function sql(string $alias = 'c'): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        return "({$prefix}status='published' OR ({$prefix}status='scheduled' AND {$prefix}published_at IS NOT NULL AND {$prefix}published_at<=UTC_TIMESTAMP()))";
    }

    /** Convert the CMS datetime-local value from the editorial timezone to UTC for storage. */
    public static function publishAtFromInput(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        try {
            $local = new DateTimeImmutable($value, self::editorialTimezone());
            return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Convert stored UTC datetime to a datetime-local value for CMS display. */
    public static function publishAtForInput(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') return '';
        try {
            $utc = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $utc->setTimezone(self::editorialTimezone())->format('Y-m-d\\TH:i');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function editorialTimezone(): DateTimeZone
    {
        $name = trim((string)env('CONTENT_TIMEZONE', 'Asia/Kolkata')) ?: 'Asia/Kolkata';
        try { return new DateTimeZone($name); }
        catch (\Throwable) { return new DateTimeZone('Asia/Kolkata'); }
    }
}
