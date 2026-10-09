<?php

namespace App\Http\Requests\ReceiptAttachment;

use App\Models\ReceiptAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form request for uploading a receipt attachment (albarán).
 *
 * Validation rules:
 *   - file: required, file, mimetype OR extension whitelist (pdf/png/jpg/jpeg/webp), max 10240 KB
 *   - receipt_id: required, integer, positive
 */
class StoreReceiptAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced at the controller level via auth:api middleware.
        // Additional role checks can be added here when an roles/permissions model exists.
        return true;
    }

    public function rules(): array
    {
        $mimeList = implode(',', [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/webp',
        ]);

        $maxKb = ReceiptAttachment::MAX_SIZE_BYTES / 1024; // 10240 KB

        return [
            'file'      => [
                'required',
                'file',
                // Extension-level check (defense-in-depth alongside MIME type check in controller)
                'mimes:pdf,png,jpg,jpeg,webp',
                "max:{$maxKb}",
            ],
            'receipt_id' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'  => 'No file was provided.',
            'file.file'      => 'The uploaded item is not a valid file.',
            'file.mimes'     => 'Only PDF, PNG, JPG, and WebP files are allowed.',
            'file.max'       => 'The file must not exceed 10 MB.',
            'receipt_id.required' => 'receipt_id is required.',
            'receipt_id.integer'  => 'receipt_id must be an integer.',
            'receipt_id.min'      => 'receipt_id must be a positive integer.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 400)
        );
    }
}
