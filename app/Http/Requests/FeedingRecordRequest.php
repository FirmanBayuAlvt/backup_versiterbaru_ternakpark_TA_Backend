<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class FeedingRecordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'feed_id' => 'required|exists:feeds,id',
            'livestock_id' => 'nullable|exists:livestocks,id',
            'pen_id' => 'nullable|exists:pens,id',
            'quantity_kg' => 'required|numeric|min:0',
            'feeding_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ];
    }
}