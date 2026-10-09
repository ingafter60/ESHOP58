<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration draft — NOT for production execution.
 * Execute only after review and approval.
 *
 * Table: receipt_attachments
 * Purpose: attach provider delivery-note (albarán) files to a receipt record.
 *
 * Columns:
 *   id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
 *   receipt_id       BIGINT UNSIGNED NOT NULL  — FK to receipts.id (to be created by #2/#6 lane)
 *   original_name    VARCHAR(255) NOT NULL    — original uploaded filename
 *   stored_name      VARCHAR(255) NOT NULL    — server-side generated storage name (UUID)
 *   mime_type        VARCHAR(100) NOT NULL    — detected MIME type
 *   size_bytes       BIGINT UNSIGNED NOT NULL — file size in bytes
 *   uploaded_by      BIGINT UNSIGNED NULL     — FK to users.id (nullable for now)
 *   created_at       TIMESTAMP
 *   updated_at       TIMESTAMP
 *
 * Indexes:
 *   receipt_id  — for fast lookups by receipt
 *
 * Constraints:
 *   FK receipt_id references receipts(id) ON DELETE CASCADE
 *   FK uploaded_by references users(id) ON DELETE SET NULL
 *
 * Disk: local (storage/app/receipt_attachments/)
 * Rationale: aligns with existing 'local' disk in config/filesystems.php
 * Mime whitelist (application-layer enforcement):
 *   application/pdf, image/png, image/jpeg, image/webp
 * Max size: 10 MB
 *
 * NEEDS_MIGRATION=receipt_attachments:receipt_id(BIGINT,NOT NULL), original_name, stored_name, mime_type, size_bytes, uploaded_by(NULL), created_at, updated_at; FK→receipts(id); INDEX(receipt_id)
 */
class CreateReceiptAttachmentsTable extends Migration
{
    public function up(): void
    {
        // NOTE: This migration must run AFTER the receipts table exists.
        // The receipts table will be created by PR #391 (lane #2).
        // Coordinate migration order with that lane before deployment.

        Schema::create('receipt_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('receipt_id');
            $table->string('original_name', 255);
            $table->string('stored_name', 255);       // UUID-based name
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('receipt_id', 'idx_receipt_attachments_receipt_id');
            // FK added conditionally — requires receipts table to exist
            $table->foreign('receipt_id', 'fk_receipt_attachments_receipt')
                  ->references('id')->on('receipts')
                  ->onDelete('cascade');
            $table->foreign('uploaded_by', 'fk_receipt_attachments_uploader')
                  ->references('id')->on('users')
                  ->onDelete('set_null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_attachments');
    }
}
