<?php
// src/Service/NotificationService.php
namespace App\Service;

use App\Entity\User;
use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\AndroidConfig;

class NotificationService
{
    public function push(User $fromUser, User $toUser, string $title, string $text, string $url, string $type)
    {
        foreach ($toUser->getDevices() as $device) {
            if ($device->getActive() && !is_null($device->getToken())) {
                $tokens[] = $device->getToken();
            }
        }

        $tag = $type . '_' . $title;

        $config = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'priority' => 'high',
            'notification' => [
                'title' => $title,
                'body' => $text,
                'sound' => "default",
                'tag' => $tag,
                'icon' => $fromUser->getAvatar(),
                'click_action' => "FCM_PLUGIN_ACTIVITY",
            ],
            'data' => [
                'fromUser' => (string) $fromUser->getId(),
                'toUser' => (string) $toUser->getId(),
                'url' => $url,
                'icon' => $fromUser->getAvatar()
            ],
            'collapse_key' => $tag
        ]);

        $message = CloudMessage::new()->withAndroidConfig($config);

        $firebase = (new Firebase\Factory())->create();
        try {
            $messaging = $firebase->getMessaging();
            $report = $messaging->sendMulticast($message, $tokens);
            /*echo 'Successful sends: '.$report->successes()->count().PHP_EOL;
            echo 'Failed sends: '.$report->failures()->count().PHP_EOL;

            if ($report->hasFailures()) {
                foreach ($report->failures()->getItems() as $failure) {
                    echo $failure->error()->getMessage().PHP_EOL;
                }
            }*/
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // echo "Error al enviar la notificación";
        }
    }

    public function pushTopic(User $fromUser, string $topic, string $title, string $text, string $url)
    {
        //fromUser debe ser frikiradar, el user 1 y el toUser un string con el 'topic'
        $config = AndroidConfig::fromArray([
            'topic' => $topic,
            'notification' => [
                'title' => $title,
                'body' => $text,
                'sound' => "default",
                'tag' => $topic,
                'click_action' => "FCM_PLUGIN_ACTIVITY",
            ],
            'data' => [
                'fromUser' => (string) $fromUser->getId(),
                'url' => $url,
                'icon' => $fromUser->getAvatar()
            ],
            'collapse_key' => $topic
        ]);

        $message = CloudMessage::new()->withAndroidConfig($config);

        $firebase = (new Firebase\Factory())->create();
        try {
            $messaging = $firebase->getMessaging();
            $messaging->send($message);
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // echo "Error al enviar la notificación";
        }
    }
}
