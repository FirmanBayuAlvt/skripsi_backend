<?php

namespace App\Imports;

use App\Models\Pen;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;

class PenImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use SkipsErrors;

    private $rows = 0;

    /**
     * @param array $row
     * @return Pen|null
     */
    public function model(array $row)
    {
        $this->rows++;

        return new Pen([
            'name'     => $row['nama'] ?? $row['name'] ?? null,
            'code'     => $row['kode'] ?? $row['code'] ?? null,
            'category' => $row['kategori'] ?? $row['category'] ?? null,
            'capacity' => $row['kapasitas'] ?? $row['capacity'] ?? 0,
            'status'   => $row['status'] ?? 'active',
        ]);
    }

    public function rules(): array
    {
        return [
            'nama'      => 'required|string|max:100',
            'kategori'  => 'required|string|max:50',
            'kapasitas' => 'required|integer|min:1',
            'status'    => 'nullable|in:active,inactive',
        ];
    }

    public function getRowCount(): int
    {
        return $this->rows;
    }
}
