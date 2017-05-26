<?php

/**
 * Class for MAF Norge Contribution Soft Credit Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 25 May 2017
 * @license AGPL-3.0
 */
class CRM_Migration_ContributionSoft extends CRM_Migration_MAF {

  /**
   * Method to migrate incoming data
   *
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      // set generic data
      $apiParams = $this->setApiParams();
      try {
        $soft = civicrm_api3('ContributionSoft', 'create', $apiParams);
        return $soft['values'][$soft['id']];
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Error', 'Could not generate soft contribution ID '
          .$this->_sourceData['id'].', error in API ContributionSoft Create!');
      }
    }
    return FALSE;
  }

  /**
   * Method to set the api params
   *
   * @return array
   */
  private function setApiParams() {
    return array(
      'contact_id' => $this->_sourceData['new_contact_id'],
      'contribution_id' => $this->_sourceData['new_contribution_id'],
      'amount' => $this->_sourceData['amount'],
      'currency' => $this->_sourceData['currency'],
    );
  }

  /**
   * Implementation of method to validate if source data is good enough for contribution
   *
   * @return bool
   * @throws Exception when required custom table not found
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['contact_id'])) {
      $this->_logger->logMessage('Error', 'Soft Credit has no contact_id, not migrated. Soft Credit id is '
        .$this->_sourceData['id']);
      return FALSE;
    }
    if (!isset($this->_sourceData['contribution_id'])) {
      $this->_logger->logMessage('Error', 'Soft Credit has no contribution_id, not migrated. Soft Credit id is '
        .$this->_sourceData['id']);
      return FALSE;
    }
    // find new contact id and error if not found
    $newContactId = $this->findNewContact($this->_sourceData['contact_id']);
    if ($newContactId) {
       $this->_sourceData['new_contact_id'] = $newContactId;
    } else {
      $this->_logger->logMessage('Error', 'Could not find a new contact with the source contact ID '
        .$this->_sourceData['contact_id'].', soft credit not migrated');
      return FALSE;
    }
    // find new contribution id and error if not found
    $sql = 'SELECT new_contribution_id FROM migration_contribution WHERE id = %1';
    $newContributionId = CRM_Core_DAO::singleValueQuery($sql, array(1 => array($this->_sourceData['contribution_id'], 'Integer')));
    if ($newContributionId) {
      $this->_sourceData['new_contribution_id'] = $newContributionId;
    } else {
      $this->_logger->logMessage('Error', 'Could not find a new contribution with the source contribution ID '
        .$this->_sourceData['contribution'].', soft credit not migrated');
      return FALSE;
    }
    return TRUE;
  }
}