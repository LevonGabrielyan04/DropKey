<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @method array{filename: string, content_type: string, size: int} validated()
 */
class CreateFileUploadLinkRequest extends FormRequest
{
    /**
     * Content types signed into temporary upload URLs.
     *
     * E2EE uploads are ciphertext only; force application/octet-stream so
     * objects cannot be served with an attacker-chosen type (XSS / sniffing).
     *
     * @var list<string>
     */
    public const ALLOWED_CONTENT_TYPES = [
        'application/octet-stream',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxFileBytes = (int) config('filesystems.upload.max_file_bytes');

        return [
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', Rule::in(self::ALLOWED_CONTENT_TYPES)],
            'size' => ['required', 'integer', 'min:1', 'max:'.$maxFileBytes],
        ];
    }
}
