<?php

namespace App\Events;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegisteredEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $notification; // 🟢 1. ປະກາດເປັນ public ເພື່ອໃຫ້ Reverb ແນບອອກໄປນຳ

    // 🟢 2. ປັບ Constructor ໃຫ້ຮັບ 2 ຄ່າ
  public function __construct(User $user, ?Notification $notification = null)
    {
        // ດຶງຂໍ້ມູນ User ພ້ອມ Eager Load ຂໍ້ມູນ roles ແລະ providers ໃຫ້ຄົບຖ້ວນ
        $this->user = User::with(['roles', 'providers'])->find($user->id);
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('users-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.registered';
    }
}