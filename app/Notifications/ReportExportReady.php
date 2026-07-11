<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivered to the admin who requested a big CSV export once the queued job
 * has written the file (M13). The URL is the admin-gated download route.
 */
class ReportExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reportTitle,
        public readonly string $filename,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (FcmChannel::isConfigured()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        [$title, $body] = $this->message();

        return [
            'type' => 'report_export_ready',
            'filename' => $this->filename,
            'title' => $title,
            'body' => $body,
            'url' => route('admin.exports.download', ['file' => $this->filename]),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        [$title, $body] = $this->message();

        return [
            'title' => $title,
            'body' => $body,
            'url' => route('admin.exports.download', ['file' => $this->filename]),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function message(): array
    {
        return [
            __('Report export ready'),
            __(':report is ready to download.', ['report' => $this->reportTitle]),
        ];
    }
}
