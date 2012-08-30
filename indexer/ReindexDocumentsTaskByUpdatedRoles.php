<?php

class f_tasks_ReindexDocumentsByUpdatedRolesTask extends task_SimpleSystemTask  
{
	/**
	 * @see task_SimpleSystemTask::execute()
	 *
	 */
	protected function execute()
	{
		$models = array();
		foreach ($this->getParameter("updatedRoles") as $roleName)
		{
			$models =  array_unique(array_merge($models, indexer_IndexService::getInstance()->getIndexableDocumentModelsForModifiedRole($roleName)));
		}
		
		$errors = array();
		$this->processModels($models, $errors);
		if (count($errors))
		{
			throw new Exception(implode("\n", $errors));
		}
	}
	
	private function processModels($modelsName, &$errors)
	{
		if (count($modelsName) == 0) {return ;}
		
		$totalDocumentCount = 0;
		$scriptPath = 'framework/indexer/chunkDocumentIndexer.php';
		$logs = LoggingService::getInstance();
		$chunkSize = 500;
				
		$logs->namedLog(__METHOD__ . "\t START", 'indexer');
		foreach ($modelsName as $modelName) 
		{
			$documentIndex = 0;
			$progres = true;
			$logs->namedLog("Processing $modelName", 'indexer');
			while ($progres) 
			{
				$this->plannedTask->ping();
				$output = f_util_System::execScript($scriptPath, array($modelName, $documentIndex, $chunkSize, 1));
				if (!is_numeric($output))
				{
					$progres = false;
					$chunkInfo = " Error on processsing $modelName at index $documentIndex: $output";
					$errors[] = $chunkInfo;
				}
				if (intval($output) == $chunkSize)
				{
					$documentIndex += $chunkSize; 
					$totalDocumentCount += $chunkSize; 
					$chunkInfo = " $modelName processed: " . $documentIndex;
				}
				else
				{
					$documentIndex += intval($output); 
					$totalDocumentCount += intval($output);	
					$progres = false;
					$chunkInfo = " $modelName processed Total: $documentIndex";
				}
				$logs->namedLog($chunkInfo, 'indexer');
			} 	
		}
		$logs->namedLog(__METHOD__ . "\tEND TOTAL " . $totalDocumentCount, 'indexer');
	}
}