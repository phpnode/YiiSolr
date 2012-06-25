<?php
/**
 * Simple load balancer for solr connections.
 * Dispatches reads to one server and writes to another.
 * Configuration:
 * <pre>
 * 'components' => array(
 *     'yourIndex' => array(
 *         'class' => 'packages.solr.ASolrLoadBalancer',
 *         'readConnection' => array(
 *             'clientOptions' => array(
 *                 'hostname' => 'firstserver',
 *                 'port' => '8080',
 *                 'path' => '/solr/core_something'
 *             ),
 *         ),
 *         'writeConnection' => array(
 *              'clientOptions' => array(
 *                  'hostname' => 'secondserver',
 *                  'port' => '8080',
 *                  'path' => '/solr/core_something'
 *              ),
 *          )
 *      ),
 *  ),
 * </pre>
 * @package packages.solr
 * @author Charles Pick / PeoplePerHour.com
 */
class ASolrLoadBalancer extends CApplicationComponent implements IASolrConnection
{
	/**
	 * The connection used for reading from solr
	 * @var ASolrConnection
	 */
	protected $_readConnection;

	/**
	 * The connection used for writing to solr
	 * @var ASolrConnection
	 */
	protected $_writeConnection;

	/**
	 * Gets the solr client instance
	 * @return SolrClient the solr client
	 */
	public function getClient()
	{
		if ($this->_writeConnection === null)
			if ($this->_readConnection === null)
				return null;
			else
				return $this->_readConnection->getClient();
		else
			return $this->_writeConnection->getClient();
	}

	/**
	 * Adds a document to the solr index
	 * @param ASolrDocument|SolrInputDocument $document the document to add to the index
	 * @param integer $commitWithin the number of milliseconds to commit within after indexing the document
	 * @return boolean true if the document was indexed successfully
	 */
	public function index($document, $commitWithin = null)
	{
		return $this->getWriteConnection()->index($document, $commitWithin);
	}

	/**
	 * Deletes a document from the solr index
	 * @param mixed $document the document to remove from the index, this can be the an id or an instance of ASoldDocument, an array of multiple values can also be used
	 * @return boolean true if the document was deleted successfully
	 */
	public function delete($document)
	{
		return $this->getWriteConnection()->delete($document);
	}

	/**
	 * Sends a commit command to solr.
	 * @return boolean true if the commit was successful
	 */
	public function commit()
	{
		return $this->getWriteConnection()->commit();
	}

	/**
	 * Makes a solr search request
	 * @param ASolrCriteria $criteria the search criteria
	 * @param string $modelClass the name of the model to use when instantiating results
	 * @return ASolrQueryResponse the response from solr
	 */
	public function search(ASolrCriteria $criteria, $modelClass = "ASolrDocument")
	{
		return $this->getReadConnection()->search($criteria, $modelClass);
	}

	/**
	 * Counts the number of rows that match the given criteria
	 * @param ASolrCriteria $criteria the search criteria
	 * @return integer the number of matching rows
	 */
	public function count(ASolrCriteria $criteria)
	{
		return $this->getReadConnection()->count($criteria);
	}

	/**
	 * Gets the last received solr query response
	 * @return ASolrQueryResponse the last query response, or null if there are no responses yet
	 */
	public function getLastQueryResponse()
	{
		return $this->getReadConnection()->getLastQueryResponse();
	}

	/**
	 * Reset the solr client
	 */
	public function resetClient()
	{
		$this->getWriteConnection()->resetClient();
		$this->getReadConnection()->resetClient();
	}

	/**
	 * Sets the connection used for reading from solr
	 * @param ASolrConnection|array $readConnection the solr connection or config
	 */
	public function setReadConnection($readConnection)
	{
		if (!($readConnection instanceof IASolrConnection)) {
			$attributes = $readConnection;
			$readConnection = new ASolrConnection();
			foreach($attributes as $attribute => $value)
				$readConnection->{$attribute} = $value;
		}
		$this->_readConnection = $readConnection;
	}

	/**
	 * Gets the connection used for reading from solr
	 * @return ASolrConnection the solr connection
	 */
	public function getReadConnection()
	{
		return $this->_readConnection;
	}

	/**
	 * Sets the connection used for writing to solr
	 * @param ASolrConnection $writeConnection the solr connection or config
	 */
	public function setWriteConnection($writeConnection)
	{
		 if (!($writeConnection instanceof IASolrConnection)) {
			$attributes = $writeConnection;
			$writeConnection = new ASolrConnection();
			foreach($attributes as $attribute => $value)
				$writeConnection->{$attribute} = $value;
		}
		$this->_writeConnection = $writeConnection;
	}

	/**
	 * Gets the connection used for writing to solr
	 * @return ASolrConnection
	 */
	public function getWriteConnection()
	{
		if ($this->_writeConnection === null)
			return $this->_readConnection;
		return $this->_writeConnection;
	}

}