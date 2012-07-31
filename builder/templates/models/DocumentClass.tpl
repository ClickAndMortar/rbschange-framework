<?php
/**
 * Class where to put your custom methods for document <{$model->getDocumentClassName()}>
 * @package modules.<{$model->getModuleName()}>.persistentdocument
 */
class <{$model->getDocumentClassName()}> extends <{$model->getDocumentClassName()}>base <{if $model->isIndexable()}>implements indexer_IndexableDocument<{/if}>

{
<{if $model->isIndexable()}>
	/**
	 * Get the indexable document
	 *
	 * @return indexer_IndexedDocument
	 */
	public function getIndexedDocument()
	{
		$indexedDoc = new indexer_IndexedDocument();
		// TODO : set the different properties you want in you indexedDocument :
		// - please verify that id, documentModel, label and lang are correct according your requirements
		// - please set text value.
		$indexedDoc->setId($this->getId());
		$indexedDoc->setDocumentModel('<{$model->getName()}>');
		$indexedDoc->setLabel($this->getNavigationLabel());
<{if $model->isInternationalized() }>
		$indexedDoc->setLang(RequestContext::getInstance()->getLang());
<{else}>
		$indexedDoc->setLang($this->getLang());
<{/if}>
		$indexedDoc->setText(null); // TODO : please fill text property
		return $indexedDoc;
	}
	
<{/if}>
<{if $model->getFinalDocumentName() == 'preferences'}>
	/**
	 * @retrun String
	 */
	public function getLabel()
	{
		return f_Locale::translateUI(parent::getLabel());
	}
	
<{/if}>
}