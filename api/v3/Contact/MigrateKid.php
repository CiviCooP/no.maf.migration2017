<?php

function civicrm_api3_contact_Migrate_Kid($params) {
  set_time_limit(0);
  $returnValues = array();
  $entity = 'kid_number';
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migration_Logger($entity);
  $limit = 1000;
  if (isset($params['options']) && isset($params['options']['limit'])) {
    $limit = $params['options']['limit'];
  }
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM migration_kid_number WHERE is_processed = 0 ORDER BY id LIMIT %1', array(1=>array($limit, 'Integer')));
  while ($daoSource->fetch()) {
    $kidNumber = new CRM_Migration_KidNumber($entity, $daoSource, $logger);
    $migrated = $kidNumber->migrate();
    if ($migrated == FALSE) {
      $logCount++;
    } else {
      $createCount++;
    }

    $updateQuery = 'UPDATE migration_kid_number SET is_processed = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
  }

  if (empty($daoSource->N)) {
    $returnValues[] = 'No more kid numbers to migrate';
  } else {
    $returnValues[] = $createCount.' kid numbers migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'Contact', 'Migrateindividual');
}
