<?php
/**
 * Allows easy indexing and searching for Active Records using Solr.
 *
 * Usage:
 * <pre>
 * $model = User::model()->find();
 * $behavior = new ASolrSearchable();
 * $behavior->attributes = array(
 *  "name", "skills", "country.name"
 * );
 * $model->attachBehavior("ASolrSearchable", $behavior);
 * $model->index(); // adds the user to the solr index
 *
 * $model->name = "Test User";
 * $model->save(); // document is automatically reindexed
 * $model->address = "123 Fake Street";
 * $model->save(); // document is not reindexed because we don't care about the address field
 *
 * $criteria = new ASolrCriteria;
 * $criteria->query = "name:'Test User'";
 * $users = $model->findAllBySolr($criteria); // all the users with the name "Test User"
 *
 * $model->delete(); // document is automatically deleted from solr after the model is deleted
 * </pre>
 * @package packages.solr
 * @author Charles Pick / PeoplePerHour.com
 */
class ASolrSearchable extends CActiveRecordBehavior {
	/**
	 * The class name of the solr document to instantiate
	 * @var string
	 */
	public $documentClass = "ASolrDocument";

	/**
	 * Whether to automatically index or reindex the document when it changes.
	 * Defaults to true.
	 * @var boolean
	 */
	public $autoIndex = true;

	/**
	 * Whether to be smart about when to reindex documents.
	 * If this is true, changes will be pushed to solr only if attributes that
	 * we care about have changed.
	 * @var boolean
	 */
	public $smartIndex = true;

	/**
	 * The configuration for the associated ASolrDocument class
	 * @var array
	 */
	public $solrDocumentConfig = array();
	/**
	 * The solr document associated with this model instance
	 * @var ASolrDocument
	 */
	protected $_solrDocument;

	/**
	 * The solr criteria associated with this model instance
	 * @var ASolrCriteria
	 */
	protected $_solrCriteria;

	/**
	 * The attributes that should be indexed in solr
	 * @var array
	 */
	protected $_attributes;

	/**
	 * Stores the attributes of the model after it is found.
	 * Used to determine whether any of the attributes we care about have changed or not
	 * @var array
	 */
	protected $_oldAttributes = array();

	/**
	 * Sets the attributes that should be indexed in solr
	 * @param array $attributes
	 */
	public function setAttributes($attributes)
	{
		$a = array();
		foreach($attributes as $key => $value) {
			if (is_integer($key)) {
				$key = $value;
			}
			$a[$key] = $value;
		}
		$this->_attributes = $a;
	}

	/**
	 * Gets the attributes that should be indexed in solr
	 * @return array
	 */
	public function getAttributes()
	{
		if ($this->_attributes === null) {
			$names = $this->getOwner()->attributeNames();
			$this->_attributes = array_combine($names,$names);
		}
		return $this->_attributes;
	}
	/**
	 * Gets a list of objects and attributes that
	 * @return array a multidimensional array of objects and attributes
	 */
	protected function resolveAttributes() {
		$names = array();
		foreach($this->getAttributes() as $modelAttribute => $docAttribute) {
			if (!strstr($modelAttribute,".")) {
				$names[$modelAttribute] = array($this->getOwner(),$modelAttribute);
				continue;
			}
			$reference = $this->getOwner(); /* @var CActiveRecord $reference */
			$pointers = explode(".",$modelAttribute);
			$lastItem = array_pop($pointers);
			foreach($pointers as $pointer) {
				$reference = $reference->{$pointer};
			}
			$names[$modelAttribute] = array($reference, $lastItem);
		}
		return $names;
	}

	/**
	 * Resolves the attribute name to the field name on solr.
	 * Default implementation replaces "." with "__" (double underscore)
	 * <pre>
	 * echo $behavior->resolveAttributeName("name"); // "name"
	 * echo $behavior->resolveAttributeName("country.name"); // "country__name"
	 * </pre>
	 * @param $attributeName
	 * @return mixed
	 */
	protected function resolveAttributeName($attributeName) {
		$attributes = $this->getAttributes();
		$attributeName = $attributes[$attributeName];
		return str_replace(".","__",$attributeName);
	}

	/**
	 * Sets the solr document associated with this model instance
	 * @param ASolrDocument $solrDocument the solr document
	 */
	public function setSolrDocument($solrDocument)
	{
		$this->_solrDocument = $solrDocument;
	}

	/**
	 * Gets the solr document associated with this model instance.
	 * @param boolean $refresh whether to refresh the document, defaults to false
	 * @return ASolrDocument the solr document
	 */
	public function getSolrDocument($refresh = false)
	{
		if ($this->_solrDocument === null || $refresh) {
			$config = $this->solrDocumentConfig;
			$config['class'] = $this->documentClass;
			$this->_solrDocument = Yii::createComponent($config);

			foreach($this->resolveAttributes() as $attribute => $item) {
				list($object, $property) = $item;
				$resolvedAttributeName = $this->resolveAttributeName($attribute);
				if (is_object($object))
					$this->_solrDocument->{$resolvedAttributeName} = $object->{$property};
			}
		}
		return $this->_solrDocument;
	}

	/**
	 * Adds the solr document to the index
	 * @return boolean true if the document was indexed successfully
	 */
	public function index() {
		$document = $this->getSolrDocument(true);
		if (!$document->save()) {
			return false;
		}
		$this->_oldAttributes = array();
		foreach($this->resolveAttributes() as $key => $item) {
			list($object, $property) = $item;
			if (is_object($object))
				$this->_oldAttributes[$key] = $object->{$property};
		}
		return true;
	}
	/**
	 * Triggered after the attached model is found.
	 * Stores the current state of attributes we care about to see if they have changed.
	 * @param CEvent $event the event raised
	 */
	public function afterFind($event) {
		if ($this->smartIndex) {
			$this->_oldAttributes = array();
			foreach($this->resolveAttributes() as $key => $item) {
				list($object, $property) = $item;
				$this->_oldAttributes[$key] = $object->{$property};
			}
		}
	}

	/**
	 * Deletes the relevant document from the solr index after the model is deleted
	 * @param CEvent $event the event raised
	 */
	public function afterDelete($event) {
		$this->getSolrDocument()->delete();
	}
	/**
	 * Adds the relevant document to the solr index after the model is saved if $this->autoIndex is true.
	 * For existing records, the document will only be re-indexed if attributes we care about have changed.
	 * @param CEvent $event the event raised
	 */
	public function afterSave($event) {
		if (!$this->autoIndex || !$this->getIsModified()) {
			return;
		}
		$this->index();
	}
	/**
	 * Finds an active record that matches the given criteria using solr
	 * @param ASolrCriteria $criteria the solr criteria to use for searching
	 * @return CActiveRecord|null the found record, or null if nothing was found
	 */
	public function findBySolr($criteria = null) {
		$c = new ASolrCriteria();
		$c->mergeWith($this->getSolrCriteria());
		if ($criteria !== null) {
			$c->mergeWith($criteria);
		}
		if ($c->getQuery() == "") {
			$c->setQuery("*:*");
		}
		$document = $this->getSolrDocument()->find($c);
		if (!is_object($document)) {
			return null;
		}
		return $this->populateFromSolr($document,false);
	}

	/**
	 * Finds all active records that matches the given criteria using solr
	 * @param ASolrCriteria $criteria the solr criteria to use for searching
	 * @return CActiveRecord[] an array of results
	 */
	public function findAllBySolr($criteria = null) {
		$c = new ASolrCriteria();
		$c->mergeWith($this->getSolrCriteria());
		if ($criteria !== null) {
			$c->mergeWith($criteria);
		}
		if ($c->getQuery() == "") {
			$c->setQuery("*:*");
		}
		return $this->populateFromSolr($this->getSolrDocument()->findAll($c),true);

	}

	/**
	 * Populates active record objects from solr
	 * @param ASolrDocument|array $document the document(s) to populate the records from
	 * @param boolean $all whether to populate a list of records instead of just one, defaults to false
	 * @return CActiveRecord|array the active record(s) populated from solr
	 */
	public function populateFromSolr($document, $all = false) {
		if ($all) {
			$results = array();
			foreach($document as $doc) {
				$results[] = $this->populateFromSolr($doc,false);
			}
			return $results;
		}
		$relations = $this->getOwner()->getMetaData()->relations;
		$attributes = array();
		$relationAttributes = array();
		foreach($this->getAttributes() as $modelAttribute => $docAttribute) {
			$resolved = $this->resolveAttributeName($modelAttribute);
			if (!strstr($modelAttribute,".")) {
				$attributes[$modelAttribute] = $document->{$resolved};
				continue;
			}
			$reference = &$relationAttributes;
			$pointers = explode(".",$modelAttribute);
			$last = array_pop($pointers);
			foreach($pointers as $pointer) {
				if (!isset($reference[$pointer])) {
					$reference[$pointer] = array();
				}
				$reference =& $reference[$pointer];
			}
			$reference[$last] = $document->{$resolved};
		}
		$modelClass = get_class($this->getOwner());
		$model = $modelClass::model()->populateRecord($attributes);
		if (count($relationAttributes)) {
			foreach($relationAttributes as $relationName => $attributes) {
				$relationClass = $relations[$relationName]->className;
				$model->{$relationName} = $relationClass::model()->populateRecord($attributes);
			}
		}
		return $model;
	}

	/**
	 * Determines whether any attributes that we care about on the model have been modified or not.
	 * @return boolean true if the item has been modified, otherwise false
	 */
	public function getIsModified() {
		if (!$this->smartIndex || count($this->_oldAttributes) == 0) {
			return true;
		}
		foreach($this->resolveAttributes() as $key => $item) {
			if (!isset($this->_oldAttributes[$key])) {
				return true;
			}
			list($object, $property) = $item;

			if ($this->_oldAttributes[$key] != $object->{$property}) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resets the scope
	 * @return ASolrSearchable $this with the scope reset
	 */
	public function resetScope() {
		$this->_solrCriteria = null;
		return $this;
	}

	/**
	 * Sets the solr criteria associated with this model
	 * @param ASolrCriteria $solrCriteria the solr criteria
	 */
	public function setSolrCriteria($solrCriteria) {
		$this->_solrCriteria = $solrCriteria;
	}

	/**
	 * Gets the solr criteria associated with this model
	 * @return ASolrCriteria the solr criteria
	 */
	public function getSolrCriteria() {
		if ($this->_solrCriteria === null) {
			$this->_solrCriteria = new ASolrCriteria();
		}
		return $this->_solrCriteria;
	}
}
