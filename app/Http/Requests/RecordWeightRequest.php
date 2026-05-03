<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class RecordWeightRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'weight_kg' => 'required|numeric|min:0|max:300',
            'record_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
