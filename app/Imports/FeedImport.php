<?php

namespace App\Imports;

use App\Models\Feed;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;

class FeedImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use SkipsErrors;

    private $rows = 0;

    /**
     * @param array $row
     * @return Feed|null
     */
    public function model(array $row)
    {
        $this->rows++;

        return new Feed([
            'name'          => $row['nama'] ?? $row['name'] ?? null,
            'category'      => $row['kategori'] ?? $row['category'] ?? null,
            'current_stock' => $row['stok_awal'] ?? $row['current_stock'] ?? 0,
            'price_per_kg'  => $row['harga_per_kg'] ?? $row['price_per_kg'] ?? null,
            'unit'          => $row['satuan'] ?? $row['unit'] ?? 'kg',
            'is_active'     => filter_var($row['aktif'] ?? $row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        return [
            'nama'      => 'required|string|max:100',
            'kategori'  => 'required|in:silase,cf_jember,jagung_halus,konsentrat',
            'stok_awal' => 'nullable|numeric|min:0',
            'harga_per_kg' => 'nullable|numeric|min:0',
        ];
    }

    public function getRowCount(): int
    {
        return $this->rows;
    }
}
