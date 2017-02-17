<?php

namespace MakinaCorpus\Drupal\NetSmtp;

/**
 * Net_SMTP mail system connector.
 *
 * Beware that this class awaits an already formatted MIME mail as input.
 * This means you probably need another mail system such as the MimeMail
 * module in order to generate the fully compliant MIME version.
 *
 * Most functions are protected, so that anyone that wants to change some
 * behavior can still extend this object and modify whatever they want.
 */
class DrupalMailSystem implements \MailSystemInterface
{
    /**
     * Default provider key
     */
    const PROVIDER_DEFAULT = 'default';

    /**
     * Provider definition key in messages
     */
    const PROVIDER_KEY = 'smtp_provider';

    /**
     * Default SSL port
     */
    const DEFAULT_SSL_PORT = 465;

    /**
     * Drupal to explode regex
     */
    const REGEX_TO = '';

    /**
     * @var \PEAR
     */
    private $PEAR;

    /**
     * Default constructor
     */
    public function __construct()
    {
        // Fuck hate PEAR.
        $this->PEAR = new \PEAR();
    }

    /**
     * Attempt to find email addresses from input
     *
     * @param string $string
     *
     * @return string[]
     *
     * @see drupal_mail()
     *   For input of what this function is supposed to accept.
     */
    protected function catchAddressesInto($string)
    {
        $ret = [];

        if (empty($string)) {
            return null; // Please bitch... Not my problem
        }

        // This should be enough to remove "Name".
        $string = preg_replace('/"[^\"]+"/', '', $string);

        // And thus, there is no risk anymore to find ',' except as separator
        foreach (explode(",", $string) as $addr) {
            $m = [];
            if (preg_match('/<([^\>]+)?>/', $addr, $m)) {
                $ret[] = trim($m[1]);
            } else {
                $ret[] = trim($addr);
            }
        }

        return $ret;
    }

    /**
     * Format an error and send it to watchdog
     *
     * @param mixed $e
     */
    protected function setError($e, $level = WATCHDOG_ERROR)
    {
        if (is_string($e)) {
            $message = $e;
        } else if ($e instanceof \PEAR_Error) {
            // God PEAR is so 90's, but I have to use it because no other
            // viable PHP SMTP library exists outside of Net_SMTP. Even
            // the Roundcube webmail client understood it.
            if ($debug = $e->getDebugInfo()) {
                $message = $e->getMessage() . ', DEBUG:<br/><pre>' . print_r($debug, true) . '</pre>';
            } else {
                $message = $e->getMessage();
            }
        } else if ($e instanceof Exception) {
            $message = 'Exception ' . get_class($e) . ': ' . $e->getMessage() . '<br/><pre>' . $e->getTraceAsString() . '</pre>';
        } else {
            $message = 'UNKNOWN ERROR, DEBUG:<br/><pre>' . print_r($e, true) . '</pre>';
        }

        watchdog('netsmtp', $message, null, $level);
    }

    /**
     * Get Net_SMTP instance
     *
     * Returned instance must be authenticated and connected.
     *
     * @param string $provider
     *   Any provider defined in the 'netsmtp' variable
     *
     * @return Net_SMTP
     *   Or null if instance could not be created or could not connect
     *   to SMTP server
     */
    protected function getInstance($provider = self::PROVIDER_DEFAULT)
    {
        $isTLS = false;
        $config = variable_get('netsmtp');

        if (empty($config[$provider])) {
            $this->setError(sprintf("Provider '%s' does not exists, fallback on default", $provider), WATCHDOG_WARNING);
            if (empty($config[$provider])) {
                $this->setError(sprintf("Default provider is not set", $provider));
                return null;
            }
        }

        if (empty($config[$provider]['hostname'])) {
            $this->setError(sprintf("Provider '%s' has no hostname", $provider));
            return null;
        }

        $info = array_filter($config[$provider]) + [
            'port'      => null,
            'username'  => null,
            'use_ssl'   => false,
            'password'  => '',
            'localhost' => null,
        ];

        if ($info['use_ssl']) {
            if ('tls' === $info['use_ssl']) {
                $info['hostname'] = 'tls://' . $info['hostname'];
                $isTLS = true;
            } else {
                $info['hostname'] = 'ssl://' . $info['hostname'];
            }
            if (empty($info['port'])) {
                $info['port'] = self::DEFAULT_SSL_PORT;
            }
        }

        // Attempt connection
        $smtp = new \Net_SMTP($info['hostname'], $info['port'], $info['localhost']);
        if ($this->PEAR->isError($e = $smtp->connect())) {
            $this->setError($e);
            return null;
        }

        if (!empty($info['username'])) {
            if ($this->PEAR->isError($e = $smtp->auth($info['username'], $info['password'], '', $isTLS))) {
                $this->setError($e);
                return null;
            }
        }

        // Finally! We did it I guess.
        return $smtp;
    }

    public function format(array $message)
    {
        watchdog('netsmtp', "I am not meant to format messages, sorry", [], WATCHDOG_ERROR);
        return false;
    }

    public function mail(array $message)
    {
        if (!empty($message[self::PROVIDER_KEY])) {
            $provider = $message[self::PROVIDER_KEY];
        } else {
            $provider = self::PROVIDER_DEFAULT;
        }

        if (!$smtp = $this->getInstance($provider)) {
            return false;
        }

        // SMTP basically does not care about message format. MIME is the
        // standard for everybody, so just prey that the previous formatter
        // did it right, but in all cases, we don't have to attempt any
        // formatting ourselves, this would be an serious error vulgaris
        // that every one seem to do... God I hate people.
        if (empty($message['body'])) {
            watchdog('netsmtp', "Sending an empty mail", [], WATCHDOG_WARNING);
            $message['body'] = '';
        }
        if (is_array($message['body'])) {
            $message['body'] = implode("\n", $message['body']);
        }
        if (empty($message['headers']['Subject'])) {
            if (variable_get('netsmtp_subject_encode', true)) {
                $message['headers']['Subject'] = mime_header_encode($message['subject']);
            } else {
                $message['headers']['Subject'] = $message['subject'];
            }
        }

        // Drupal black magic: we have to find the sender FROM into the
        // headers, that's a shame, but that's that. And if we can't find
        // any then do some black magic by ourselves
        if (empty($message['headers']['From'])) {
            if (empty($message['headers']['Reply-To'])) {
                $this->setError("No 'From' nor 'Reply-To' in mail");
                return false;
            } else {
                $this->setError("No 'From' in mail, using 'Reply-To' instead", WATCHDOG_INFO);
                $from = $this->catchAddressesInto($message['headers']['Reply-To']);
                $from = reset($from);
            }
        } else {
            $from = $this->catchAddressesInto($message['headers']['From']);
            $from = reset($from);
        }

        if (empty($from)) {
            $this->setError("FROM invalid or not found");
            return false;
        }
        if ($this->PEAR->isError($e = $smtp->mailFrom($from))) {
            $this->setError($e);
            return false;
        }

        $atLeastOne = false;
        foreach ($this->catchAddressesInto($message['to']) as $to) {
            if ($this->PEAR->isError($e = $smtp->rcptTo($to))) {
                $this->setError($e);
            } else {
                $atLeastOne = true;
            }
        }
        if (!$atLeastOne) {
            $this->setError("No RCPT was accepted by the SMTP server", WATCHDOG_ERROR);
            return false;
        }

        // Also note that the Net_SMTP library wants headers to be a string too
        $headers = [];
        foreach ($message['headers'] as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $_value) {
                    $headers[] = $name . ": " . $_value;
                }
            } else {
                $headers[] = $name . ": " . $value;
            }
        }

        // And the ugly part is, append body like a real Viking would do!
        $status = true;
        if ($this->PEAR->isError($e = $smtp->data($message['body'], implode("\n", $headers)))) {
            $this->setError($e);
            $status = false;
        }

        if (variable_get('netsmtp_debug_mime')) {
            $path = variable_get('netsmtp_debug_mime_path', 'temporary://netsmtp');
            $path .= '/' . date('Y-m-d');
            file_prepare_directory($path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
            file_put_contents($path . '/' . $provider . '-' . date('Y_m_d-H_i_s'). '.mbox', implode("\n", $headers) . "\n\n" . $message['body']);
        }

        $smtp->disconnect();

        return $status;
    }
}
