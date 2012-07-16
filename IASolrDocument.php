<?php
/**
 * Interface for solr documents
 */
interface IASolrDocument
{
    /**
	 * Returns the static model of the specified solr document class.
	 * The model returned is a static instance of the solr document class.
	 * It is provided for invoking class-level methods (something similar to static class methods.)
	 *
	 * EVERY derived solr document  class must override this method as follows,
	 * <pre>
	 * public static function model($className=__CLASS__)
	 * {
	 *	 return parent::model($className);
	 * }
	 * </pre>
	 *
	 * @param string $className solr document class name.
	 * @return ASolrDocument solr document model instance.
	 */
	public static function model($className = __CLASS__);

    /**
	 * Returns the solr connection used by solr document.
	 * By default, the "solr" application component is used as the solr connection.
	 * You may override this method if you want to use a different solr connection.
	 * @return ASolrConnection the solr connection used by solr document.
	 */
	public function getSolrConnection();

    /**
	 * Returns the primary key value.
	 * @return mixed the primary key value. An array (column name=>column value) is returned if the primary key is composite.
	 * If primary key is not defined, null will be returned.
	 */
	public function getPrimaryKey();

    /**
	 * Sets the position in the search results
	 * @param integer $position
	 */
	public function setPosition($position);

	/**
	 * Gets the position in the search results
	 * @return integer the position in the search results
	 */
	public function getPosition();
    /**
	 * Sets the solr query response.
	 * @param ASolrQueryResponse $solrResponse the response from solr that this model belongs to
	 */
	public function setSolrResponse($solrResponse);

	/**
	 * Gets the response from solr that this model belongs to
	 * @return ASolrQueryResponse the solr query response
	 */
	public function getSolrResponse();
    /**
	 * Gets the solr input document
	 * @return SolrInputDocument the solr document
	 */
	public function getInputDocument();
    /**
	 * Sets the highlights for this record
	 * @param array $highlights the highlights, attribute => highlights
	 */
	public function setHighlights($highlights);

	/**
	 * Gets the highlights if highlighting is enabled
	 * @param string|null $attribute the attribute to get highlights for, if null all attributes will be returned
	 * @return array|boolean the highlighted results
	 */
	public function getHighlights($attribute = null);
    /**
	 * Finds a single solr document according to the specified criteria.
	 * @param ASolrCriteria $criteria solr query criteria.
	 * @return ASolrDocument the document found. Null if none is found.
	 */
	public function find(ASolrCriteria $criteria = null);

	/**
	 * Finds multiple solr documents according to the specified criteria.
	 * @param ASolrCriteria $criteria solr query criteria.
	 * @return ASolrDocument[] the documents found.
	 */
	public function findAll(ASolrCriteria $criteria = null);

    /**
	 * Returns the number of documents matching specified criteria.
	 * @param ASolrCriteria $criteria solr query criteria.
	 * @return integer the number of rows found
	 */
	public function count(ASolrCriteria $criteria = null);
    /**
	 * Creates a solr document with the given attributes.
	 * This method is internally used by the find methods.
	 * @param array $attributes attribute values (column name=>column value)
	 * @param boolean $callAfterFind whether to call {@link afterFind} after the record is populated.
	 * @return ASolrDocument the newly created solr document. The class of the object is the same as the model class.
	 * Null is returned if the input data is false.
	 */
	public function populateRecord($attributes,$callAfterFind=true);

}