<?
/**
 * The custom, extended implementation of the '_Common' Row.
 * This table incorporates all common functionality/columns that should belong to nearly every table. This is an abstract table, so it doesn't really exist in the database, but is available to extend from
 */
class _Common extends \SmartRow {
	/**
	 * This is an abstract table, so it doesn't really exist in the database. Thus, we must pass the $tableName through the constructor.
	 * @param string $tableName
	 * @param SmartDatabase $Database
	 * @param mixed $ids [optional]
	 * @param array $options [optional]
	 */
	public function __construct($tableName, $Database, $ids=null, $options=null) {
		parent::__construct($tableName, $Database, $ids, $options);

		$this->OnBeforeInsert('UpdateDateCreatedTimestamp', $this);
		$this->OnBeforeCommit('UpdateLastModifiedTimestamp', $this);
	}

	/**
	 * Invoked automatically as a callback before the row is committed with new/updated data
	 */
	protected function UpdateLastModifiedTimestamp($eventObject, $eventArgs) {
		if($this->Table->AutoCommit){
			//dont get stuck in a loop always updating the values
			$this->Table->AutoCommit = false;
			$this['DateLastModified'] = gmdate("Y-m-d H:i:s T");
			$this->Table->AutoCommit = true;
		}
		else $this['DateLastModified'] = gmdate("Y-m-d H:i:s T");
	}

	/**
	 * Invoked automatically as a callback when the row is created
	 */
	protected function UpdateDateCreatedTimestamp($eventObject, $eventArgs) {
		if($this->Table->AutoCommit){
			//dont get stuck in a loop always updating the values
			$this->Table->AutoCommit = false;
			$this['DateCreated'] = gmdate("Y-m-d H:i:s T");
			$this->Table->AutoCommit = true;
		}
		else $this['DateCreated'] = gmdate("Y-m-d H:i:s T");
	}
}
?>