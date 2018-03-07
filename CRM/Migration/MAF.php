<?php

/**
 * Abstract class for ForumZFD Migration to CiviCRM
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 1 March 2017
 * @license AGPL-3.0
 */
abstract class CRM_Migration_MAF {

  protected $_logger = NULL;
  protected $_sourceData = array();
  protected $_entity = NULL;

  /**
   * CRM_Migratie_MAF constructor.
   *
   * @param string $entity
   * @param object $sourceData
   * @param object $logger
   * @throws Exception when entity invalid
   */
  public function __construct($entity, $sourceData = NULL, $logger = NULL) {
    $entity = strtolower($entity);
    if (!$this->entityCanBeMigrated($entity)) {
      throw new Exception('Entity '.$entity.' can not be migrated.');
    } else {
      $this->_entity = $entity;
      $this->_sourceData = (array)$sourceData;
      $this->cleanSourceData();
      $this->_logger = $logger;
    }
  }

  /**
   * Method to remove DAO parts of source data and unnecessary is processed element
   *
   * @access private
   */
  private function cleanSourceData() {
    foreach ($this->_sourceData as $sourceKey => $sourceValue) {
      if ($sourceKey == 'N' || substr($sourceKey, 0, 1) == '_') {
        unset($this->_sourceData[$sourceKey]);
      }
    }
    if (isset($this->_sourceData['is_processed'])) {
      unset($this->_sourceData['is_processed']);
    }
  }

  /**
   * Method to check if entity can be migrated
   *
   * @param string $entity
   * @return bool
   * @access private
   */
  private function entityCanBeMigrated($entity) {
    $validEntities = array(
      'individual', 'organisation', 'contributionrecur', 'contribution', 'contributionsoft', 'kid_number', 'groupcontact', 'entitytag'
    );
    if (!in_array($entity, $validEntities)) {
      return FALSE;
    } else {
      return TRUE;
    }
  }

  /**
   * Abstract method to migrate incoming data
   */
  abstract function migrate();

  /**
   * Abstract Method to validate if source data is good enough
   */
  abstract function validSourceData();

  /**
   * Check if is_primary is set to 1, it can actually be set and otherwise set to 0 and log
   *
   * @access protected
   */
  protected function checkIsPrimary() {
    if ($this->_sourceData['is_primary'] == 1) {
      $countQuery = 'SELECT COUNT(*) FROM civicrm_'.$this->_entity.' WHERE contact_id = %1 AND is_primary = %2';
      $countParams = array(
        1 => array($this->_sourceData['contact_id'], 'Integer'),
        2 => array(1, 'Integer')
      );
      $countPrimary = CRM_Core_DAO::singleValueQuery($countQuery, $countParams);
      if ($countPrimary > 0) {
        $this->_sourceData['is_primary'] = 0;
      }
    }
  }

  /**
   * Method to check if contact already exists
   *
   * @param int $contactId
   * @return bool
   * @access protected
   */
  protected function contactExists($contactId) {
    $query = 'SELECT COUNT(*) FROM civicrm_contact WHERE id = %1';
    $params = array(1 => array($contactId, 'Integer'));
    $countContact = CRM_Core_DAO::singleValueQuery($query, $params);
    if ($countContact == 0) {
      return FALSE;
    } else {
      return TRUE;
    }
  }

  /**
   * Method to check if location type is valid
   *
   * @return bool
   * @access protected
   */
  protected function validLocationType() {
    if (!isset($this->_sourceData['location_type_id'])) {
      $this->_logger->logMessage('Warning', $this->_entity.' of contact_id '.$this->_sourceData['contact_id']
        .'has no location_type_id, location_type_id 1 used');
      $this->_sourceData['location_type_id'] = 1;
    } else {
      try {
        $count = civicrm_api3('LocationType', 'getcount', array('id' => $this->_sourceData['location_type_id']));
        if ($count != 1) {
          $this->_logger->logMessage('Warning', $this->_entity.' with contact_id ' . $this->_sourceData['contact_id']
            . ' does not have a valid location_type_id (' . $count . ' of ' . $this->_sourceData['location_type_id']
            . 'found), created with location_type_id 1');
          $this->_sourceData['location_type_id'] = 1;
        }
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Error', 'Error retrieving location type id from CiviCRM for '.$this->_entity
          .' with contact_id '. $this->_sourceData['contact_id'] . ' and location_type_id'
          . $this->_sourceData['location_type_id']
          . ', ignored. Error from API LocationType getcount : ' . $ex->getMessage());
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Method to check if membership type is valid
   *
   * @return bool
   * @access protected
   */
  protected function validMembershipType() {
    if (!isset($this->_sourceData['membership_type_id'])) {
      $this->_logger->logMessage('Error', $this->_entity.' of contact_id '.$this->_sourceData['contact_id']
        .'has no membership_type_id, not migrated');
      return FALSE;
    } else {
      try {
        $count = civicrm_api3('MembershipType', 'getcount', array('id' => $this->_sourceData['membership_type_id']));
        if ($count != 1) {
          $this->_logger->logMessage('Error', $this->_entity.' with contact_id ' . $this->_sourceData['contact_id']
            . ' does not have a valid membership_type_id (' . $count . ' of ' . $this->_sourceData['membership_type_id']
            . 'found), not migrated');
          return FALSE;
        }
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Error', 'Error retrieving membership type id from CiviCRM for '.$this->_entity
          .' with contact_id '. $this->_sourceData['contact_id'] . ' and membership_type_id'
          . $this->_sourceData['membership_type_id']
          . ', ignored. Error from API MembershipType getcount : ' . $ex->getMessage());
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Method to check if membership status is valid
   *
   * @return bool
   * @access protected
   */
  protected function validMembershipStatus() {
    if (!isset($this->_sourceData['status_id'])) {
      $this->_logger->logMessage('Error', $this->_entity.' of contact_id '.$this->_sourceData['contact_id']
        .'has no status_id, not migrated');
      return FALSE;
    } else {
      try {
        $count = civicrm_api3('MembershipStatus', 'getcount', array('id' => $this->_sourceData['status_id']));
        if ($count != 1) {
          $this->_logger->logMessage('Error', $this->_entity.' with contact_id ' . $this->_sourceData['contact_id']
            . ' does not have a valid status_id (' . $count . ' of ' . $this->_sourceData['status_id']
            . 'found), not migrated');
          return FALSE;
        }
      } catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Error', 'Error retrieving status_id from CiviCRM for '.$this->_entity
          .' with contact_id '. $this->_sourceData['contact_id'] . ' and status_id'
          . $this->_sourceData['status_id']
          . ', ignored. Error from API MembershipStatus getcount : ' . $ex->getMessage());
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Method to find the new contact id with the old one as a identity
   *
   * @param $sourceContactId
   * @return array|bool
   */
  protected function findNewContact($sourceContactId) {
    if (!empty($sourceContactId)) {
      try {
        $newContact = civicrm_api3('Contact', 'findbyidentity', array(
          'identifier' => $sourceContactId,
          'identifier_type' => 'original_contact_id'));
        if ($newContact['count'] == 0) {
          return FALSE;
        } else {
          if ($newContact['id']) {
            return $newContact['id'];
          }
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
      }
    }
    return FALSE;
  }

	protected function earmarkingToCampaign($earmarking) {
		$config = CRM_Mafsepa_Config::singleton();
		
		switch($earmarking) {
			case '300 Medlemsinntekter':
			case '333 Gaver Fuel':
			case '338 Gaver til frie midler':
			case 'Andresen':
			case 'LePoidevin':
			case 'Lindtjørn':
			case 'Pedersen':
			case 'Simpson':
			case 'Steinsletten':
				$campaign = $earmarking;	
				break;
			case '331 Gaver Fly':
				$campaign = '333 Gaver Fuel';
				break;
			default:
				$campaign = '338 Gaver til frie midler';
				break; 
		}
		
		// Check if this campaign exists if so return the campaign id
		// if not create the campaign and return then the campaign id
		try {
			$campaign_id = civicrm_api3('Campaign', 'getvalue', array(
				'return' => 'id',
				'title' => $campaign,
			));
			return $campaign_id;
		} catch (Exception $e) {
			// Create the campaign
			$params['is_active'] = 1;
			$params['title'] = $campaign;
			$params['campaign_type_id'] = $config->getFundraisingCampaignType();
			$result = civicrm_api3('Campaign', 'create', $params);
			return $result['id'];
		}
		
		return $config->getDefaultFundraisingCampaignId();
	}
}