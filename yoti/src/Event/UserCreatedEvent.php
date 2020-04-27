<?php

namespace Drupal\yoti\Event;

/**
 * Event that is fired when a user is created from Yoti.
 */
class UserCreatedEvent extends AbstractUserEvent {

  const EVENT_NAME = 'yoti_user_created';

}
