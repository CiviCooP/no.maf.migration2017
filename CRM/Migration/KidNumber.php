<?php

class CRM_Migration_KidNumber extends CRM_Migration_MAF {

  /**
   * Abstract method to migrate incoming data
   */
  function migrate() {
    if ($this->validSourceData()) {
      $apiParams['contact_id'] = $this->_sourceData['contact_id'];
      $apiParams['identifier'] = $this->_sourceData['kid_number'];
      $apiParams['identifier_type'] = 'KID';
      try {
        civicrm_api3('Contact', 'addidentity', $apiParams);
        return true;
      } catch (Exception $e) {
        $this->_logger->logMessage('Error', 'Could not add kid number ('.$this->_sourceData['kid_number'].') because of '.$e->getMessage());
        return false;
      }
    }
    return false;
  }

  /**
   * Abstract Method to validate if source data is good enough
   */
  function validSourceData() {
    if (!isset($this->_sourceData['contact_id'])) {
      $this->_logger->logMessage('Error', 'KID Number has no contact_id, not migrated. KID Number is '
        .$this->_sourceData['kid_number']);
      return FALSE;
    }
    // find new contact id and error if not found
    $newContactId = $this->findNewContact($this->_sourceData['contact_id']);
    if ($newContactId) {
      $this->_sourceData['contact_id'] = $newContactId;
    } else {
      $this->_logger->logMessage('Error', 'Could not find a new contact with the source contact ID '
        .$this->_sourceData['contact_id'].', KID Number not migrated');
      return FALSE;
    }

    // Check whether the kid number already exists
    $existsQuery = "SELECT COUNT(*) FROM civicrm_value_contact_id_history WHERE identifier_type = 'KID' AND identifier = %1";
    $existsParams[1] = array($this->_sourceData['kid_number'], 'String');
    $exists = CRM_Core_DAO::singleValueQuery($existsQuery, $existsParams);
    if ($exists) {
      $this->_logger->logMessage('Error', 'KID already exists in the system ('.$this->_sourceData['kid_number'].'). Not migrated');
      return FALSE;
    }
    return true;
  }

}