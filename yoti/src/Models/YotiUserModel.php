<?php

namespace Drupal\yoti\Models;

use Drupal;
use Drupal\yoti\YotiHelper;
use Yoti\ActivityDetails;

/**
 * Class YotiUserModel.
 *
 * @package Drupal\yoti\Models
 * @author Moussa Sidibe <websdk@yoti.com>
 */
class YotiUserModel {

  /**
   * Get Yoti user profile by user Id.
   *
   * @param int $userId
   *   User Id.
   *
   * @return mixed
   *   Yoti user profile.
   */
  public static function getYotiUserById($userId) {
    $userProfile = NULL;
    if ((int) $userId > 0) {
      $tableName = YotiHelper::YOTI_USER_TABLE_NAME;
      $userProfile = Drupal::database()->query("SELECT * from `{$tableName}` WHERE uid=" . $userId . " Limit 1")->fetchAssoc();
    }
    return $userProfile;
  }

  /**
   * Create Yoti user table.
   */
  public static function createYotiUserTable() {
    $table_name = YotiHelper::YOTI_USER_TABLE_NAME;
    Drupal::database()->query("CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT,
            `uid` int(10) UNSIGNED NOT NULL,
            `identifier` VARCHAR(255) NOT NULL,
            `data` TEXT NULL,
            PRIMARY KEY `id` (`id`),
            UNIQUE KEY `uid` (`uid`)
        )")->execute();
  }

  /**
   * Return fields to be removed from Yoti users table.
   *
   * @return array
   *   Fields to remove.
   */
  public static function removeDuplicatedFieldsFromYotiUserTable() {
    $table_name = YotiHelper::YOTI_USER_TABLE_NAME;
    $ret = [];
    $dbConn = Drupal::database();
    $ret[] = $dbConn->schema()->dropField($table_name, 'selfie_filename');
    $ret[] = $dbConn->schema()->dropField($table_name, 'phone_number');
    $ret[] = $dbConn->schema()->dropField($table_name, 'date_of_birth');
    $ret[] = $dbConn->schema()->dropField($table_name, 'given_names');
    $ret[] = $dbConn->schema()->dropField($table_name, 'family_name');
    $ret[] = $dbConn->schema()->dropField($table_name, 'nationality');
    $ret[] = $dbConn->schema()->dropField($table_name, 'gender');
    $ret[] = $dbConn->schema()->dropField($table_name, 'email_address');

    return $ret;
  }

  /**
   * Delete Yoti user table.
   */
  public static function deleteYotiUserTable() {
    $table_name = YotiHelper::YOTI_USER_TABLE_NAME;
    Drupal::database()->query("DROP TABLE IF EXISTS `{$table_name}`")->execute();
  }

  /**
   * Get user Drupal Uid by Yoti user Id.
   *
   * @param int $yotiId
   *   Yoti user Id.
   * @param string $field
   *   User field to look up.
   *
   * @return mixed
   *   Drupal User Uid.
   */
  public static function getUserUidByYotiId($yotiId, $field) {
    $tableName = YotiHelper::YOTI_USER_TABLE_NAME;
    $col = NULL;
    if (!empty($yotiId) && !empty($field)) {
      $col = Drupal::database()->query("SELECT uid FROM `{$tableName}` WHERE `{$field}` = '$yotiId' Limit 1")->fetchCol();
    }
    return $col;
  }

  /**
   * Create Yoti user.
   *
   * @param int $userId
   *   User Id.
   * @param \Yoti\ActivityDetails $activityDetails
   *   Yoti user data.
   * @param array $meta
   *   User meta data.
   *
   * @throws \Exception
   */
  public static function createYotiUser($userId, ActivityDetails $activityDetails, array $meta) {
    Drupal::database()->insert(YotiHelper::YOTI_USER_TABLE_NAME)->fields([
      'uid' => $userId,
      'identifier' => $activityDetails->getRememberMeId(),
      'data' => serialize($meta),
    ])->execute();
  }

  /**
   * Delete Yoti user by Id.
   *
   * @param int $userId
   *   User Id.
   */
  public static function deleteYotiUserById($userId) {
    Drupal::database()->delete(YotiHelper::YOTI_USER_TABLE_NAME)->condition("uid", $userId)->execute();
  }

  /**
   * Get the number of username starting with prefix.
   *
   * @param string $prefix
   *   Yoti username prefix.
   *
   * @return int
   *   Yoti username count.
   */
  public static function getUsernameCountByPrefix($prefix) {
    $usernameCount = 0;
    if (!empty($prefix)) {
      $userQuery = Drupal::database()->select('users_field_data', 'uf');
      $userQuery->fields('uf', ['name']);
      $userQuery->condition('name', $userQuery->escapeLike($prefix) . '%', 'LIKE');
      $results = $userQuery->execute()->fetchAll();
      $usernameCount = count($results);
    }
    return $usernameCount;
  }

  /**
   * Get the number of user email starting with prefix.
   *
   * @param string $prefix
   *   Yoti user email prefix.
   *
   * @return int
   *   Yoti user email count.
   */
  public static function getUserEmailCountByPrefix($prefix) {
    $emailCount = 0;
    if (!empty($prefix)) {
      $userQuery = Drupal::database()->select('users_field_data', 'uf');
      $userQuery->fields('uf', ['mail']);
      $userQuery->condition('mail', Drupal::database()->escapeLike($prefix) . '%', 'LIKE');
      $results = $userQuery->execute()->fetchAll();
      $emailCount = count($results);
    }
    return $emailCount;
  }

}
