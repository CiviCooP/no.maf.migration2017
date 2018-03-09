<?php

class CRM_Migration_Page_MigrateContribution extends CRM_Core_Page {
	
	const QUEUE_NAME = 'no.maf.migration.migrationcontribution';
	
	const BATCH_SIZE = 10;
		
	function run() {
    //retrieve the queue
    $queue = self::getQueue();
    $runner = new CRM_Queue_Runner(array(
      'title' => ts('Migrate contribution'), //title fo the queue
      'queue' => $queue, //the queue object
      'errorMode'=> CRM_Queue_Runner::ERROR_ABORT, //abort upon error and keep task in queue
      'onEnd' => array('CRM_Migration_Page_MigrateContribution', 'onEnd'), //method which is called as soon as the queue is finished
      'onEndUrl' => CRM_Utils_System::url('civicrm', 'reset=1'), //go to page after all tasks are finished
    ));
		
		$count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM migration_contribution WHERE is_processed = 0");
		$steps = $count / self::BATCH_SIZE;
		$steps ++;
		for($i=0; $i<$steps; $i++) {
			//create a task without parameters
    	$task = new CRM_Queue_Task(
      	array('CRM_Migration_Page_MigrateContribution', 'migrate'), //call back method
      	array(), //parameters,
      	'Migrated '.($i*self::BATCH_SIZE).' of '.$count
    	);
    	//now add this task to the queue
    	$queue->createItem($task);
		}
 
    $runner->runAllViaWeb(); // does not return
  }
	
	public static function getQueue() {
		return CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => self::QUEUE_NAME,
      'reset' => false, //do not flush queue upon creation
    ));
	}
 
  /**
   * Handle the final step of the queue
   */
  public static function onEnd(CRM_Queue_TaskContext $ctx) {
    //set a status message for the user
    CRM_Core_Session::setStatus('All tasks in queue are executes', 'Queue', 'success');
  }
	
	public static function migrate(CRM_Queue_TaskContext $ctx) {
    $return = civicrm_api3('Contribution', 'Migrate', array('options' => array('limit' => self::BATCH_SIZE)));
    return true;
  }
	
}
