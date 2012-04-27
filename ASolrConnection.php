<?php
/**
 * A simple wrapper for Solr
 * @author Charles Pick / PeoplePerHour.com
 * @package packages.solr
 */
class ASolrConnection extends CApplicationComponent {
	/**
	 * Whether to profile solr queries or not
	 * @var boolean
	 */
	public $enableProfiling = true;
	/**
	 * The solr client object
	 * @var SolrClient
	 */
	protected $_client;
	/**
	 * The options used to initialize the client class
	 * @var CAttributeCollection
	 */
	protected $_clientOptions;

	/**
	 * Holds the last received query response
	 * @var ASolrQueryResponse
	 */
	protected $_lastQueryResponse;
	/**
	 * Sets the solr client
	 * @param SolrClient $client the solr client
	 */
	public function setClient($client) {
		$this->_client = $client;
	}

	/**
	 * Gets the solr client instance
	 * @return SolrClient the solr client
	 */
	public function getClient() {
		if ($this->_client === null) {
			$this->_client = new SolrClient($this->getClientOptions()->toArray());
		}
		return $this->_client;
	}

	/**
	 * Sets the solr client options
	 * @param array|CAttributeCollection $clientOptions the client options
	 */
	public function setClientOptions($clientOptions) {
		if (!($clientOptions instanceof CAttributeCollection)) {
			$data = $clientOptions;
			$clientOptions = new CAttributeCollection();
			$clientOptions->caseSensitive = true;
			foreach($data as $key => $value) {
				$clientOptions->add($key,$value);
			}
		}
		else {
			$clientOptions->caseSensitive = true;
		}
		$this->_clientOptions = $clientOptions;
	}

	/**
	 * Gets the solr client options
	 * @return CAttributeCollection the client options
	 */
	public function getClientOptions() {
		if ($this->_clientOptions === null) {
			$clientOptions = new CAttributeCollection();
			$clientOptions->caseSensitive = true;
			if ($this->_client !== null) {
				foreach($this->_client->getOptions() as $key => $value) {
					$clientOptions->add($key,$value);
				}
			}
			$this->_clientOptions = $clientOptions;
		}
		return $this->_clientOptions;
	}
	/**
	 * Adds a document to the solr index
	 * @param ASolrDocument|SolrInputDocument $document the document to add to the index
	 * @param integer $commitWithin the number of milliseconds to commit within after indexing the document
	 * @return boolean true if the document was indexed successfully
	 */
	public function index($document, $commitWithin = null) {
		if ($document instanceof ASolrDocument) {
			if ($commitWithin === null && $document->getCommitWithin() > 0) {
				$commitWithin = $document->getCommitWithin();
			}
			$document = $document->getInputDocument();
		}
		elseif (is_array($document) || $document instanceof Traversable) {
			if ($commitWithin === null) {
				$commitWithin = 0;
			}
			$document = (array) $document;
			foreach($document as $key => $value) {
				if ($value instanceof ASolrDocument) {
					$document[$key] = $value->getInputDocument();
				}
			}
			Yii::trace('Adding '.count($document)." documents to the solr index",'packages.solr.ASolrConnection');
			return $this->getClient()->addDocuments($document,false, $commitWithin)->success();
		}
		if ($commitWithin === null) {
			$commitWithin = 0;
		}
		Yii::trace('Adding 1 document to the solr index','packages.solr.ASolrConnection');
		$response = $this->getClient()->addDocument($document, false,$commitWithin);
		return $response->success();
	}


	/**
	 * Deletes a document from the solr index
	 * @param mixed $document the document to remove from the index, this can be the an id or an instance of ASoldDocument, an array of multiple values can also be used
	 * @return boolean true if the document was deleted successfully
	 */
	public function delete($document) {
		if ($document instanceof ASolrDocument) {
			$document = $document->getPrimaryKey();
		}
		elseif (is_array($document) || $document instanceof Traversable) {
			$document = (array) $document;
			foreach($document as $key => $value) {
				if ($value instanceof ASolrDocument) {
					$document[$key] = $value->getPrimaryKey();
				}
			}
			Yii::trace('Deleting From Solr IDs: '.implode(", ",$document),'packages.solr.ASolrConnection');
			return $this->getClient()->deleteByIds($document)->success();
		}
		Yii::trace('Deleting From Solr ID: '.$document,'packages.solr.ASolrConnection');
		return $this->getClient()->deleteById($document)->success();
	}
	/**
	 * Sends a commit command to solr.
	 * @return boolean true if the commit was successful
	 */
	public function commit() {
		return $this->getClient()->commit()->success();
	}
	/**
	 * Makes a solr search request
	 * @param ASolrCriteria $criteria the search criteria
	 * @param string $modelClass the name of the model to use when instantiating results
	 * @return ASolrQueryResponse the response from solr
	 */
	public function search(ASolrCriteria $criteria, $modelClass = "ASolrDocument") {
		if (is_object($modelClass)) {
			$modelClass = get_class($modelClass);
		}
		$c = new ASolrCriteria();
		$c->mergeWith($criteria);

		Yii::trace('Querying Solr: '.((string) $c),'packages.solr.ASolrConnection');
		$this->_lastQueryResponse = new ASolrQueryResponse($this->rawSearch($c),$c,$modelClass);
		return $this->_lastQueryResponse;
	}

	/**
	 * Counts the number of rows that match the given criteria
	 * @param ASolrCriteria $criteria the search criteria
	 * @return integer the number of matching rows
	 */
	public function count(ASolrCriteria $criteria) {
		$c = new ASolrCriteria();
		$c->mergeWith($criteria);
		$c->setLimit(0);
		Yii::trace('Counting Results from Solr: '.((string) $c),'packages.solr.ASolrConnection');
		return $this->rawSearch($c)->response->numFound;
	}

	/**
	 * Makes a search query with the given criteria and returns the raw solr object.
	 * Usually you should use the search() method instead.
	 * @param ASolrCriteria $criteria the search criteria
	 * @return SolrObject the response from solr
	 */
	protected function rawSearch(ASolrCriteria $criteria) {
		if ($this->enableProfiling) {
			$profileTag = "packages.solr.AConnection.rawSearch(".$criteria->__toString().")";
			Yii::beginProfile($profileTag);
		}
		$this->resetClient(); // solr client is not safely reusable, reset before every request
		$response = $this->getClient()->query($criteria)->getResponse();
		if ($this->enableProfiling)
			Yii::endProfile($profileTag);

		return $response;
	}
	/**
	 * Gets the last received solr query response
	 * @return ASolrQueryResponse the last query response, or null if there are no responses yet
	 */
	public function getLastQueryResponse() {
		return $this->_lastQueryResponse;
	}

	/**
	 * Reset the solr client
	 */
	public function resetClient() {
		$this->_client = new SolrClient($this->getClientOptions()->toArray());
	}
}
