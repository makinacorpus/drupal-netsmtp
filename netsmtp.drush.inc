<?php
/**
 * Drush integration, mostly provides a set of test methods.
 */

use MakinaCorpus\Drupal\NetSmtp\MailSystemProxy;

/**
 * Implements hook_drush_command().
 */
function netsmtp_drush_command() {
  return [
    'netsmtp-test' => [
      'description' => "Connect to an arbitrary SMTP server and send a test mail",
      'arguments'   => [
        'email'     => "Testing e-mail address to send the mail too, you might set as many as you wish, space-separated",
      ],
      'options'     => [
        'hostname'  => "SMTP host",
        'username'  => "SMTP username",
        'password'  => "SMTP password, please type 'ask' for asking it",
        'port'      => "SMTP port",
        'use_ssl'   => "SSL mode, leave null for no SSL, value can be 'tls' or 'ssl'",
        'localhost' => "Local hostname override for SMTP communication",
      ],
    ],
  ];
}

/**
 * Run the test mail.
 */
function drush_netsmtp_test() {

  $arguments = func_get_args();
  if (empty($arguments)) {
    drush_print("you need to give me at least one e-mail address");
    return 1;
  }

  $options = array_filter([
    'hostname'  => drush_get_option('hostname'),
    'username'  => drush_get_option('username'),
    'password'  => drush_get_option('password'),
    'port'      => drush_get_option('port'),
    'use_ssl'   => drush_get_option('use_ssl'),
    'localhost' => drush_get_option('localhost'),
  ]);

  if (isset($options['use_ssl']) && 'ssl' !== $options['use_ssl'] && 'tls' !== $options['use_ssl']) {
    drush_print("'use_ssl' parameter must be 'ssl' or 'tls'");
    return 1;
  }
  if (isset($options['port']) && !is_numeric($options['port']) && !0 < $options['port']) {
    drush_print("'port' parameter must be positive integer");
    return 1;
  }

  if (isset($options['password']) && 'ask' === $options['password']) {
    drush_print("password prompt is not implemented yet");
    return 1;
  }

  // Trick Drupal, give him our information.
  $GLOBALS['conf']['mail_system'] = ['default-system' => MailSystemProxy::class];
  $GLOBALS['conf']['netsmtp'] = ['default' => $options];
  $GLOBALS['conf']['netsmtp_catch'] = false;

  drupal_mail('netsmtp', 'test', $arguments[0], 'en');
}
