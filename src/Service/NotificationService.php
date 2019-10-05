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
                    'fromUser' => (string) $fromUser->getId(),
                    'toUser' => (string) $toUser->getId(),
                    'url' => $url,
                    'icon' => $fromUser->getAvatar()
                ];

                $tag = $type . '_' . $title;

                $config = AndroidConfig::fromArray([
                    'ttl' => '3600s',
                    'priority' => 'high',
                    'notification' => [
                        'title' => $title,
                        'body' => $text,
                        'sound' => "default",
                        'tag' => $tag,
                        'click_action' => "FCM_PLUGIN_ACTIVITY",
                    ],
                    "collapse_key" => $tag
                ]);

                $message = CloudMessage::withTarget('token', $device->getToken())
                    ->withNotification($notification) // optional
                    ->withData($data)
                    ->withAndroidConfig($config);

                $firebase = (new Firebase\Factory())->create();
                try {
                    $messaging = $firebase->getMessaging();
                    $messaging->send($message);
                } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
                    // echo "Error al enviar la notificación";
                }
            }
        }
    }
}
