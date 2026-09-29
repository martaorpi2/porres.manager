<?php

namespace App\Services;

use App\Models\MarketRate;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestEvent;
use App\Models\QuoteDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MarketRateUpdateService
{
    public function persistFromRequest(MarketRate $entry, Request $request, bool $lockPurchaseRequestId = false): MarketRate
    {
        $this->assertValidQuoteUploads($request);

        $purchaseRequestId = $lockPurchaseRequestId
            ? (int) $entry->purchase_request_id
            : (int) ($request->input('purchase_request_id') ?: $entry->purchase_request_id);

        $dataToSave = [
            'supplier_id' => $request->input('supplier_id', $entry->supplier_id),
            'purchase_request_id' => $purchaseRequestId,
            'date' => $request->input('date', $entry->date),
            'delivery_date' => $request->input('delivery_date') ?: null,
            'delivery_term' => $request->input('delivery_term'),
            'payment_method' => $request->input('payment_method'),
            'validity_term' => $request->input('validity_term'),
            'document_files' => $this->mergeMarketRateDocumentFiles($request, $entry),
            'reference_links' => $this->normalizeReferenceLinksField($request->input('reference_links')),
        ];

        $selectedItems = $request->input('selected_quote_items');
        $calculatedTotal = $this->sumTotalFromSelectedQuoteItemsJson(is_string($selectedItems) ? $selectedItems : null);
        $parsedManual = $this->parseTotalAmountInput($request->input('total_amount'));
        if ($parsedManual !== null) {
            $dataToSave = $this->withUndiscriminatedTotal($dataToSave, $parsedManual);
        } else {
            $fallback = $calculatedTotal > 0
                ? $calculatedTotal
                : (float) $entry->effectiveTotalWithVat();
            $dataToSave = $this->withUndiscriminatedTotal($dataToSave, $fallback);
        }

        $entry->update($dataToSave);
        $this->processSelectedQuoteItems($entry->fresh(), $request, true);

        return $entry->fresh(['quoteDetails.product', 'supplier', 'purchaseRequest']);
    }

    public function recordAndNotifyAfterEdit(MarketRate $updated, float $oldQuoteTotal, ?User $user): void
    {
        $updated->refresh();
        $updated->loadMissing([
            'supplier',
            'quoteDetails',
            'purchaseRequest.marketRates.quoteDetails',
            'purchaseRequest.details.selectedMarketRate.quoteDetails',
        ]);

        $purchaseRequest = $updated->purchaseRequest;
        if (! $purchaseRequest) {
            return;
        }

        $newQuoteTotal = $updated->effectiveTotalWithVat();
        $oldRequestTotal = (float) ($purchaseRequest->total_amount ?? 0);
        $newRequestTotal = $purchaseRequest->effectiveTotalForAuthorizationLimits();

        if (abs($newRequestTotal - $oldRequestTotal) >= 0.005) {
            $purchaseRequest->update(['total_amount' => $newRequestTotal]);
        }

        PurchaseRequestEvent::record(
            $purchaseRequest,
            PurchaseRequestEvent::EVENT_QUOTATION_EDITED,
            $user?->id,
            [
                'market_rate_id' => $updated->id,
                'supplier_id' => $updated->supplier_id,
                'supplier_name' => $updated->supplier->company_name ?? null,
                'old_quote_total' => round($oldQuoteTotal, 2),
                'new_quote_total' => round($newQuoteTotal, 2),
                'old_request_total' => round($oldRequestTotal, 2),
                'new_request_total' => round((float) ($purchaseRequest->fresh()->total_amount ?? $newRequestTotal), 2),
            ]
        );

        if (round($oldQuoteTotal, 2) !== round($newQuoteTotal, 2)) {
            PurchaseRequestNotificationService::notifyAdministratorQuotationAmountChanged(
                $purchaseRequest->fresh(),
                $updated,
                $oldQuoteTotal,
                $newQuoteTotal,
                $oldRequestTotal,
                (float) ($purchaseRequest->fresh()->total_amount ?? $newRequestTotal),
                $user
            );
        }
    }

    public function abortIfCannotEditLoadedQuotation(PurchaseRequest $purchaseRequest, ?User $user): void
    {
        if (! $user instanceof User || ! $user->canEditLoadedPurchaseRequestQuotations()) {
            abort(403, 'No tiene permiso para editar cotizaciones cargadas.');
        }

        if ($purchaseRequest->hasGeneratedPurchaseOrder()) {
            abort(403, 'No se puede editar la cotización: ya se generó una orden de compra.');
        }
    }

    private function processSelectedQuoteItems(MarketRate $marketRate, Request $request, bool $isUpdate = false): void
    {
        if ($isUpdate) {
            $marketRate->quoteDetails()->delete();
        }

        $manualTotal = $this->parseTotalAmountInput($request->input('total_amount'));
        $selectedItems = $request->input('selected_quote_items');

        if (! $selectedItems) {
            if ($manualTotal !== null) {
                $marketRate->update($this->withUndiscriminatedTotal([], $manualTotal));
            }

            return;
        }

        $items = json_decode($selectedItems, true);
        if (! is_array($items) || $items === []) {
            if ($manualTotal !== null) {
                $marketRate->update($this->withUndiscriminatedTotal([], $manualTotal));
            }

            return;
        }

        $totalAmount = 0.0;
        foreach ($items as $itemData) {
            if (! is_array($itemData)) {
                continue;
            }
            $productId = $itemData['product_id'] ?? null;
            $quantity = (float) ($itemData['quantity'] ?? 0);
            $unitPrice = (float) ($itemData['unit_price'] ?? 0);
            $productDescription = isset($itemData['product_description']) ? trim((string) $itemData['product_description']) : null;
            if ($productDescription === '') {
                $productDescription = null;
            }
            if (! $productId || $quantity <= 0 || $unitPrice < 0) {
                continue;
            }

            QuoteDetail::create([
                'market_rate_id' => $marketRate->id,
                'product_id' => $productId,
                'product_description' => $productDescription,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ]);
            $totalAmount += $quantity * $unitPrice;
        }

        $finalTotal = $totalAmount;
        if ($finalTotal <= 0 && $manualTotal !== null && $manualTotal > 0) {
            $finalTotal = $manualTotal;
        }

        $marketRate->refresh();
        $marketRate->update($this->withUndiscriminatedTotal([], $finalTotal));
    }

    /**
     * @param  array<string, mixed>  $dataToSave
     * @return array<string, mixed>
     */
    private function withUndiscriminatedTotal(array $dataToSave, float $total): array
    {
        $dataToSave['total_amount'] = $total;
        $dataToSave['vat_amount'] = 0;
        $dataToSave['total_amount_with_vat'] = $total;

        return $dataToSave;
    }

    private function parseTotalAmountInput($raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $v = $raw;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') {
                return null;
            }
            if (str_contains($v, ',')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            }
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    private function sumTotalFromSelectedQuoteItemsJson(?string $selectedItems): float
    {
        $calculatedTotal = 0.0;
        if (! $selectedItems) {
            return $calculatedTotal;
        }
        $items = json_decode($selectedItems, true);
        if (! is_array($items)) {
            return $calculatedTotal;
        }
        foreach ($items as $itemData) {
            if (! is_array($itemData)) {
                continue;
            }
            $calculatedTotal += ((float) ($itemData['quantity'] ?? 0)) * ((float) ($itemData['unit_price'] ?? 0));
        }

        return $calculatedTotal;
    }

    private function assertValidQuoteUploads(Request $request): void
    {
        if (! $request->hasFile('document_files')) {
            return;
        }
        $files = $request->file('document_files');
        if (! is_array($files)) {
            $files = [$files];
        }
        $validator = Validator::make(
            ['document_files' => $files],
            ['document_files' => 'array', 'document_files.*' => 'file|max:10240|mimes:pdf,jpeg,jpg,png,gif,webp,doc,docx']
        );
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * @return list<string>
     */
    private function mergeMarketRateDocumentFiles(Request $request, ?MarketRate $existing): array
    {
        $paths = [];
        if ($existing && is_array($existing->document_files)) {
            $paths = $existing->document_files;
        }
        foreach ((array) $request->input('clear_document_files', []) as $cleared) {
            if ($cleared === null || $cleared === '') {
                continue;
            }
            $paths = array_values(array_filter($paths, fn ($p) => $p !== $cleared));
            try {
                Storage::disk('public')->delete($cleared);
            } catch (\Throwable $e) {
                Log::warning('market_rate: no se pudo borrar archivo', ['path' => $cleared, 'message' => $e->getMessage()]);
            }
        }
        if ($request->hasFile('document_files')) {
            $uploaded = $request->file('document_files');
            $list = is_array($uploaded) ? $uploaded : [$uploaded];
            foreach ($list as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $paths[] = $file->store('cotizaciones', 'public');
                }
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private function normalizeReferenceLinksField($raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $text = trim((string) $raw);
        if ($text === '') {
            return null;
        }
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $kept = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! preg_match('#^https?://#i', $line)) {
                continue;
            }
            $kept[] = $line;
        }

        return $kept === [] ? null : implode("\n", $kept);
    }
}
