<?php

namespace App\Http\Requests;

use App\Support\ReplayFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReplayRequest extends FormRequest
{
    private const MAX_REPLAY_SIZE_KILOBYTES = 25 * 1024;

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
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_REPLAY_SIZE_KILOBYTES,
                // Content-Type headers and extensions are client controlled, so this
                // MIME check must be paired with the magic-byte check in after().
                'mimetypes:application/octet-stream,application/x-replay',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'game_version' => [
                'required',
                'string',
                'regex:/^\d+\.\d+\.\d+$/',
            ],
            'guild_id' => [
                'nullable',
                'integer',
                Rule::exists('guild_user', 'guild_id')
                    ->where('user_id', $userId),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');

                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    return;
                }

                if (! $this->hasReplayMagicBytes($file)) {
                    $validator->errors()->add(
                        'file',
                        'The file must be a valid replay file.',
                    );
                }
            },
        ];
    }

    private function hasReplayMagicBytes(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, ReplayFormat::MAGIC_BYTES_LENGTH) === ReplayFormat::MAGIC_BYTES;
        } finally {
            fclose($handle);
        }
    }
}
