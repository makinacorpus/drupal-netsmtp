<?php

/**
 * Main administration screen.
 */
function netsmtp_ui_admin_server_list() {
  return "@todo";
}

/**
 * Main administration screen.
 */
function netsmtp_ui_admin_server_add($form, &$form_state) {

  $form['actions']['#type'] = 'actions';
  $form['actions']['submit'] = array(
    '#type'   => 'submit',
    '#value'  => t("Save"),
    '#submit' => array('netsmtp_ui_admin_server_save_submit'),
  );

  return $form;
}

/**
 * Main administration screen.
 */
function netsmtp_ui_admin_server_edit($form, &$form_state, $key) {
  $form = netsmtp_ui_admin_server_add($form, $form_state);

  $form['#server_key'] = $key;

  // @todo Set default values

  $form['actions']['submit']['#value'] = t("Update");
}

/**
 * Save or edit form submit handler.
 */
function netsmtp_ui_admin_server_save_submit($form, &$form_state) {
  // @todo

  drupal_set_message(t("Configuration has been updated"));
  $form_state['redirect'] = 'admin/config/system/netsmtp';
}

/**
 * Main administration screen.
 */
function netsmtp_ui_admin_server_delete($form, &$form_state, $key) {
  return "@todo";
}
