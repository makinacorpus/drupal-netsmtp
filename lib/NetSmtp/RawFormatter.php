<?php

class NetSmtp_RawFormatter implements MailSystemInterface
{
    public function format(array $message) 
    {
        if (is_array($message['body'])) {
            $message['body'] = implode("\n", $message['body']);
        } else {
            $message['body'] =(string)$message['body'];
        }
        return $message;
    }

    public function mail(array $message)
    {
        watchdog('netsmtp', "I am not meant to send messages, sorry", array(), WATCHDOG_ERROR);
        return false;
    }
}
