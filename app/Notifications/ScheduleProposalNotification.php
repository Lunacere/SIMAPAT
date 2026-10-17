<?php

namespace App\Notifications;

use App\Models\ScheduleProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleProposalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public string $proposalStatus;

    public function __construct(public ScheduleProposal $proposal)
    {
        $this->proposalStatus = $proposal->status;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function viaConnections(): array
    {
        return ['database' => 'sync', 'mail' => config('queue.default')];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $notifiable->refresh();
        $this->proposal->refresh();
        if ($channel === 'mail' && ($this->proposal->status !== $this->proposalStatus || $this->proposal->meeting->status !== 'scheduled' || $this->proposal->meeting_version !== $this->proposal->meeting->version)) {
            return false;
        }

        return $notifiable->can('viewNegotiation', $this->proposal->meeting()->firstOrFail());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $accepted = $this->proposal->status === 'accepted';

        return (new MailMessage)
            ->subject(($accepted ? 'Jadwal rapat disepakati: ' : 'Ajuan jadwal rapat: ').$this->proposal->meeting->title)
            ->greeting('Halo, '.$notifiable->name)
            ->line($accepted ? 'Kedua pihak telah menyepakati jadwal rapat berikut.' : 'Ada ajuan jadwal rapat yang memerlukan tanggapan Anda.')
            ->line($this->proposal->meeting->title)
            ->line('Mulai: '.$this->proposal->starts_at->format('d-m-Y H:i').' WIB')
            ->line('Selesai: '.$this->proposal->ends_at->format('d-m-Y H:i').' WIB')
            ->line('Tempat/media: '.$this->proposal->meeting->location)
            ->action('Lihat negosiasi jadwal', route('negotiations.show', $this->proposal->meeting))
            ->line($accepted ? 'Silakan buka SIMAPAT untuk melihat detail kesepakatan.' : 'Masuk ke SIMAPAT untuk menyetujui atau mengajukan jadwal alternatif.');
    }

    public function toDatabase(object $notifiable): array
    {
        return ['kind' => 'schedule_proposal', 'meeting_id' => $this->proposal->meeting_id,
            'meeting_version' => $this->proposal->meeting_version, 'proposal_id' => $this->proposal->id,
            'status' => $this->proposal->status, 'starts_at' => $this->proposal->starts_at->format('d-m-Y H:i')];
    }
}
