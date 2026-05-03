<?php

namespace App\Http\Requests;

use App\Models\Pen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20|unique:pens,code,' . $this->route('pen'),
            'category' => ['required', Rule::in(Pen::CATEGORIES)],
            'abk' => ['nullable', Rule::in(Pen::ABK_OPTIONS)],
            'capacity' => 'required|integer|min:1',
            'status' => 'nullable|in:active,inactive',
        ];
        return $rules;
    }

    public function messages(): array
    {
        return [
            'category.in' => 'Kategori kandang harus salah satu dari: ' . implode(', ', Pen::CATEGORIES),
            'abk.in' => 'ABK harus salah satu dari: ' . implode(', ', Pen::ABK_OPTIONS),
        ];
    }
}
