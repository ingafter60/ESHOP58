<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:api')->group(function () {
    Route::post('/receipt-attachments', [App\Http\Controllers\Api\ReceiptAttachment\ReceiptAttachmentController::class, 'store'])
        ->name('receipt-attachments.store');
    Route::get('/receipt-attachments/{id}', [App\Http\Controllers\Api\ReceiptAttachment\ReceiptAttachmentController::class, 'show'])
        ->where('id', '[0-9]+')
        ->name('receipt-attachments.show');
    Route::delete('/receipt-attachments/{id}', [App\Http\Controllers\Api\ReceiptAttachment\ReceiptAttachmentController::class, 'destroy'])
        ->where('id', '[0-9]+')
        ->name('receipt-attachments.destroy');
    Route::get('/receipts/{receiptId}/attachments', [App\Http\Controllers\Api\ReceiptAttachment\ReceiptAttachmentController::class, 'indexByReceipt'])
        ->where('receiptId', '[0-9]+')
        ->name('receipts.attachments.index');
});
