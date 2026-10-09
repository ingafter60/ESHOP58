<?php

namespace App\Http\Controllers\Api\ReceiptAttachment;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiptAttachment\StoreReceiptAttachmentRequest;
use App\Models\ReceiptAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ReceiptAttachmentController
 *
 * Provides REST endpoints for attaching albarán (provider delivery-note)
 * files to a receipt record:
 *
 *   POST   /api/receipt-attachments          — upload & attach a file to a receipt
 *   GET    /api/receipt-attachments/{id}     — download a specific attachment
 *   GET    /api/receipts/{receiptId}/attachments — list all attachments for a receipt
 *   DELETE /api/receipt-attachments/{id}     — delete an attachment
 *
 * All endpoints are protected by the auth:api guard (token-based auth).
 *
 * File storage uses the `local` disk (storage/app/receipt_attachments/).
 * This aligns with the existing Laravel filesystem configuration and requires
 * no new external credentials.
 *
 * Security model:
 *   - Upload: any authenticated user (scope can be tightened when roles exist)
 *   - Download: any authenticated user (scope can be tightened with receipt
 *               ownership checks once the Receipt model is available)
 *   - Delete:  any authenticated user (same note as download)
 *
 * MIME type and size enforcement:
 *   - Application layer validates MIME via finfo_file (not just extension)
 *   - Size limit: 10 MB hard cap; extension whitelist: pdf, png, jpg, jpeg, webp
 *   - Violations return HTTP 400
 *
 * Error responses:
 *   400 — validation failure (invalid type, size, missing fields)
 *   401 — unauthenticated
 *   403 — action not permitted
 *   404 — attachment or receipt not found
 *   500 — storage failure
 */
class ReceiptAttachmentController extends Controller
{
    private const DISK = 'local';

    // -------------------------------------------------------------------------
    // POST /api/receipt-attachments
    // -------------------------------------------------------------------------
    /**
     * Upload and attach a new albarán file to a receipt.
     *
     * Request (multipart/form-data):
     *   - file:       the uploaded file (required)
     *   - receipt_id: integer ID of the target receipt (required)
     *
     * Returns 201 with the created attachment record on success.
     * Returns 400 if file type/size is invalid or receipt_id is missing.
     */
    public function store(StoreReceiptAttachmentRequest $request): JsonResponse
    {
        $uploadedFile = $request->file('file');
        $receiptId    = $request->input('receipt_id');

        // ── Step 1: server-side MIME detection (defense-in-depth) ──────────
        $detectedMime = $uploadedFile->getMimeType() ?? 'application/octet-stream';

        if (!ReceiptAttachment::isAllowedMime($detectedMime)) {
            return $this->errorResponse(
                'Invalid file type. Allowed: PDF, PNG, JPG, WebP.',
                400
            );
        }

        // ── Step 2: size guard (defense-in-depth) ────────────────────────────
        $sizeBytes = $uploadedFile->getSize();

        if (!ReceiptAttachment::isAllowedSize($sizeBytes)) {
            return $this->errorResponse(
                'File size exceeds the 10 MB limit.',
                400
            );
        }

        // ── Step 3: store the file ───────────────────────────────────────────
        $originalName = $uploadedFile->getClientOriginalName();
        $storedName   = Str::uuid()->toString() . '.' . $uploadedFile->getClientOriginalExtension();
        $subdir       = ReceiptAttachment::STORAGE_SUBDIR;

        try {
            Storage::disk(self::DISK)->putFileAs(
                $subdir,
                $uploadedFile,
                $storedName
            );
        } catch (\Exception $e) {
            report($e);
            return $this->errorResponse(
                'Failed to store the uploaded file.',
                500
            );
        }

        // ── Step 4: persist attachment record ────────────────────────────────
        $attachment = ReceiptAttachment::create([
            'receipt_id'    => $receiptId,
            'original_name' => $originalName,
            'stored_name'   => $storedName,
            'mime_type'     => $detectedMime,
            'size_bytes'    => $sizeBytes,
            'uploaded_by'   => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attachment uploaded successfully.',
            'data'    => $this->formatAttachment($attachment),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // GET /api/receipt-attachments/{id}
    // -------------------------------------------------------------------------
    /**
     * Download a specific attachment by ID.
     *
     * Returns the file as a streamed download (preserves original filename).
     * Returns 404 if the attachment record does not exist.
     * Returns 403 if the caller is not authorised (scope for future receipt-ownership check).
     */
    public function show(int $id): StreamedResponse|JsonResponse
    {
        $attachment = ReceiptAttachment::find($id);

        if (!$attachment) {
            return $this->errorResponse('Attachment not found.', 404);
        }

        // ── Authorization placeholder ────────────────────────────────────────
        // TODO (when Receipt model lands): check that $request->user()
        // has permission to view this receipt's attachments, e.g.:
        //   $receipt = $attachment->receipt;
        //   if (!$receipt || !$this->userCanAccessReceipt($request->user(), $receipt)) {
        //       return $this->errorResponse('Forbidden.', 403);
        //   }

        // ── Serve file from local disk ──────────────────────────────────────
        $diskPath = $attachment->diskPath();

        if (!Storage::disk(self::DISK)->exists($diskPath)) {
            // File is missing from disk but record exists — treat as gone
            return $this->errorResponse('Attachment file not found on server.', 404);
        }

        return Storage::disk(self::DISK)->download(
            $diskPath,
            $attachment->original_name,
            [
                'Content-Type'        => $attachment->mime_type,
                'Content-Disposition' => 'attachment; filename="' . $attachment->original_name . '"',
            ]
        );
    }

    // -------------------------------------------------------------------------
    // GET /api/receipts/{receiptId}/attachments
    // -------------------------------------------------------------------------
    /**
     * List all attachments for a given receipt.
     *
     * Returns 200 with an array of attachment objects.
     * Returns empty array if no attachments exist.
     */
    public function indexByReceipt(int $receiptId): JsonResponse
    {
        $attachments = ReceiptAttachment::where('receipt_id', $receiptId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ReceiptAttachment $a) => $this->formatAttachment($a));

        return response()->json([
            'success' => true,
            'data'    => $attachments,
        ]);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/receipt-attachments/{id}
    // -------------------------------------------------------------------------
    /**
     * Delete an attachment and its stored file.
     *
     * Returns 200 on success.
     * Returns 404 if the attachment does not exist.
     * Returns 500 if file deletion fails (record is left intact for retry).
     */
    public function destroy(int $id): JsonResponse
    {
        $attachment = ReceiptAttachment::find($id);

        if (!$attachment) {
            return $this->errorResponse('Attachment not found.', 404);
        }

        // Delete the physical file
        $diskPath = $attachment->diskPath();
        try {
            if (Storage::disk(self::DISK)->exists($diskPath)) {
                Storage::disk(self::DISK)->delete($diskPath);
            }
        } catch (\Exception $e) {
            report($e);
            return $this->errorResponse(
                'Failed to delete the attachment file.',
                500
            );
        }

        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    private function formatAttachment(ReceiptAttachment $attachment): array
    {
        return [
            'id'            => $attachment->id,
            'receipt_id'    => $attachment->receipt_id,
            'original_name' => $attachment->original_name,
            'mime_type'     => $attachment->mime_type,
            'size_bytes'    => $attachment->size_bytes,
            'uploaded_by'   => $attachment->uploaded_by,
            'created_at'   => $attachment->created_at?->toIso8601String(),
            'updated_at'   => $attachment->updated_at?->toIso8601String(),
            // Download URL — relative, to be prefixed by the application base URL
            'download_url'  => "/api/receipt-attachments/{$attachment->id}",
        ];
    }
}
