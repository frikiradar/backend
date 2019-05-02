<?php
// src/Service/NotificationService.php
namespace App\Service;

use App\Entity\User;
use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as PushNotification;
use Kreait\Firebase\Messaging\AndroidConfig;

class NotificationService
{
    public function push(User $fromUser, User $toUser, string $title, string $text, string $url, string $type)
    {

        $devices = $toUser->getDevices();
        foreach ($devices as $device) {
            if ($device->getActive() && !is_null($device->getToken())) {
                $notification = PushNotification::create($title, $text);
                $data = [
                    'fromUser' => (string)$fromUser->getId(),
                    'toUser' => (string)$toUser->getId(),
                    'url' => $url
                ];

                $config = AndroidConfig::fromArray([
                    'ttl' => '3600s',
                    'priority' => 'high',
                    'notification' => [
                        'title' => $title,
                        'body' => $text,
                        'sound' => "default",
                        // 'tag' => $type . '_' . $title,
                        'click_action' => "FCM_PLUGIN_ACTIVITY"
                    ],
                ]);

                $message = CloudMessage::withTarget('token', $device->getToken())
                    ->withNotification($notification) // optional
                    ->withData($data)
                    ->withAndroidConfig($config);

                $firebase = (new Firebase\Factory())->create();
                $messaging = $firebase->getMessaging();
                @$messaging->send($message);
            }
        }
    }
}
