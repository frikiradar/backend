<?php
// src/Service/MailingService.php
namespace App\Service;

include_once __DIR__ . '/../../../httpdocs/wp-load.php';

use App\Entity\User;

define('MP_SUBSCRIBERS_TABLE', 'b11ZjdM_mailpoet_subscribers');

class MailingService
{
    var $name, $email, $date, $list_name, $list_description, $list, $lists;
    var $options = array(
        'send_confirmation_email' => false,
        'schedule_welcome_email' => false
    );

    function getLists()
    {
        return \MailPoet\API\API::MP('v1')->getLists();
    }

    function getSubscriber()
    {
        try {
            return \MailPoet\API\API::MP('v1')->getSubscriber($this->email); // $identifier can be either a subscriber ID or e-mail
        } catch (Exception $exception) {
            // echo $exception->getMessage();
            return false;
        }
    }

    function getSubscriberLists()
    {
        $lists_array = $this->getSubscriber()['subscriptions'];
        $lists = array();
        foreach ($lists_array as $list) {
            if ($list['status'] == 'subscribed') {
                $lists[] = $list['segment_id'];
            }
        }

        return $lists;
    }
    //Escritura-----------------------------------------------------//
    function set()
    {
        // if ($this->getSubscriber()) {
        // return (!empty($this->lists)) ? $this->subscribeToLists() : $this->subscribeToList();
        // } else {
        if ($this->addSubscriber()) {
            return (!empty($this->lists)) ? $this->subscribeToLists() : $this->subscribeToList();
        }
        // }
    }

    function update()
    {
        //recogemos las listas antiguas
        $old_lists = $this->getSubscriberLists();
        ksort($old_lists);
        $new_lists = $this->lists;
        ksort($new_lists);
        //las recorremos y comparamos para ver cuales quitar
        $this->lists = array_diff($old_lists, $new_lists);
        $this->unsubscribeFromLists();
        //y cuales añadir
        $this->lists = array_diff($new_lists, $old_lists);
        $this->subscribeToLists();
        return true;
    }

    function addList()
    {
        try {
            $list_data = array(
                'name' => $this->list_name,
                'description' => $this->list_description
            );
            return \MailPoet\API\API::MP('v1')->addList($list_data);
        } catch (Exception $exception) {
            // echo $exception->getMessage();
            return false;
        }
    }

    function addSubscriber()
    {
        try {
            $subscriber_data = array(
                'email' => $this->email,
                'first_name' => $this->name
            );
            return \MailPoet\API\API::MP('v1')->addSubscriber($subscriber_data, $this->lists, $this->options);
        } catch (Exception $exception) {
            // echo $exception->getMessage();
            return false;
        }
    }

    function subscribeToList()
    {
        try {
            return \MailPoet\API\API::MP('v1')->subscribeToList($this->email, $this->list, $this->options);
        } catch (Exception $exception) {
            // echo $exception->getMessage();
            return false;
        }
    }

    function subscribeToLists()
    {
        try {
            return \MailPoet\API\API::MP('v1')->subscribeToLists($this->email, $this->lists, $this->options);
        } catch (Exception $exception) {
            // echo $exception->getMessage();
            return false;
        }
    }

    //Borrado---------------------------------------------------------//
    function remove()
    {
        /*$query = "DELETE FROM mailing WHERE email='$this->email'";
        if ($this->_db->query($query))
            return true;
        return false;*/ }

    function unsubscribeFromList()
    {
        try {
            if (in_array($this->list, $this->getSubscriberLists())) {
                return \MailPoet\API\API::MP('v1')->unsubscribeFromList($this->email, $this->list);
            }
        } catch (Exception $exception) {
            // echo $exception->getMessage();
            return false;
        }
    }

    function unsubscribeFromLists()
    {
        try {
            return \MailPoet\API\API::MP('v1')->unsubscribeFromLists($this->email, $this->lists);
        } catch (Exception $exception) {
            // echo $exception->getMessage();
            return false;
        }
    }
}
