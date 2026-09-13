<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['achievements', 'obstacles'] as $field) {
            if (is_string($this->input($field))) {
                $value = json_decode($this->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $value]);
                }
            }
        }
    }

    public function rules(): array
    {
        if (str_ends_with($this->path(), '/status')) {
            return ['status' => 'required|in:approved,disapproved', 'evaluation' => 'nullable|string', 'content' => 'nullable|string'];
        }
        if ($this->is('api/reports/daily*')) {
            $rules = ['content' => 'required|string'];
            if ($this->isMethod('POST')) {
                $rules['date'] = 'required|date_format:Y-m-d';
                $rules['user_id'] = $this->user()?->role === 'admin' ? 'required|integer|exists:users,id' : 'nullable';
            }

            return $rules;
        }
        $rules = ['summary' => 'required|string', 'achievements' => 'nullable|array', 'obstacles' => 'nullable|array'];
        if ($this->isMethod('POST')) {
            $rules += ['month' => 'required|integer|between:1,12', 'year' => 'required|integer|between:1900,9999'];
        }

        return $rules;
    }
}
