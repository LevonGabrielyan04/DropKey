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
     * Object keys for chat attachment uploads.
     *
     * New uploads use uploads/{userId}/{uuidv7}. Legacy ULID keys (with or
     * without a .{ext} suffix) remain accepted so older objects stay downloadable.
     */
    public const PATH_PATTERN = '/^uploads\/\d+\/(?:[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}|[0-9A-HJKMNP-TV-Z]{26})(?:\.[a-z0-9]{1,20})?$/i';

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
