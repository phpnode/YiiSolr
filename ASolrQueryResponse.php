<?php
/**
 * Wraps a solr query response
 * @author Charles Pick / PeoplePerHour.com
 * @package packages.solr
 */
class ASolrQueryResponse extends CComponent {
	/**
	 * The class name of the model that represents the results
	 * @var string
	 */
	protected $_modelClass;
	/**
	 * Holds the solr query response
	 * @var SolrObject
	 */
	protected $_solrObject;

	/**
	 * Holds the solr criteria that caused the results
	 * @var ASolrCriteria
	 */
	protected $_criteria;
	/**
	 * The search results
	 * @var ASolrResultList
	 */
	protected $_results;

	/**
	 * The list of groups in the search results, if applicable
	 * @var ASolrGroup[]
	 */
	protected $_groups;

	/**
	 * A collection of query facets
	 * @var CAttributeCollection
	 */
	protected $_queryFacets;
	/**
	 * A collection of field facets
	 * @var CAttributeCollection
	 */
	protected $_fieldFacets;
	/**
	 * A collection of date facets
	 * @var CAttributeCollection
	 */
	protected $_dateFacets;
	/**
	 * A collection of range facets
	 * @var CAttributeCollection
	 */
	protected $_rangeFacets;

	/**
	 * Constructor.
	 * @param SolrObject $solrObject the response from solr
	 * @param ASolrCriteria $criteria the search criteria
	 * @param string $modelClass the name of the results model class
	 */
	public function __construct($solrObject, ASolrCriteria $criteria, $modelClass = "ASolrDocument") {
		$this->_solrObject = $solrObject;
		$this->_criteria = $criteria;
		$this->_modelClass = $modelClass;
	}

	/**
	 * Gets a list of groups in the search results, if this is
	 * a group query.
	 * @return ASolrGroup[] a list of groups
	 */
	public function getGroups() {
		if ($this->_groups === null)
			$this->loadGroups();
		return $this->_groups;
	}

	/**
	 * Loads the groups in the result set, if any
	 * @return boolean true if the groups were loaded successfully
	 */
	protected function loadGroups()
	{
		$this->_groups = new CAttributeCollection;
		if (!isset($this->getSolrObject()->grouped))
			return false;
		foreach($this->getSolrObject()->grouped as $name => $rawGroup) {
			$group = new ASolrGroup();
			$group->name = $name;
			$group->total = $rawGroup->matches;
			$this->processResults($rawGroup->doclist->docs, $group);
			$this->_groups->{$name} = $group;
		}
		return true;
	}
	/**
	 * Gets an array of date facets that belong to this query response
	 * @return ASolrFacet[]
	 */
	public function getDateFacets()
	{
		if ($this->_dateFacets === null)
			$this->loadFacets();

		return $this->_dateFacets;
	}

	/**
	 * Gets an array of field facets that belong to this query response
	 * @return ASolrFacet[]
	 */
	public function getFieldFacets()
	{
		if ($this->_fieldFacets === null)
			$this->loadFacets();

		return $this->_fieldFacets;
	}
	/**
	 * Gets an array of query facets that belong to this query response
	 * @return ASolrFacet[]
	 */
	public function getQueryFacets()
	{
		if ($this->_queryFacets === null)
			$this->loadFacets();

		return $this->_queryFacets;
	}
	/**
	 * Gets an array of range facets that belong to this query response
	 * @return ASolrFacet[]
	 */
	public function getRangeFacets()
	{
		if ($this->_rangeFacets === null)
			$this->loadFacets();

		return $this->_rangeFacets;
	}
	/**
	 * Loads the facet objects
	 * @return boolean true if facets were loaded
	 */
	protected function loadFacets() {
		$this->_dateFacets = new CAttributeCollection();
		$this->_dateFacets->caseSensitive = true;
		$this->_fieldFacets = new CAttributeCollection();
		$this->_fieldFacets->caseSensitive = true;
		$this->_queryFacets = new CAttributeCollection();
		$this->_queryFacets->caseSensitive = true;
		$this->_rangeFacets = new CAttributeCollection();
		$this->_rangeFacets->caseSensitive = true;
		if (!isset($this->getSolrObject()->facet_counts))
			return false;

		foreach($this->getSolrObject()->facet_counts as $facetType => $item) {
			foreach($item as $facetName => $values) {
				/*
				 * Using @ to suppress weird errors occactionally coming
				 * from the solr pecl extension:
				 * e.g. "Illegal member variable name"
				 */
				if (@is_object($values))
					$values = (array) $values;
				elseif (!@is_array($values))
					$values = array("value" => $values);

				$facet = new ASolrFacet($values);
				$facet->name = $facetName;
				$facet->type = $facetType;
				switch ($facetType) {
					case ASolrFacet::TYPE_DATE:
						$this->_dateFacets[$facet->name] = $facet;
						break;
					case ASolrFacet::TYPE_FIELD:
						$this->_fieldFacets[$facet->name] = $facet;
						break;
					case ASolrFacet::TYPE_QUERY:
						$this->_queryFacets[$facet->name] = $facet;
						break;
					case ASolrFacet::TYPE_RANGE:
						$this->_rangeFacets[$facet->name] = $facet;
						break;
				}
			}
		}
		return true;
	}


	/**
	 * Gets the SolrObject object wrapped by this class
	 * @return SolrObject the solr query response object
	 */
	public function getSolrObject()
	{
		return $this->_solrObject;
	}

	/**
	 * Gets the list of search results
	 * @return ASolrResultList the solr results
	 */
	public function getResults()
	{
		if ($this->_results === null) {
			$this->_results = new ASolrResultList;
			$this->_results->total = isset($this->_solrObject->response->numFound) ? $this->_solrObject->response->numFound : 0;
			if ($this->_results->total)
				$this->processResults($this->_solrObject->response->docs,$this->_results);
		}
		return $this->_results;
	}

	/**
	 * Processes a list of results
	 * @param SolrObject $rawResults the raw results to process
	 * @param ASolrResultList $list the list of results
	 */
	protected function processResults($rawResults, $list = null) {
		if ($list === null)
			$list = new ASolrResultList;
		$modelClass = $this->_modelClass;
		$highlighting = isset($this->_solrObject->highlighting);
		if ($highlighting)
			$highlights = array_values((array) $this->_solrObject->highlighting);
		if ($rawResults) {
			foreach($rawResults as $n => $row) {
				$result = $modelClass::model()->populateRecord($row); /* @var ASolrDocument $result */
				$result->setPosition($n + $this->_criteria->getOffset());
				$result->setSolrResponse($this);
				if ($highlighting && isset($highlights[$n]))
					$result->setHighlights($highlights[$n]);

				$list->add($result);
			}
		}
		return $list;
	}
    public function getScoreAnalysis($id) {
        return isset($this->getSolrObject()->debug['explain'][$id]) ?
            $this->getSolrObject()->debug['explain'][$id] :
            null;
    }
}
