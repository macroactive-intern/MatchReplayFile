<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReplayRequest extends FormRequest
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
        $userId = $this->user()?->getKey() ?? 0;

        return [
            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'guild_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('guild_user', 'guild_id')
                    ->where('user_id', $userId),
            ],
        ];
    }
}
