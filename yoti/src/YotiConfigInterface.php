<?php

namespace Drupal\yoti;

/**
 * Interface YotiConfigInterface.
 *
 * @package Drupal\yoti
 */
interface YotiConfigInterface {

  /**
   * Yoti settings data.
   *
   * @return array
   *   Settings data as array.
   */
  public function getSettings();

  /**
   * Yoti App ID.
   *
   * @return string
   *   App ID.
   */
  public function getAppId();

  /**
   * Yoti Scenario ID.
   *
   * @return string
   *   Scenario ID.
   */
  public function getScenarioId();

  /**
   * Yoti SDK ID.
   *
   * @return string
   *   SDK ID.
   */
  public function getSdkId();

  /**
   * Only allow existing Drupal users to link their Yoti account.
   *
   * @return int
   *   Flag to only link existing users.
   */
  public function getOnlyExisting();

  /**
   * Redirect users here if they successfully login with Yoti.
   *
   * @return string
   *   Redirect URL.
   */
  public function getSuccessUrl();

  /**
   * Redirect users here if they were unable to login with Yoti.
   *
   * @return string
   *   Redirect URL.
   */
  public function getFailUrl();

  /**
   * Attempt to link Yoti email address with Drupal account.
   *
   * @return int
   *   Flag to link users based on email address.
   */
  public function getUserEmail();

  /**
   * Prevent users who have not passed age verification.
   *
   * @return int
   *   Flag to prevent users that have not passed age verification.
   */
  public function getAgeVerification();

  /**
   * Company name to display to users.
   *
   * @return string
   *   Company name.
   */
  public function getCompanyName();

  /**
   * Contents of the PEM file.
   *
   * @return string
   *   PEM file contents.
   */
  public function getPemContents();

}
