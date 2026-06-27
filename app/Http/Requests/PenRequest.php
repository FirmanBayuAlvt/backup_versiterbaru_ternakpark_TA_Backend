<?php

namespace App\Http\Requests;

use App\Models\Pen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $penId = $this->route('pen');

        return [
            'name'     => 'required|string|max:100',
            'code'     => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('pens', 'code')->ignore($penId),
            ],
            'category' => ['required', Rule::in(Pen::CATEGORIES)],
            'abk'      => ['nullable', Rule::in(Pen::ABK_OPTIONS)],
            'capacity' => 'required|integer|min:1',
            'status'   => 'nullable|in:active,inactive',
        ];
    }

    /**
     * Get custom error messages for validator failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.in' => 'Kategori kandang harus salah satu dari: ' . implode(', ', Pen::CATEGORIES),
            'abk.in'      => 'ABK harus salah satu dari: ' . implode(', ', Pen::ABK_OPTIONS),
        ];
    }
}
