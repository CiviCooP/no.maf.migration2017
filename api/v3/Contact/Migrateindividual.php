<?php

/**
 * Contact.Migrate API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_contact_migrateindividual_spec(&$spec) {
}

/**
 * Contact.Migrate API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contact_migrateindividual($params) {
  set_time_limit(0);
  $returnValues = array();
  $entity = 'individual';
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migration_Logger($entity);
  $limit = 1000;
  if (isset($params['options']) && isset($params['options']['limit'])) {
    $limit = $params['options']['limit'];
  }
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM migration_individual WHERE is_processed = 0 ORDER BY master_id, id LIMIT %1', array(1=>array($limit, 'Integer')));
  while ($daoSource->fetch()) {
    $civiContact = new CRM_Migration_Individual($entity, $daoSource, $logger);
    $newContact = $civiContact->migrate();
    if ($newContact == FALSE) {
      $logCount++;
      $updateQuery = 'UPDATE migration_individual SET is_processed = %1 WHERE id = %2';
      CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
    } else {
      $createCount++;
      $updateQuery = 'UPDATE migration_individual SET is_processed = %1, new_contact_id = %2 WHERE id = %3';
      CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($newContact['id'], 'Integer'), 3 => array($daoSource->id, 'Integer')));
    }
  }
  // set max contact id + 1 as the auto increment key for contact_id
  $maxId = CRM_Core_DAO::singleValueQuery('SELECT MAX(id) FROM civicrm_contact');
  $maxId++;
  CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_contact AUTO_INCREMENT = '.$maxId);

  if (empty($daoSource->N)) {
    $returnValues[] = 'No more contacts to migrate';
  } else {
    $returnValues[] = $createCount.' contacts migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'Contact', 'Migrateindividual');
}
