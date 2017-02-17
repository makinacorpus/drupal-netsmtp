<?php

namespace MakinaCorpus\Drupal\NetSmtp;

/**
 * Raw message formatter.
 */
class RawFormatter implements \MailSystemInterface
{
    /**
     * {@inheritdoc}
     */
    public function format(array $message)
    {
        if (is_array($message['body'])) {
            $message['body'] = implode("\n", $message['body']);
        } else {
            $message['body'] =(string)$message['body'];
        }

        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function mail(array $message)
    {
        watchdog('netsmtp', "I am not meant to send messages, sorry", [], WATCHDOG_ERROR);

        return false;
    }
}
