<?php

namespace App\Imports;

use App\Models\Livestock;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class LivestockImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Livestock([
            'ear_tag' => $row['ear_tag'],
            'breed_type' => $row['breed_type'],
            'gender' => $row['gender'],
            'birth_date' => Date::excelToDateTimeObject($row['birth_date'])->format('Y-m-d'),
            'initial_weight' => $row['initial_weight'],
            'health_status' => $row['health_status'] ?? 'good',
            'notes' => $row['notes'] ?? null,
            'pen_id' => $row['pen_id'] ?? null,
        ]);
    }
}
