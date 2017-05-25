<?php


/**
 * Contribution.Migrate API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contribution_Migrate($params) {
  set_time_limit(0);
  $returnValues = array();
  $entity = 'contribution';
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migration_Logger($entity);
  $limit = 2500;
  if (isset($params['options']) && isset($params['options']['limit'])) {
    $limit = $params['options']['limit'];
  }
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM migration_contribution
    WHERE is_processed = 0 ORDER BY id LIMIT %1', array(1=>array($limit, 'Integer')));
  while ($daoSource->fetch()) {
    $civiContribution = new CRM_Migration_Contribution($entity, $daoSource, $logger);
    $newContribution = $civiContribution->migrate();
    if ($newContribution == FALSE) {
      $logCount++;
      $updateQuery = 'UPDATE migration_contribution SET is_processed = %1 WHERE id = %2';
      CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
    } else {
      $createCount++;
      $updateQuery = 'UPDATE migration_contribution SET is_processed = %1, new_contribution_id = %2 WHERE id = %3';
      CRM_Core_DAO::executeQuery($updateQuery, array(
        1 => array(1, 'Integer',),
        2 => array($newContribution['id'], 'Integer',),
        3 => array($daoSource->id, 'Integer',),));
    }
  }

  if (empty($daoSource->N)) {
    $returnValues[] = 'No more contributions to migrate';
  } else {
    $returnValues[] = $createCount.' contributions migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'Contribution', 'Migrate');}
