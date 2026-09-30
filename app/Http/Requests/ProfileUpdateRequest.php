<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function messages(): array
    {
        return [
            'orcid_url.regex' => 'Informe uma URL válida do ORCID. Exemplo: https://orcid.org/0000-0000-0000-0000',
            'lattes_url.regex' => 'Informe uma URL válida do Lattes. Exemplo: http://lattes.cnpq.br/1234567890123456',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'orcid_url' => ['nullable', 'string', 'max:255', 'regex:/^https?:\/\/orcid\.org\/[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$/'],
            'lattes_url' => ['nullable', 'string', 'max:255', 'regex:/^https?:\/\/lattes\.cnpq\.br\/[0-9]+$/'],
        ];
    }
}
