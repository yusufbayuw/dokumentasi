<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

final class FingerprintUsersExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithCustomValueBinder
{
    public function __construct(private readonly int $deviceId)
    {
    }

    public function query(): Builder
    {
        return DB::table('fingerprint_device_users')
            ->select(['user_id', 'uid', 'name', 'role', 'last_seen_at'])
            ->where('device_id', $this->deviceId)
            ->orderBy('id');
    }

    public function headings(): array
    {
        return ['ID Pengguna', 'UID', 'Nama', 'Role', 'Terakhir Terbaca'];
    }

    public function map($row): array
    {
        return [
            (string) $row->user_id,
            (int) $row->uid,
            (string) $row->name,
            (int) $row->role,
            (string) $row->last_seen_at,
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Do not interpret machine user IDs, names or timestamps as Excel formulas.
        // Explicit strings also preserve leading zeros in IDs.
        if (in_array($cell->getColumn(), ['A', 'C', 'E'], true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
