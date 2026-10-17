<?php

namespace App\Console\Commands;

use App\Services\ReminderDelivery;
use Illuminate\Console\Command;

class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders';

    protected $description = 'Kirim pengingat rapat yang jatuh tempo ke notifikasi website.';

    public function handle(ReminderDelivery $delivery): int
    {
        $this->info($delivery->deliverDue().' pengingat dikirim.');

        return self::SUCCESS;
    }
}
