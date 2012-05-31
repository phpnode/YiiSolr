<?php
/**
 * A common interface for solr connections
 * @author Charles Pick / PeoplePerHour.com
 * @package packages.solr
 */
interface IASolrConnection
{
	/**
	 * Gets the solr client instance
	 * @return SolrClient the solr client
	 */
	public function getClient();

	/**
	 * Adds a document to the solr index
	 * @param ASolrDocument|SolrInputDocument $document the document to add to the index
	 * @param integer $commitWithin the number of milliseconds to commit within after indexing the document
	 * @return boolean true if the document was indexed successfully
	 */
	public function index($document, $commitWithin = null);


	/**
	 * Deletes a document from the solr index
	 * @param mixed $document the document to remove from the index, this can be the an id or an instance of ASoldDocument, an array of multiple values can also be used
	 * @return boolean true if the document was deleted successfully
	 */
	public function delete($document);

	/**
	 * Sends a commit command to solr.
	 * @return boolean true if the commit was successful
	 */
	public function commit();

	/**
	 * Makes a solr search request
	 * @param ASolrCriteria $criteria the search criteria
	 * @param string $modelClass the name of the model to use when instantiating results
	 * @return ASolrQueryResponse the response from solr
	 */
	public function search(ASolrCriteria $criteria, $modelClass = "ASolrDocument");

	/**
	 * Counts the number of rows that match the given criteria
	 * @param ASolrCriteria $criteria the search criteria
	 * @return integer the number of matching rows
	 */
	public function count(ASolrCriteria $criteria);

	/**
	 * Gets the last received solr query response
	 * @return ASolrQueryResponse the last query response, or null if there are no responses yet
	 */
	public function getLastQueryResponse();

	/**
	 * Reset the solr client
	 */
	public function resetClient();
}