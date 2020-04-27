<?php

namespace Drupal\yoti\Event;

/**
 * Event that is fired when a user is unlinked from Yoti.
 */
class UserUnlinkedEvent extends AbstractUserEvent {

  const EVENT_NAME = 'yoti_user_unlinked';

}
