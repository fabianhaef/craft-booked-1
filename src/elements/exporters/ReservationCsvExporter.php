<?php

namespace anvildev\booked\elements\exporters;

use anvildev\booked\helpers\CsvHelper;
use Craft;
use craft\base\ElementExporter;
use craft\elements\db\ElementQueryInterface;

class ReservationCsvExporter extends ElementExporter
{
    public static function displayName(): string
    {
        return Craft::t('booked', 'export.reservations');
    }

    public static function isFormattable(): bool
    {
        return false;
    }

    public function export(ElementQueryInterface $query): mixed
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'ID', 'Name', 'Email', 'Phone',
            'Service', 'Employee', 'Location',
            'Date', 'Start Time', 'End Time', 'Duration (min)',
            'Status', 'Quantity', 'Price',
            'Notes', 'Created',
        ]);

        assert($query instanceof \anvildev\booked\elements\db\ReservationQuery);
        // Craft 6: Db::each()/Db::batch() call createCommand() on the query, a
        // real Yii method that doesn't exist on Craft 6's native, Laravel-backed
        // ElementQuery (confirmed broken even for core elements like User, not
        // just Booked's own query classes). Its own each() exists but takes a
        // callback, not a generator, unlike Yii's - a different enough shape
        // that plain all() is simpler and dual-compatible without branching.
        // Element exports run on an already-filtered/selected set, not the
        // whole table, so the memory tradeoff is fine here.
        foreach ($query->withRelations()->all() as $r) {
            /** @var \anvildev\booked\elements\Reservation $r */
            fputcsv($handle, [
                (string) $r->id,
                CsvHelper::sanitizeValue($r->userName ?? ''),
                CsvHelper::sanitizeValue($r->userEmail ?? ''),
                CsvHelper::sanitizeValue($r->userPhone ?? ''),
                CsvHelper::sanitizeValue($r->getService()?->title ?? ''),
                CsvHelper::sanitizeValue($r->getEmployee()?->title ?? ''),
                CsvHelper::sanitizeValue($r->getLocation()?->title ?? ''),
                (string) ($r->bookingDate ?? ''),
                (string) ($r->startTime ?? ''),
                (string) ($r->endTime ?? ''),
                (string) $r->getDurationMinutes(),
                (string) $r->getStatusLabel(),
                (string) $r->quantity,
                number_format($r->getTotalPrice(), 2, '.', ''),
                CsvHelper::sanitizeValue($r->notes ?? ''),
                $r->dateCreated ? $r->dateCreated->format('Y-m-d H:i:s') : '',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    public function getFilename(): string
    {
        return 'bookings-' . date('Y-m-d') . '.csv';
    }
}
