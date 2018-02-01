<?php

/**
 * Class for MAF Norge Entity Tag Migration to CiviCRM
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @date 31 Jan 2018
 * @license AGPL-3.0
 */
class CRM_Migration_EntityTag extends CRM_Migration_MAF {

  /**
   * Method to migrate incoming data
   *
   * @return bool|array
   */
  public function migrate() {
    if ($this->validSourceData()) {
    	try {
	    	$result = civicrm_api3('EntityTag', 'create', array(
	    		'tag_id' => $this->_sourceData['tag_id'],
	    		'entity_id' => $this->_sourceData['contact_id'],
	    		'entity_table' => 'civicrm_contact',
	 			));
				return true;
			} catch (Exception $e) {
				$this->_logger->logMessage('Error', 'Entity tag has no contact_id, not migrated. Entity tag id is '
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
      $this->_logger->logMessage('Error', 'Entity tag has no contact_id, not migrated. Group contact id is '
        .$this->_sourceData['id']);
      return FALSE;
    }
    // find new contact id and error if not found
    $newContactId = $this->findNewContact($this->_sourceData['contact_id']);
    if ($newContactId) {
       $this->_sourceData['contact_id'] = $newContactId;
    } else {
      $this->_logger->logMessage('Error', 'Could not find a new contact with the source contact ID '
        .$this->_sourceData['contact_id'].', entity tag not migrated');
      return FALSE;
    }
		
		// find or create tag
		$newTagId = $this->findOrCreateTag();
		if ($newTagId) {
			$this->_sourceData['tag_id'] = $newTagId;
		} else {
			$this->_logger->logMessage('Error', 'Could not find a new tag id for old tag id '
        .$this->_sourceData['tag_id'].', entity tag not migrated');
			return FALSE;
		}
		
		return TRUE;
	}

	private function findOrCreateTag() {		
		$tag_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_tag WHERE name = %1", array(
			1 => array($this->_sourceData['name'], 'String'),
		));
		if ($tag_id) {
			return $tag_id;
		}
		
		$tagParams['name'] = $this->_sourceData['name'];
		$tagParams['is_selectable'] = 1;
		$tagParams['used_for'] = 'Contacts';
		try {
			$result = civicrm_api3('Tag', 'create', $tagParams);
			
			// Find the id of the tag.
			$tag_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_tag WHERE name = %1", array(
				1 => array($this->_sourceData['name'], 'String'),
			));
			if ($tag_id) {
				return $tag_id;
			}
			
		} catch (Exception $e) {
			// do nothing
		}
		return false;
	}
}