<?php
/**
 * @package framework.indexer
 * Solr specific implementation of indexer_SearchResults
 */
class indexer_SolrSearchResults extends ArrayObject implements indexer_SearchResults
{
	private $totalHits;
	private $offset;
	private $returnedHits;
	private $maxScore;
	private $results = array();
	private $rows = 0;
	
	/**
	 * @var indexer_FacetResult[]
	 */
	private $facetResults;
	/**
	 * @var String
	 */
	private $suggestion;

	public function __construct($data = null)
	{
		$dom = f_util_DOMUtils::fromString($data);
		$resultElem = $dom->findUnique("result[@name = 'response']");
		if ($resultElem === null)
		{
			throw new Exception("No result from SolR ".$data);
		}

		$this->totalHits = intval($resultElem->getAttribute("numFound"));
		$this->offset = intval($resultElem->getAttribute("start"));
		$this->maxScore = floatval($resultElem->getAttribute("maxScore"));

		$docs = $dom->find("doc", $resultElem);
		$this->returnedHits = $docs->length;

		for ($i = 0; $i < $docs->length; $i++)
		{
			$docElem = $docs->item($i);
			$result = new indexer_SearchResult();
			for ($j = 0; $j < $docElem->childNodes->length; $j++)
			{
				$fieldElem = $docElem->childNodes->item($j);
				$name = $this->trimFieldSuffix($fieldElem->getAttribute("name"));
				if ($fieldElem->tagName == "arr")
				{
					$value = array();
					for ($k = 0; $k < $fieldElem->childNodes->length; $k++)
					{
						$value[] = $fieldElem->childNodes->item($k)->textContent;
					}
				}
				else
				{
					$value = $fieldElem->textContent;
				}
				// trim suffix if needed
				if ($name == "score")
				{
					$result->setProperty("normalizedScore", $this->normalizeScore((float)$value));
				}
				$result->setProperty($name, $value);
			}
			$this->results[] = $result;
		}

		$this->rows = intval($dom->findUnique("lst[@name = 'responseHeader']/lst[@name = 'params']/str[@name = 'rows']")->textContent);

		// Deal with facet
		$facetResults = array();
		$facetCountsElem = $dom->findUnique("lst[@name = 'facet_counts']");
		if ($facetCountsElem !== null)
		{
			$facetFieldsElem = $dom->findUnique("lst[@name = 'facet_fields']", $facetCountsElem);
			if ($facetFieldsElem !== null)
			{
				for ($i = 0; $i < $facetFieldsElem->childNodes->length; $i++)
				{
					$childNode = $facetFieldsElem->childNodes->item($i);
					if ($childNode->nodeType !== XML_ELEMENT_NODE)
					{
						continue;
					}
					$facetResult = new indexer_FacetResult($childNode, $this->totalHits);
					$facetResults[$facetResult->getSimpleFieldName()] = $facetResult;
				}
			}
			$facetQueriesElem = $dom->findUnique("lst[@name = 'facet_queries']", $facetCountsElem);
			if ($facetQueriesElem !== null)
			{
				$rangeFacets = array();
				foreach ($dom->find("int", $facetQueriesElem) as $facetIntElem)
				{
					$matches = null;
					if (preg_match('/^(.*):(\[.*\])$/', $facetIntElem->getAttribute("name"), $matches))
					{
						$fieldName = $matches[1];
						$range = $matches[2];
						if (!isset($rangeFacets[$fieldName]))
						{
							$rangeFacets[$fieldName] = array();
						}
						$rangeFacets[$fieldName][] = new indexer_RangeFacetCount($range, intval($facetIntElem->textContent));
					}
					else
					{
						// For now, only simple range queries supported for facets
					}
				}
				foreach ($rangeFacets as $fieldName => $facetCounts)
				{
					$facetResult = new indexer_RangeFacetResult($fieldName, $facetCounts, $this->totalHits);
					$facetResults[$facetResult->getSimpleFieldName()] = $facetResult;
				}
			}
		}
		
		$this->facetResults = $facetResults;
		
		// Suggestions
		$suggestionElem = $dom->findUnique("lst[@name='spellcheck']/lst[@name='suggestions']/str[@name='collation']");
		if ($suggestionElem !== null)
		{
			$this->suggestion = $suggestionElem->textContent;
		}

		// Deal with highlighting
		$hightlightElem = $dom->findUnique("lst[@name='highlighting']");
		if ($hightlightElem !== null) 
		{
			// We received some highlighting results
			$idx = 0;
			foreach ($dom->find("lst", $hightlightElem) as $hightlightLst)
			{
				$hlArray = array();
				foreach ($dom->find("arr", $hightlightLst) as $hightlightArr)
				{
					$fieldName = $this->trimFieldSuffix($hightlightArr->getAttribute("name"));
					$lstStr = $dom->findUnique("str", $hightlightArr);
					if ($lstStr !== null)
					{
						$hlArray[$fieldName] = $lstStr->textContent;
					}
				}
				$this->results[$idx]->setProperty("highlighting", $hlArray);
				$idx++;
			}
		}
		parent::__construct($this->results);
	}

	public function getTotalHitsCount()
	{
		return $this->totalHits;
	}
	public function getReturnedHitsCount()
	{
		return $this->returnedHits;
	}
	public function getFirstHitOffset()
	{
		return $this->offset;
	}
	public function getReturnedHits()
	{
		return $this->results;
	}

	public function getRequestedHitsPerPageCount()
	{
		return $this->rows;
	}

	/**
	 * @return indexer_FacetResult
	 */
	public function getFacetResult($fieldName)
	{
		$simpleFieldName = indexer_Field::getSimpleFieldName($fieldName);
		if (!isset($this->facetResults[$simpleFieldName]))
		{
			return null;
		}
		return $this->facetResults[$simpleFieldName];
	}

	/**
	 * @return indexer_FacetResult[]
	 */
	public function getFacetResults()
	{
		return $this->facetResults;
	}
	
	/**
	 * @return String
	 */
	public function getSuggestion()
	{
		return $this->suggestion;
	}

	private function trimFieldSuffix($fieldName)
	{
		$elems = preg_split('/_([a-z]{2}||idx_float|idx_int|idx_str|idx_dt)$/', $fieldName);
		return $elems[0];
	}

	private function normalizeScore($value)
	{
		if ( is_null($this->maxScore) || $this->maxScore < 0.1 )
		{
			return 0;
		}
		else
		{
			return $value/$this->maxScore;
		}
	}
}