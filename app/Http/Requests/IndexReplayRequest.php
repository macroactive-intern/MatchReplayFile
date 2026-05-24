<?php

namespace App\Http\Requests;

use App\Models\Replay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexReplayRequest extends FormRequest
{
    private const FILTERABLE_STATUSES = [
        Replay::STATUS_UPLOADED,
        Replay::STATUS_PROCESSING,
        Replay::STATUS_READY,
        Replay::STATUS_FAILED,
    ];

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
            'status' => [
                'sometimes',
                'string',
                Rule::in(self::FILTERABLE_STATUSES),
            ],
            'game_version' => [
                'sometimes',
                'string',
                'max:20',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function filterStatus(): ?string
    {
        return $this->filled('status')
            ? $this->string('status')->toString()
            : null;
    }

    public function filterGameVersion(): ?string
    {
        return $this->filled('game_version')
            ? $this->string('game_version')->toString()
            : null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
