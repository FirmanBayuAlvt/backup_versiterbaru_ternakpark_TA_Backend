<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class PredictionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'livestock_id' => 'required|exists:livestocks,id',
            'prediction_days' => 'required|integer|min:7|max:90',
        ];
    }
}