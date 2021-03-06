<?php


/**
 * ContributionSoft.Migrate API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contribution_soft_Migrate($params) {
  set_time_limit(0);
  $returnValues = array();
  $entity = 'contributionsoft';
  $createCount = 0;
  $logCount = 0;
  $logger = new CRM_Migration_Logger($entity);
  $limit = 2500;
  if (isset($params['options']) && isset($params['options']['limit'])) {
    $limit = $params['options']['limit'];
  }
  $daoSource = CRM_Core_DAO::executeQuery('SELECT * FROM migration_soft
    WHERE is_processed = 0 ORDER BY id LIMIT %1', array(1=>array($limit, 'Integer')));
  while ($daoSource->fetch()) {
    $civiSoft = new CRM_Migration_ContributionSoft($entity, $daoSource, $logger);
    $newSoft = $civiSoft->migrate();
    if ($newSoft == FALSE) {
      $logCount++;
      $updateQuery = 'UPDATE migration_soft SET is_processed = %1 WHERE id = %2';
      CRM_Core_DAO::executeQuery($updateQuery, array(1 => array(1, 'Integer'), 2 => array($daoSource->id, 'Integer')));
    } else {
      $createCount++;
      $updateQuery = 'UPDATE migration_soft SET is_processed = %1, new_soft_id = %2 WHERE id = %3';
      CRM_Core_DAO::executeQuery($updateQuery, array(
        1 => array(1, 'Integer',),
        2 => array($newSoft['id'], 'Integer',),
        3 => array($daoSource->id, 'Integer',),));
    }
  }

  if (empty($daoSource->N)) {
    $returnValues[] = 'No more soft credits to migrate';
  } else {
    $returnValues[] = $createCount.' soft credits migrated to CiviCRM, '.$logCount.' with logged errors that were not migrated';
  }
  return civicrm_api3_create_success($returnValues, $params, 'ContributionSoft', 'Migrate');}
