<?php

class NetSmtp_MailSystemProxy implements MailSystemInterface
{
    /**
     * Fallback identifier
     */
    const MAIL_DEFAULT = 'default';

    /**
     * Default mail class
     */
    const DEFAULT_CLASS = 'DefaultMailSystem';

    /**
     * @var MailSystemInterface
     */
    protected $mailer;

    /**
     * This will be merge with the $message structure before formatting and
     * before mailing, allowing to set extra data for formatter and mailer
     *
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $classes;

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->options = array();
        $this->classes = variable_get('netsmtp_proxy');
        $this->mailer = new NetStmp_DrupalMailSystem();
    }

    /**
     * Get formatter for message type
     *
     * @param string $type
     *   Configuration identifier
     *
     * @return MailSystemInterface
     */
    protected function getFormatter($message)
    {
        $candidates = array(
            $message['module'] . '_' . $message['key'],
            $message['module'],
            self::MAIL_DEFAULT,
        );

        foreach ($candidates as $key) {
            if (isset($this->classes[$key])) {
                $class = $this->classes[$key];
                if (!class_exists($class)) {
                    watchdog('netsmtp_proxy_', "Class @class does not exist, fallback to Drupal default", array('@class' => $class), WATCHDOG_WARNING);
                    $class = self::DEFAULT_CLASS;
                } else {
                    break;
                }
            }
        }

        if (empty($class)) {
            $class = self::DEFAULT_CLASS;
        }

        return new $class();
    }

    public function format(array $message) 
    {
        return $this->getFormatter($message)->format($message + $this->options);
    }

    public function mail(array $message)
    {
        if (!$this->mailer->mail($message + $this->options)) {
            trigger_error(sprintf("Error while sending mail, HEADERS: <pre>%s</pre>, MAILER: <pre>%s</pre>", print_r($message['headers'], true), get_class($this->mailer)), E_USER_ERROR);
            return false;
        }
        return true;
    }
}
