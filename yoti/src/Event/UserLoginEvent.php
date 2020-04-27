<?php

namespace Drupal\yoti\Event;

/**
 * Event that is fired when a user logs in via Yoti.
 */
class UserLoginEvent extends AbstractUserEvent {

  const EVENT_NAME = 'yoti_user_login';

}
