<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class FeedRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:feeds,name,' . $this->route('feed'),
            'category' => 'required|in:silase,cf_jember,jagung_halus,konsentrat',
            'current_stock' => 'sometimes|numeric|min:0',
            'price_per_kg' => 'nullable|numeric|min:0',
            'unit' => 'sometimes|string|max:10',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
