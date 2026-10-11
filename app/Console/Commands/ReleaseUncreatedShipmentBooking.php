<?php

namespace App\Console\Commands;

use App\Models\ShipmentOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReleaseUncreatedShipmentBooking extends Command
{
    protected $signature = 'shipments:release-uncreated {order_no} {attempt_id}
        {--confirmed-not-created : Provider has confirmed no order was created}
        {--worker-stopped : The original booking worker is confirmed stopped}
        {--reason= : Provider support ticket or investigation evidence}';

    protected $description = 'Release a specific booking claim only after operator reconciliation';

    public function handle(): int
    {
        if (! $this->option('confirmed-not-created') || ! $this->option('worker-stopped')
            || strlen(trim((string) $this->option('reason'))) < 10) {
            $this->error('Wajib konfirmasi provider tidak membuat order, worker lama berhenti, dan alasan/bukti minimal 10 karakter.');

            return self::FAILURE;
        }
        try {
            DB::transaction(function () {
                $shipment = ShipmentOrder::where('order_no', $this->argument('order_no'))->lockForUpdate()->firstOrFail();
                $meta = $shipment->external_response_json ?? [];
                $attempt = $meta['booking_attempt'] ?? [];
                if ($shipment->external_order_id || ! $shipment->hasUnresolvedBooking()
                    || ! in_array($shipment->status, ['paid', 'booking_failed'], true)
                    || (string) ($attempt['id'] ?? $attempt['started_at'] ?? '') !== $this->argument('attempt_id')) {
                    throw new RuntimeException('Klaim tidak cocok, sudah diselesaikan, atau shipment tidak eligible.');
                }
                $meta['booking_release_history'][] = [
                    'attempt' => $attempt, 'released_at' => now()->toIso8601String(),
                    'reason' => trim($this->option('reason')),
                    'provider_confirmed_not_created' => true, 'worker_confirmed_stopped' => true,
                ];
                unset($meta['booking_attempt']);
                $shipment->update(['external_response_json' => $meta]);
            });
            $this->info('Klaim dilepas. Tidak ada booking baru dikirim; retry melalui alur admin.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
