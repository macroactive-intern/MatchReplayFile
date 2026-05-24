<?php

namespace App\Http\Requests;

use App\Models\ReplayShare;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReplayShareRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => [
                'required',
                'string',
                Rule::in([
                    ReplayShare::SCOPE_LINK,
                    ReplayShare::SCOPE_GUILD,
                ]),
            ],
            'expiry_hours' => [
                'required',
                'integer',
                'min:1',
                'max:168',
            ],
        ];
    }
}
