<?php

/**
 * ContributionRecur.Fixfirstcontribution
 * 
 * This api fixes the first contribution id field on the mandate.
 * This field is not migrated and we need to fill it with a reference to
 * the last contribution with status completed.
 * The field is needed to calculate when the next contribution should happen ans specially
 * when the frequency is other than once a month.
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contribution_recur_Fixfirstcontribution($params) {
  set_time_limit(0);
  $returnValues = array();
	
	$dao = CRM_Core_DAO::executeQuery("
		SELECT * 
		FROM civicrm_sdd_mandate mandate 
		WHERE mandate.first_contribution_id IS NULL
		AND entity_table = 'civicrm_contribution_recur'
		AND entity_id in (select contribution_recur_id from civicrm_contribution);
	");
	
	$found = 0;
	$updated = 0;
	while($dao->fetch()) {
		$found++;
		$contribution_id = CRM_Core_DAO::singleValueQuery("
			SELECT id 
			FROM `civicrm_contribution`
			WHERE contribution_status_id = 1
			AND contribution_recur_id = %1
			ORDER BY receive_date DESC LIMIT 0,1 ",
			array(1=>array($dao->entity_id, 'Integer')));
		if ($contribution_id) {
			CRM_Core_DAO::executeQuery("UPDATE civicrm_sdd_mandate SET first_contribution_id = %1 WHERE id = %2",
			array(
				1 => array($contribution_id, 'Integer'),
				2 => array($dao->id, 'Integer')
			));
			$updated++;
		}
	} 
	
	$returnValues['updated'] = $updated;
	$returnValues['found'] = $found;
	
	return civicrm_api3_create_success($returnValues, $params, 'ContributionRecur', 'Fixfirstcontribution');
}