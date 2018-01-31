<?php

/**
 * Class for MAF Norge Group Contact Migration to CiviCRM
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @date 31 Jan 2018
 * @license AGPL-3.0
 */
class CRM_Migration_GroupContact extends CRM_Migration_MAF {
	
	private $titleToGroupId = array();

  /**
   * Method to migrate incoming data
   *
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
    	try {
	    	$result = civicrm_api3('GroupContact', 'create', array(
	    		'group_id' => $this->_sourceData['group_id'],
	    		'contact_id' => $this->_sourceData['contact_id'],
	 			));
				return true;
			} catch (Exception $e) {
				$this->_logger->logMessage('Error', 'Group contact has no contact_id, not migrated. Group contact id is '
        .$this->_sourceData['id'].', error message: '.$e->getMessage());
			}
		}
		return false;
	}
	
	/**
   * Implementation of method to validate if source data is good enough for contact
   *
   * @return bool
   * @throws Exception when required custom table not found
   */
  public function validSourceData() {
    if (!isset($this->_sourceData['contact_id'])) {
      $this->_logger->logMessage('Error', 'Group contact has no contact_id, not migrated. Group contact id is '
        .$this->_sourceData['id']);
      return FALSE;
    }
    // find new contact id and error if not found
    $newContactId = $this->findNewContact($this->_sourceData['contact_id']);
    if ($newContactId) {
       $this->_sourceData['contact_id'] = $newContactId;
    } else {
      $this->_logger->logMessage('Error', 'Could not find a new contact with the source contact ID '
        .$this->_sourceData['contact_id'].', group contact not migrated');
      return FALSE;
    }
		
		// find or create group
		$newGroupId = $this->findOrCreateGroup();
		if ($newGroupId) {
			$this->_sourceData['group_id'] = $newGroupId;
		} else {
			$this->_logger->logMessage('Error', 'Could not find a new group id for old group id '
        .$this->_sourceData['group_id'].', group contact not migrated');
			return FALSE;
		}
		
		return TRUE;
	}

	private function findOrCreateGroup() {
		if (isset($this->titleToGroupId[$this->_sourceData['title']])) {
			return $this->titleToGroupId[$this->_sourceData['title']];
		}
		
		$group_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_group WHERE title = %1", array(
			1 => array($this->_sourceData['title'], 'String'),
		));
		if ($group_id) {
			$this->titleToGroupId[$this->_sourceData['title']] = $group_id;
			return $this->titleToGroupId[$this->_sourceData['title']];
		}
		
		$groupParams['title'] = $this->_sourceData['title'];
		$groupParams['description'] = $this->_sourceData['description'];
		$groupParams['is_reserved'] = $this->_sourceData['is_reserved'];
		try {
			$result = civicrm_api3('Group', 'create', $groupParams);
			$this->titleToGroupId[$this->_sourceData['title']] = $result['id'];
			return $this->titleToGroupId[$this->_sourceData['title']];
		} catch (Exception $e) {
			// do nothing
		}
		return false;
	}
}