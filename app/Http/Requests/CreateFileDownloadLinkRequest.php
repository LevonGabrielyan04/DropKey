<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @method array{path: string} validated()
 */
class CreateFileDownloadLinkRequest extends FormRequest
{
    /**
     * Object keys created by chat attachment uploads (uploads/{userId}/{ulid}.{ext}).
     */
    public const PATH_PATTERN = '/^uploads\/\d+\/[0-9A-HJKMNP-TV-Z]{26}\.[a-z0-9]{1,20}$/i';

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
        return [
            'path' => ['required', 'string', 'max:255', 'regex:'.self::PATH_PATTERN],
        ];
    }
}
