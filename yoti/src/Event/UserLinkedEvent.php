<?php

namespace Drupal\yoti\Event;

/**
 * Event that is fired when a user is linked to Yoti.
 */
class UserLinkedEvent extends AbstractUserEvent {

  const EVENT_NAME = 'yoti_user_linked';

}
