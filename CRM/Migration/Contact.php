<?php

/**
 * Base class for MAF Norge Contact Migration to CiviCRM
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @date 15 May 2017
 * @license AGPL-3.0
 */
abstract class CRM_Migration_Contact extends CRM_Migration_MAF {

  /**
   * Returns a list with columns to ignore for setting api parameters.
   *
   * @return array
   */
  abstract protected function getColumnsToIgnore();

  /**
   * Method to migrate incoming data
   *
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
      try {
        $apiParams = $this->setApiParams();
        $created = civicrm_api3('Contact', 'create', $apiParams);
        $this->setCreateDate($created['id']);
        $this->addIdentity($created['id'], $this->_sourceData['id']);
        $this->addMobile($created['id']);
        $this->addAddress($created['id']);
        $this->addCustomData();
        return $created;
      } catch (CiviCRM_API3_Exception $ex) {
        $message = 'Could not add or update contact, error from API Contact create: '.$ex->getMessage().'. Source data is ';
        $paramMessage = array();
        foreach ($apiParams as $paramKey => $paramValue) {
          if (!is_array($paramValue)) {
            $paramMessage[] = $paramKey . ' with value ' . $paramValue;
          }
        }
        $message .= implode('; ', $paramMessage);
        $this->_logger->logMessage('Error', $message);
        return FALSE;
      }
    }
    return false;
  }

  /**
   * Method to create params for contact create (remove id as we need a new contact)
   */
  protected function setApiParams() {
    $columnsToIgnore = $this->getColumnsToIgnore();
    $apiParams = $this->_sourceData;
    foreach ($apiParams as $paramKey => $paramValue) {
      if (is_array($paramValue)) {
        unset($apiParams[$paramKey]);
      }
    }
    foreach ($columnsToIgnore as $removeKey) {
      unset($apiParams[$removeKey]);
    }

    //if (isset($this->_sourceData['new_contact_id']) && !empty($this->_sourceData['new_contact_id'])) {
    //$apiParams['id'] = $this->_sourceData['new_contact_id'];
    //}

    $apiParams['preferred_communication_method'] = array();
    $preferred_communication_method = explode(CRM_Core_DAO::VALUE_SEPARATOR,  $this->_sourceData['preferred_communication_method']);
    foreach($preferred_communication_method as $value) {
      if ($value) {
        $apiParams['preferred_communication_method'][] = $value;
      }
    }

    return $apiParams;
  }



  protected function addIdentity($contact_id, $original_contact_id) {
    $config = CRM_Migration_Config::singleton();

    $result = civicrm_api3('Contact', 'findbyidentity', array(
      'identifier_type' => $config->getOriginalContactIdType(),
      'identifier' => $original_contact_id,
    ));
    if ($result['count'] == 1) {
      return;
    }

    $params['contact_id'] = $contact_id;
    $params['identifier'] = $original_contact_id;
    $params['identifier_type'] = $config->getOriginalContactIdType();
    $identifier = civicrm_api3('Contact', 'addidentity', $params);
  }

  protected function setCreateDate($contact_id) {
    if (!isset($this->_sourceData['contact_source_date']) || empty($this->_sourceData['contact_source_date'])) {
      return;
    }
    $date_addedd = new DateTime($this->_sourceData['contact_source_date']);
    $sqlParams[1] = array($date_addedd->format('Y-m-d'), 'String');
    $sqlParams[2] = array($contact_id, 'Integer');
    CRM_Core_DAO::executeQuery("UPDATE civicrm_contact SET created_date = %1 WHERE id = %2", $sqlParams);
  }

  protected function addMobile($contact_id) {
    if (!isset($this->_sourceData['mobile']) || empty($this->_sourceData['mobile'])) {
      return;
    }

    $config = CRM_Migration_Config::singleton();
    $apiParams = array();

    // Retrieve current primary phone if it exists.
    try {
      $apiParams['id'] = civicrm_api3('Phone', 'getvalue', array(
        'contact_id' => $contact_id,
        'is_primary' => '1',
        'return' => 'id'
      ));
    } catch (Exception $e) {
      // Do nothing
    }

    $apiParams['contact_id'] = $contact_id;
    $apiParams['phone'] = $this->_sourceData['mobile'];
    $apiParams['phone_type_id'] = $config->getMobilePhoneTypeId();
    try {
      civicrm_api3('Phone', 'create', $apiParams);
    } catch (Exception $e) {
      $this->_logger->logMessage('Warning', 'Could not add a mobile (' . $this->_sourceData['mobile'] . ') for contact '
        .$this->_sourceData['display_name'].', mobile ignored. Reason for failing: '.$e->getMessage());
    }
  }

  protected function addAddress($contact_id) {
    $config = CRM_Migration_Config::singleton();
    $address_fields = array(
      'street_address' => 'street_address',
      'street_number' => 'street_number',
      'street_number_suffix' => 'street_number_suffix',
      'street_name' => 'street_name',
      'city' => 'city',
      'country_id' => 'country_id',
      'county_id' => 'county_id',
      'state_province_id' => 'state_province_id',
      'postal_code' => 'postal_code',
    );

    $apiParams = array();

    if (isset($this->_sourceData['master_id']) && !empty($this->_sourceData['master_id'])) {
      $master_contact_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM migration_individual WHERE address_id = %1", array(1=>array($this->_sourceData['master_id'], 'Integer')));
      if (!empty($master_contact_id)) {
        // Find master id
        $master_contact = civicrm_api3('Contact', 'findbyidentity', array(
          'identifier_type' => $config->getOriginalContactIdType(),
          'identifier' => $master_contact_id,
        ));
        if ($master_contact['count'] == 1) {
          $master_contact_id = $master_contact['id'];
        }
        else {
          $master_contact_id = FALSE;
        }
      }

      if (empty($master_contact_id)) {
        if (!empty($this->_sourceData['master_master_id'])) {
          $this->_logger->logMessage('Warning', 'Could not add an address because it is linked to another address for contact and that address is also linked '
            . $this->_sourceData['display_name'] . ' and could not find the master address, address ignored');
          return;
        } else {
          // Prefill address with data from master address fields
          $address_fields = array(
            'street_address' => 'master_street_address',
            'street_number' => 'master_street_number',
            'street_number_suffix' => 'master_street_number_suffix',
            'street_name' => 'master_street_name',
            'city' => 'master_city',
            'country_id' => 'master_country_id',
            'county_id' => 'master_county_id',
            'state_province_id' => 'master_state_province_id',
            'postal_code' => 'master_postal_code',
          );
        }
      } else {
        try {
          $apiParams['master_id'] = civicrm_api3('Address', 'getvalue', array(
            'contact_id' => $master_contact_id,
            'is_primary' => 1,
            'return' => 'id'
          ));
        } catch (Exception $e) {
          if (!empty($this->_sourceData['master_master_id'])) {
            $this->_logger->logMessage('Warning', 'Could not add an address because it is linked to another address for contact and that address is also linked '
              . $this->_sourceData['display_name'] . ' and could not find the master address, address ignored');
            return;
          } else {
            // Prefill address with data from master address fields
            $address_fields = array(
              'street_address' => 'master_street_address',
              'street_number' => 'master_street_number',
              'street_number_suffix' => 'master_street_number_suffix',
              'street_name' => 'master_street_name',
              'city' => 'master_city',
              'country_id' => 'master_country_id',
              'county_id' => 'master_county_id',
              'state_province_id' => 'master_state_province_id',
              'postal_code' => 'master_postal_code',
            );
          }
        }
      }
    }

    foreach($address_fields as $field => $source_field) {
      if (isset($this->_sourceData[$source_field]) && !empty($this->_sourceData[$source_field])) {
        $apiParams[$field] = $this->_sourceData[$source_field];
      }
    }

    if (empty($apiParams)) {
      // No data to set.
      return;
    }

    $apiParams['contact_id'] = $contact_id;
    $apiParams['location_type_id'] = $config->getDefaultLocationTypeId();

    // Retrieve current primary phone if it exists.
    try {
      $apiParams['id'] = civicrm_api3('Address', 'getvalue', array(
        'contact_id' => $contact_id,
        'is_primary' => '1',
        'return' => 'id'
      ));
    } catch (Exception $e) {
      // Do nothing
    }

    try {
      civicrm_api3('Address', 'create', $apiParams);
    } catch (Exception $e) {
      $this->_logger->logMessage('Warning', 'Could not add an address for contact '
        .$this->_sourceData['display_name'].' because '.$e->getMessage().', address ignored');
    }
  }

  /**
   * Implementation of method to validate if source data is good enough for contact
   *
   * @return bool
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['id'])) {
      $this->_logger->logMessage('Error', 'Contact has no contact_id, not migrated. Source data is '.implode(';', $this->_sourceData));
      return FALSE;
    }
    // check address
    if (!$this->checkAddress()) {
      $this->_logger->logMessage('Error', 'Contact has invalid address data (master id is set and we don\'t know how to process this yet) , not migrated. Source data is '.implode(';', $this->_sourceData));
      return false;
    }
    return TRUE;
  }

  /**
   * Method to check whether the address data is valid.
   *
   * @return bool
   */
  protected function checkAddress() {
    if (!empty($this->_sourceData['country_id'])) {
      try {
        $count = civicrm_api3('Country', 'getcount', array('id' => $this->_sourceData['country_id']));
        if ($count != 1) {
          $this->_logger->logMessage('Warning', 'Address with contact_id ' . $this->_sourceData['contact_id']
            . ' does not have a valid country_id (' . $count . ' of ' . $this->_sourceData['country_id']
            . 'found), address created without country');
          $this->_sourceData['country_id'] = 0;
        }
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Warning', 'Error retrieving country from CiviCRM for address with contact_id '
          . $this->_sourceData['contact_id'] . ' and country_id' . $this->_sourceData['country_id']
          . ', address migrated without country. Error from API Country getcount : ' . $ex->getMessage());
        $this->_sourceData['country_id'] = 0;
      }
    }
    return true;
  }

  /**
   * Method to add contact custom data if necessary
   *
   * @access protected
   */
  protected function addCustomData() {
  }

}