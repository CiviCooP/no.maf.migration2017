<?php


/**
 * GroupContact.Migrate API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_group_contact_Migrate($params) {
  set_time_limit(0);
  $returnValues = array();
  $entity = 'groupcontact';
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migration_Logger($entity);
  $limit = 1000;
  if (isset($params['options']) && isset($params['options']['limit'])) {
    $limit = $params['options']['limit'];
  }
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM migration_groups 
    WHERE is_processed = 0 ORDER BY id LIMIT %1', array(1=>array($limit, 'Integer')));
  while ($daoSource->fetch()) {
    $civiGroupContact = new CRM_Migration_GroupContact($entity, $daoSource, $logger);
    $success = $civiGroupContact->migrate();
    if ($success == FALSE) {
      $logCount++;
      $updateQuery = 'UPDATE migration_groups SET is_processed = %1 WHERE id = %2';
      CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
    } else {
      $createCount++;
      $updateQuery = 'UPDATE migration_groups SET is_processed = %1 WHERE id = %2';
      CRM_Core_DAO::executeQuery($updateQuery, array(
        1 => array(1, 'Integer',),
        2 => array($daoSource->id, 'Integer',),));
    }
  }

  if (empty($daoSource->N)) {
    $returnValues[] = 'No more group contacts to migrate';
  } else {
    $returnValues[] = $createCount.' group contacts migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'GroupContact', 'Migrate');}