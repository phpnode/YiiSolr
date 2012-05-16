<?php
/**
 * Allows access to solr documents using the active record pattern.
 * <pre>
 * $document = ASolrDocument::model()->findByPk(1); // loads a document with a unique id of 1
 * $document->name = "a test name"; // sets the name attribute on the document
 * $document->save(); // saves the document to solr
 * $document->delete(); // deletes the document from solr
 * </pre>
 * @author Charles Pick / PeoplePerHour.com
 * @package packages.solr
 */
class ASolrDocument extends CFormModel {
	/**
	 * The solr connection
	 * @var ASolrConnection
	 */
	public static $solr;

	/**
	 * The number of milliseconds to commit saves and updates within.
	 * @var integer
	 */
	protected $_commitWithin = 0;

	/**
	 * The document attributes.
	 * @var CAttributeCollection
	 */
	protected $_attributes;
	/**
	 * Holds the solr query response after a find call
	 * @var ASolrQueryResponse
	 */
	protected $_solrResponse;

	/**
	 * The connection to solr
	 * @var ASolrConnection
	 */
	protected $_connection;
	/**
	 * Holds the solr criteria
	 * @var ASolrCriteria
	 */
	protected $_solrCriteria;

	/**
	 * The position in the search results
	 * @var integer
	 */
	protected $_position;

	/**
	 * Holds the document score
	 * @var integer
	 */
	protected $_score;

	/**
	 * The solr input document
	 * @var SolrInputDocument
	 */
	protected $_inputDocument;

	/**
	 * The old primary key value
	 * @var mixed
	 */
	private $_pk;
	/**
	 * Whether this is a new document or not
	 * @var boolean
	 */
	private $_new = true;

	/**
	 * @var array the highlights for this record if highlighting is enabled
	 */
	private $_highlights;

	/**
	 * An array of static model instances, clas name => model
	 * @var array
	 */
	private static $_models=array();


	/**
	 * Sets the document score
	 * @param integer $score
	 */
	public function setScore($score) {
		$this->_score = $score;
	}

	/**
	 * Gets the score for this document
	 * @return integer
	 */
	public function getScore() {
		return $this->_score;
	}

	/**
	 * Sets the position in the search results
	 * @param integer $position
	 */
	public function setPosition($position) {
		$this->_position = $position;
	}

	/**
	 * Gets the position in the search results
	 * @return integer the position in the search results
	 */
	public function getPosition() {
		return $this->_position;
	}
	/**
	 * Gets a list of attribute names on the model
	 * @return array the list of attribute names
	 */
	public function attributeNames()
	{
		return CMap::mergeArray(
				array_keys($this->_attributes->toArray()),
				parent::attributeNames()
			);
	}
	/**
	 * An array of attribute default values
	 * @return array
	 */
	public function attributeDefaults() {
		return array();
	}
	/**
	 * Checks whether this AR has the named attribute
	 * @param string $name attribute name
	 * @return boolean whether this AR has the named attribute (table column).
	 */
	public function hasAttribute($name)
	{
		return in_array($name, $this->attributeNames());
	}
	/**
	 * Constructor.
	 * @param string $scenario the scenario name
	 * See {@link CModel::scenario} on how scenario is used by models.
	 * @see getScenario
	 */
	public function __construct($scenario = "insert")
	{
		if ($scenario === null) {
			return;
		}
		$this->init();
		$this->attachBehaviors($this->behaviors());
		$this->afterConstruct();
	}

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
	public static function model($className=__CLASS__)
	{
		if(isset(self::$_models[$className]))
			return self::$_models[$className];
		else
		{
			$model=self::$_models[$className]=new $className(null);
			$model->attachBehaviors($model->behaviors());
			return $model;
		}
	}

	/**
	 * Returns the solr connection used by solr document.
	 * By default, the "solr" application component is used as the solr connection.
	 * You may override this method if you want to use a different solr connection.
	 * @return ASolrConnection the solr connection used by solr document.
	 */
	public function getSolrConnection()
	{
		if ($this->_connection !== null) {
			return $this->_connection;
		}
		elseif(self::$solr!==null) {
			return self::$solr;
		}
		else
		{
			self::$solr=Yii::app()->solr;
			if(self::$solr instanceof ASolrConnection)
				return self::$solr;
			else
				throw new CException(Yii::t('yii','Solr Document requires a "solr" ASolrConnection application component.'));
		}
	}
	/**
	 * Sets the solr connection used by this solr document
	 * @param ASolrConnection $connection the solr connection to use for this document
	 */
	public function setSolrConnection(ASolrConnection $connection) {
		$this->_connection = $connection;
	}
	/**
	 * Returns the solr query criteria associated with this model.
	 * @param boolean $createIfNull whether to create a criteria instance if it does not exist. Defaults to true.
	 * @return ASolrCriteria the query criteria that is associated with this model.
	 * This criteria is mainly used by {@link scopes named scope} feature to accumulate
	 * different criteria specifications.
	 */
	public function getSolrCriteria($createIfNull=true)
	{
		if($this->_solrCriteria===null)
		{
			if(($c=$this->defaultScope())!==array() || $createIfNull) {
				$this->_solrCriteria=new ASolrCriteria($c);

			}
		}
		return $this->_solrCriteria;
	}

	/**
	 * Sets the solr query criteria for the current model.
	 * @param ASolrCriteria $criteria the query criteria
	 */
	public function setSolrCriteria(ASolrCriteria $criteria)
	{
		$this->_solrCriteria=$criteria;
	}

	/**
	 * Returns the default named scope that should be implicitly applied to all queries for this model.
	 * The default implementation simply returns an empty array. You may override this method
	 * if the model needs to be queried with some default criteria
	 * @return array the query criteria. This will be used as the parameter to the constructor
	 * of {@link ASolrCriteria}.
	 */
	public function defaultScope()
	{
		return array();
	}

	/**
	 * Resets all scopes and criterias applied including default scope.
	 *
	 * @return ASolrDocument
	 */
	public function resetScope()
	{
		$this->_solrCriteria = new ASolrCriteria();
		return $this;
	}

	/**
	 * Gets the solr input document
	 * @return SolrInputDocument the solr document
	 */
	public function getInputDocument() {
		if ($this->_inputDocument !== null) {
			return $this->_inputDocument;
		}
		$this->_inputDocument = new SolrInputDocument();
		foreach($this->attributeNames() as $attribute) {
			if ($this->{$attribute} !== null) {
				if (is_array($this->{$attribute})) {
					foreach($this->{$attribute} as $value) {
						$this->_inputDocument->addField($attribute,$value);
					}
				}
				else {
					$this->_inputDocument->addField($attribute,$this->prepareAttribute($attribute));
				}
			}
		}
		return $this->_inputDocument;
	}
	/**
	 * Gets an array of mapping rules for this solr index.
	 * Child classes should override this method to specify solr types for individual fields.
	 * E.g.
	 * <pre>
	 * array(
	 *  "fieldName" => "string",
	 *  "someDateField" => "date",
	 * );
	 * </pre>
	 * @return array an array of mapping rules for this solr index
	 */
	public function attributeMapping() {
		return array();
	}
	/**
	 * Prepares an attribute to be inputted into solr
	 * @param string $attribute the name of the attribute
	 * @return mixed the prepared value to insert into solr
	 */
	protected function prepareAttribute($attribute) {
		$mapping = $this->attributeMapping();
		if (!isset($mapping[$attribute])) {
			return $this->{$attribute};
		}
		if (is_array($mapping[$attribute])) {
			if (!isset($mapping[$attribute]['type'])) {
				return $this->{$attribute};
			}
			$type = $mapping[$attribute]['type'];
		}
		else {
			$type = $mapping[$attribute];
		}
		switch (strtolower($type)) {
			case "date":
				$date = $this->{$attribute};
				if (substr($date,0,4) == "0000") {
					$date = 0;
				}
				$date = $this->createSolrDateTime(is_int($date) ? $date : strtotime($date));
				return $date;
				break;
			default:
				return $this->{$attribute};
		}
	}
	/**
	 * Formats a unix timestamp in solr date format
	 * @param integer $time the unix timestamp
	 * @return string the solr formatted timestamp
	 */
	public function createSolrDateTime($time) {
		$defaultTZ = date_default_timezone_get();
		date_default_timezone_set("UTC");
		$date = date("Y-m-d\TH:i:s\Z",$time);
		date_default_timezone_set($defaultTZ);
		return $date;
	}

	/**
	 * Parse a solr date time into unix time
	 * @param string $time the time from solr
	 * @return integer the unix time
	 */
	public function parseSolrDateTime($time) {
		if (substr($time,0,7) == "2-11-30") // this is a broken date / time in solr
			return null;
		return strtotime($time);
	}


	/**
	 * Returns a property value or an event handler list by property or event name.
	 * This method overrides the parent implementation by returning
	 * a key value if the key exists in the collection.
	 * @param string $name the property name or the event name
	 * @return mixed the property value or the event handler list
	 * @throws CException if the property/event is not defined.
	 */
	public function __get($name)
	{
		if($this->_attributes->contains($name))
			return $this->_attributes->itemAt($name);
		else
			return parent::__get($name);
	}

	/**
	 * Sets value of a component property.
	 * This method overrides the parent implementation by adding a new key value
	 * to the collection.
	 * @param string $name the property name or event name
	 * @param mixed $value the property value or event handler
	 * @throws CException If the property is not defined or read-only.
	 */
	public function __set($name,$value)
	{
		$setter = "set".$name;
		if (method_exists($this,$setter)) {
			return $this->{$setter}($value);
		}
		$this->add($name,$value);
	}

	/**
	 * Checks if a property value is null.
	 * This method overrides the parent implementation by checking
	 * if the key exists in the collection and contains a non-null value.
	 * @param string $name the property name or the event name
	 * @return boolean whether the property value is null
	 * @since 1.0.1
	 */
	public function __isset($name)
	{
		if($this->_attributes->contains($name))
			return $this->_attributes->itemAt($name)!==null;
		else
			return parent::__isset($name);
	}

	/**
	 * Sets a component property to be null.
	 * This method overrides the parent implementation by clearing
	 * the specified key value.
	 * @param string $name the property name or the event name
	 * @since 1.0.1
	 */
	public function __unset($name)
	{
		$this->_attributes->remove($name);
	}
	/**
	 * Initializes this model.
	 * This method is invoked in the constructor right after {@link scenario} is set.
	 * You may override this method to provide code that is needed to initialize the model (e.g. setting
	 * initial property values.)
	 * @since 1.0.8
	 */
	public function init()
	{
		$this->_attributes = new CAttributeCollection();
		$this->_attributes->caseSensitive = true;
	}

	/**
	 * Creates a solr document instance.
	 * This method is called by {@link populateRecord} and {@link populateRecords}.
	 * You may override this method if the instance being created
	 * depends the attributes that are to be populated to the record.
	 * For example, by creating a record based on the value of a column,
	 * you may implement the so-called single-table inheritance mapping.
	 * @param array $attributes list of attribute values for the solr document.
	 * @return ASolrDocument the active record
	 */
	protected function instantiate($attributes)
	{
		$class=get_class($this);
		$model=new $class(null);
		return $model;
	}

	/**
	 * Creates a solr document with the given attributes.
	 * This method is internally used by the find methods.
	 * @param array $attributes attribute values (column name=>column value)
	 * @param boolean $callAfterFind whether to call {@link afterFind} after the record is populated.
	 * @return ASolrDocument the newly created solr document. The class of the object is the same as the model class.
	 * Null is returned if the input data is false.
	 */
	public function populateRecord($attributes,$callAfterFind=true)
	{
		if($attributes!==false)
		{
			$record=$this->instantiate($attributes);
			$record->setScenario('update');
			$record->init();
			$mapping = $this->attributeMapping();
			foreach($attributes as $name=>$value) {
				if (isset($mapping[$name])) {
					switch ($mapping[$name]) {
						case "date";
							$value = $this->parseSolrDateTime($value);
							if ($value !== null)
								$value = date("Y-m-d H:i:s", $value);
							break;
					}
				}
				$record->$name=$value;
			}
			$record->_pk=$record->getPrimaryKey();
			$record->attachBehaviors($record->behaviors());
			if($callAfterFind) {
				$record->afterFind();
			}
			return $record;
		}
		else {
			return null;
		}
	}

	/**
	 * Creates a list of solr documents based on the input data.
	 * This method is internally used by the find methods.
	 * @param array $data list of attribute values for the solr documents.
	 * @param boolean $callAfterFind whether to call {@link afterFind} after each record is populated.
	 * @param string $index the name of the attribute whose value will be used as indexes of the query result array.
	 * If null, it means the array will be indexed by zero-based integers.
	 * @return array list of solr documents.
	 */
	public function populateRecords($data,$callAfterFind=true,$index=null)
	{
		$records=array();
		foreach($data as $attributes)
		{
			if(($record=$this->populateRecord($attributes,$callAfterFind))!==null)
			{
				if($index===null)
					$records[]=$record;
				else
					$records[$record->$index]=$record;
			}
		}
		return $records;
	}

	/**
	 * Returns the name of the primary key of the associated solr index.
	 * Child classes should override this if the primary key is anything other than "id"
	 * @return mixed the primary key attribute name(s). Defaults to "id"
	 */
	public function primaryKey()
	{
		return "id";
	}

	/**
	 * Returns the primary key value.
	 * @return mixed the primary key value. An array (column name=>column value) is returned if the primary key is composite.
	 * If primary key is not defined, null will be returned.
	 */
	public function getPrimaryKey()
	{
		$attribute = $this->primaryKey();
		if (!is_array($attribute)) {
			return $this->{$attribute};
		}
		$pk = array();
		foreach($attribute as $field) {
			$pk[$field] = $this->{$attribute};
		}
		return $pk;
	}

	/**
	 * Sets the primary key value.
	 * After calling this method, the old primary key value can be obtained from {@link oldPrimaryKey}.
	 * @param mixed $value the new primary key value. If the primary key is composite, the new value
	 * should be provided as an array (column name=>column value).
	 */
	public function setPrimaryKey($value)
	{
		$this->_pk=$this->getPrimaryKey();
		$attribute = $this->primaryKey();
		if (!is_array($attribute)) {
			return $this->{$attribute} = $value;
		}
		foreach($value as $attribute => $attributeValue) {
			$this->{$attribute} = $attributeValue;
		}
		return $value;
	}
	/**
	 * Returns the old primary key value.
	 * This refers to the primary key value that is populated into the record
	 * after executing a find method (e.g. find(), findAll()).
	 * The value remains unchanged even if the primary key attribute is manually assigned with a different value.
	 * @return mixed the old primary key value. An array (column name=>column value) is returned if the primary key is composite.
	 * If primary key is not defined, null will be returned.
	 * @since 1.1.0
	 */
	public function getOldPrimaryKey()
	{
		return $this->_pk;
	}

	/**
	 * Sets the old primary key value.
	 * @param mixed $value the old primary key value.
	 * @since 1.1.3
	 */
	public function setOldPrimaryKey($value)
	{
		$this->_pk=$value;
	}
	/**
	 * Adds an item into the map.
	 * If the item is an array, it will be converted to an instance of ASolrCriteria
	 * @param mixed $key key
	 * @param mixed $value value
	 */
	public function add($key,$value) {
		if (property_exists($this,$key)) {
			$this->{$key} = $value;
		}
		else {
			$this->_attributes->add($key,$value);
		}
	}

	/**
	 * Returns an array containing the attributes
	 * @return array the list of items in array
	 */
	public function toArray() {
		$data = array();
		foreach($this->attributeNames() as $attribute) {
			$data[$attribute] = $this->{$attribute};
		}
		return $data;
	}

	/**
	 * Returns an array containing the attributes
	 * @see toArray()
	 */
	public function __toArray() {
		return $this->toArray();
	}

	/**
	 * Sets the attribute values in a massive way.
	 * @param array $values attribute values (name=>value) to be set.
	 * @param boolean $safeOnly whether the assignments should only be done to the safe attributes.
	 * A safe attribute is one that is associated with a validation rule in the current {@link scenario}.
	 * @see getSafeAttributeNames
	 * @see attributeNames
	 */
	public function setAttributes($values,$safeOnly=true)
	{
		if ($safeOnly) {
			return parent::setAttributes($values,true);
		}
		foreach($values as $attribute => $value) {
			$this->{$attribute} = $value;
		}
	}

	/**
	 * Saves the solr document
	 * @param boolean $runValidation whether to run validation or not, defaults to true
	 * @return boolean whether the save succeeded or not
	 */
	public function save($runValidation = true) {
		if ($runValidation && !$this->validate()) {
			return false;
		}
		if (!$this->beforeSave()) {
			return false;
		}
		$connection = $this->getSolrConnection();
		if (!$connection->index($this)) {
			return false;
		}
		$this->afterSave();
		return true;
	}
	/**
	 * Deletes the solr document
	 * @return boolean whether the delete succeeded or not
	 */
	public function delete() {
		if (!$this->beforeDelete() || !$this->getSolrConnection()->delete($this)){
			return false;
		}
		$this->afterDelete();
		return true;
	}

	/**
	 * Returns the number of documents matching specified criteria.
	 * @param ASolrCriteria $criteria solr query criteria.
	 * @return integer the number of rows found
	 */
	public function count(ASolrCriteria $criteria = null)
	{
		Yii::trace(get_class($this).'.count()','packages.solr.ASolrDocument');
		if ($criteria === null) {
			$criteria = new ASolrCriteria();
		}
		$this->applyScopes($criteria);
		return $this->getSolrConnection()->count($criteria);
	}

	/**
	 * Finds a single solr document according to the specified criteria.
	 * @param ASolrCriteria $criteria solr query criteria.
	 * @return ASolrDocument the document found. Null if none is found.
	 */
	public function find(ASolrCriteria $criteria = null)
	{
		Yii::trace(get_class($this).'.find()','packages.solr.ASolrDocument');
		if ($criteria === null) {
			$criteria = new ASolrCriteria();
		}
		return $this->query($criteria);
	}

	/**
	 * Finds multiple solr documents according to the specified criteria.
	 * @param ASolrCriteria $criteria solr query criteria.
	 * @return ASolrDocument[] the documents found.
	 */
	public function findAll(ASolrCriteria $criteria = null)
	{
		Yii::trace(get_class($this).'.findAll()','packages.solr.ASolrDocument');
		if ($criteria === null) {
			$criteria = new ASolrCriteria();
		}
		return $this->query($criteria,true);
	}

	/**
	 * Finds a single solr document with the specified primary key.
	 * @param mixed $pk primary key value(s). Use array for multiple primary keys. For composite key, each key value must be an array (column name=>column value).
	 * @param ASolrCriteria $criteria solr query criteria.
	 * @return ASolrDocument the document found. Null if none is found.
	 */
	public function findByPk($pk,ASolrCriteria $criteria = null)
	{
		Yii::trace(get_class($this).'.findByPk()','packages.solr.ASolrDocument');
		if ($criteria === null) {
			$criteria = new ASolrCriteria();
		}
		if (is_array($pk)) {
			$query = array();
			foreach($pk as $attribute => $value) {
				$query[] = $attribute.":".$value;
			}
			$criteria->setQuery(implode(" AND ",$query));
		}
		else {
			$criteria->setQuery($this->primaryKey().":".$pk);
		}
		return $this->query($criteria);
	}

	/**
	 * Finds all solr documents with the specified primary keys.
	 * @param mixed $pk primary key value(s). Use array for multiple primary keys. For composite key, each key value must be an array (column name=>column value).
	 * @param ASolrCriteria $criteria solr query criteria.
	 * @return ASolrDocument the document found. Null if none is found.
	 */
	public function findAllByPk($pk,ASolrCriteria $criteria = null)
	{
		Yii::trace(get_class($this).'.findByPk()','packages.solr.ASolrDocument');
		if ($criteria === null) {
			$criteria = new ASolrCriteria();
		}
		$query = array();
		foreach($pk as $key) {
			if (is_array($key)) {
				$item = array();
				foreach($key as $attribute => $value) {
					$item[] = $attribute.":".$value;
				}
				$query[] = implode(" AND ",$item);
			}
			else {
				$query[] = $this->primaryKey().":".$key;
			}
		}
		$criteria->setQuery(implode(" OR ",$query));
		return $this->query($criteria,true);
	}

	/**
	 * Finds a single solr document that has the specified attribute values.
	 * @param array $attributes list of attribute values (indexed by attribute names) that the solr documents should match.
	 * @param ASolrCriteria $criteria The solr search criteria
	 * @return ASolrDocument the document found. Null if none is found.
	 */
	public function findByAttributes($attributes,$criteria = null)
	{
		Yii::trace(get_class($this).'.findByAttributes()','packages.solr.ASolrDocument');
		if ($criteria === null) {
			$criteria = new ASolrCriteria();
		}
		$query = array();
		foreach($attributes as $attribute => $value) {
			$query[] = $attribute.":".$value;
		}
		$criteria->setQuery(implode(" AND ",$query));
		return $this->query($criteria);
	}

	/**
	 * Finds multiple solr document that have the specified attribute values.
	 * @param array $attributes list of attribute values (indexed by attribute names) that the solr documents should match.
	 * @param ASolrCriteria $criteria The solr search criteria
	 * @return ASolrDocument[] the documents found.
	 */
	public function findAllByAttributes($attributes,$criteria = null)
	{
		Yii::trace(get_class($this).'.findAllByAttributes()','packages.solr.ASolrDocument');
		if ($criteria === null) {
			$criteria = new ASolrCriteria();
		}
		$query = array();
		foreach($attributes as $attribute => $value) {
			$query[] = $attribute.":".$value;
		}
		$criteria->setQuery(implode(" AND ",$query));
		return $this->query($criteria,true);
	}

	/**
	 * Performs the actual solr query and populates the solr document objects with the query result.
	 * This method is mainly internally used by other solr document query methods.
	 * @param ASolrCriteria $criteria the query criteria
	 * @param boolean $all whether to return all data
	 * @return mixed the solr document objects populated with the query result
	 */
	protected function query($criteria,$all=false)
	{
		$this->beforeFind();
		$this->applyScopes($criteria);

		if(!$all) {
			$criteria->setLimit(1);
		}

		$response = $this->getSolrConnection()->search($criteria,$this);
		if ($all) {
			return $response->getResults()->toArray();
		}
		else {
			$results = $response->getResults()->toArray();
			return array_shift($results);
		}

	}
	/**
	 * Returns the declaration of named scopes.
	 * A named scope represents a query criteria that can be chained together with
	 * other named scopes and applied to a query. This method should be overridden
	 * by child classes to declare named scopes for the particular solr document classes.
	 * @return array the scope definition
	 */
	public function scopes()
	{
		return array();
	}

	/**
	 * Applies the query scopes to the given criteria.
	 * This method merges {@link solrCriteria} with the given criteria parameter.
	 * It then resets {@link solrCriteria} to be null.
	 * @param ASolrCriteria $criteria the query criteria. This parameter may be modified by merging {@link solrCriteria}.
	 */
	public function applyScopes(&$criteria)
	{
		if(!empty($criteria->scopes))
		{
			$scs=$this->scopes();
			$c=$this->getSolrCriteria();
			foreach((array)$criteria->scopes as $k=>$v)
			{
				if(is_integer($k))
				{
					if(is_string($v))
					{
						if(isset($scs[$v]))
						{
							$c->mergeWith($scs[$v],true);
							continue;
						}
						$scope=$v;
						$params=array();
					}
					else if(is_array($v))
					{
						$scope=key($v);
						$params=current($v);
					}
				}
				else if(is_string($k))
				{
					$scope=$k;
					$params=$v;
				}

				call_user_func_array(array($this,$scope),(array)$params);
			}
		}

		if(isset($c) || ($c=$this->getSolrCriteria(false))!==null)
		{
			$c->mergeWith($criteria);
			$criteria=$c;
			$this->_solrCriteria=null;
		}
	}


	/**
	 * Returns whether there is an element at the specified offset.
	 * This method is required by the interface ArrayAccess.
	 * @param mixed $offset the offset to check on
	 * @return boolean
	 */
	public function offsetExists($offset)
	{
		return $this->_attributes->contains($offset);
	}

	/**
	 * Returns the element at the specified offset.
	 * This method is required by the interface ArrayAccess.
	 * @param integer $offset the offset to retrieve element.
	 * @return mixed the element at the offset, null if no element is found at the offset
	 */
	public function offsetGet($offset)
	{
		return $this->_attributes->itemAt($offset);
	}

	/**
	 * Sets the element at the specified offset.
	 * This method is required by the interface ArrayAccess.
	 * @param integer $offset the offset to set element
	 * @param mixed $item the element value
	 */
	public function offsetSet($offset,$item)
	{
		$this->_attributes->add($offset,$item);
	}

	/**
	 * Unsets the element at the specified offset.
	 * This method is required by the interface ArrayAccess.
	 * @param mixed $offset the offset to unset element
	 */
	public function offsetUnset($offset)
	{
		$this->_attributes->remove($offset);
	}

	/**
	 * Returns if the current document is new.
	 * @return boolean whether the record is new and should be inserted when calling {@link save}.
	 * This property is automatically set in constructor and {@link populateRecord}.
	 * Defaults to false, but it will be set to true if the instance is created using
	 * the new operator.
	 */
	public function getIsNewRecord()
	{
		return $this->_new;
	}

	/**
	 * Sets if the record is new.
	 * @param boolean $value whether the document is new and should be inserted when calling {@link save}.
	 * @see getIsNewRecord
	 */
	public function setIsNewRecord($value)
	{
		$this->_new=$value;
	}

	/**
	 * This event is raised before the document is saved.
	 * By setting {@link CModelEvent::isValid} to be false, the normal {@link save()} process will be stopped.
	 * @param CModelEvent $event the event parameter
	 */
	public function onBeforeSave($event)
	{
		$this->raiseEvent('onBeforeSave',$event);
	}

	/**
	 * This event is raised after the document is saved.
	 * @param CEvent $event the event parameter
	 */
	public function onAfterSave($event)
	{
		$this->raiseEvent('onAfterSave',$event);
	}

	/**
	 * This event is raised before the document is deleted.
	 * By setting {@link CModelEvent::isValid} to be false, the normal {@link delete()} process will be stopped.
	 * @param CModelEvent $event the event parameter
	 */
	public function onBeforeDelete($event)
	{
		$this->raiseEvent('onBeforeDelete',$event);
	}

	/**
	 * This event is raised after the record is deleted.
	 * @param CEvent $event the event parameter
	 */
	public function onAfterDelete($event)
	{
		$this->raiseEvent('onAfterDelete',$event);
	}

	/**
	 * This event is raised before a find call.
	 * In this event, the {@link CModelEvent::criteria} property contains the query criteria
	 * passed as parameters to those find methods. If you want to access
	 * the query criteria specified in scopes, please use {@link getDbCriteria()}.
	 * You can modify either criteria to customize them based on needs.
	 * @param CModelEvent $event the event parameter
	 * @see beforeFind
	 */
	public function onBeforeFind($event)
	{
		$this->raiseEvent('onBeforeFind',$event);
	}

	/**
	 * This event is raised after the document is instantiated by a find method.
	 * @param CEvent $event the event parameter
	 */
	public function onAfterFind($event)
	{
		$this->raiseEvent('onAfterFind',$event);
	}

	/**
	 * This method is invoked before saving a document (after validation, if any).
	 * The default implementation raises the {@link onBeforeSave} event.
	 * You may override this method to do any preparation work for document saving.
	 * Use {@link isNewRecord} to determine whether the saving is
	 * for inserting or updating document.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @return boolean whether the saving should be executed. Defaults to true.
	 */
	protected function beforeSave()
	{
		if($this->hasEventHandler('onBeforeSave'))
		{
			$event=new CModelEvent($this);
			$this->onBeforeSave($event);
			return $event->isValid;
		}
		else
			return true;
	}

	/**
	 * This method is invoked after saving a document successfully.
	 * The default implementation raises the {@link onAfterSave} event.
	 * You may override this method to do postprocessing after document saving.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 */
	protected function afterSave()
	{
		if($this->hasEventHandler('onAfterSave'))
			$this->onAfterSave(new CEvent($this));
	}

	/**
	 * This method is invoked before deleting a document.
	 * The default implementation raises the {@link onBeforeDelete} event.
	 * You may override this method to do any preparation work for document deletion.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @return boolean whether the document should be deleted. Defaults to true.
	 */
	protected function beforeDelete()
	{
		if($this->hasEventHandler('onBeforeDelete'))
		{
			$event=new CModelEvent($this);
			$this->onBeforeDelete($event);
			return $event->isValid;
		}
		else
			return true;
	}

	/**
	 * This method is invoked after deleting a document.
	 * The default implementation raises the {@link onAfterDelete} event.
	 * You may override this method to do postprocessing after the document is deleted.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 */
	protected function afterDelete()
	{
		if($this->hasEventHandler('onAfterDelete'))
			$this->onAfterDelete(new CEvent($this));
	}

	/**
	 * This method is invoked before a find call.
	 * The find calls include {@link find}, {@link findAll}, {@link findByPk},
	 * {@link findAllByPk}, {@link findByAttributes} and {@link findAllByAttributes}.
	 * The default implementation raises the {@link onBeforeFind} event.
	 * If you override this method, make sure you call the parent implementation
	 * so that the event is raised properly.
	 */
	protected function beforeFind()
	{
		if($this->hasEventHandler('onBeforeFind'))
		{
			$event=new CModelEvent($this);
			// for backward compatibility
			$event->criteria=func_num_args()>0 ? func_get_arg(0) : null;
			$this->onBeforeFind($event);
		}
	}

	/**
	 * This method is invoked after each record is instantiated by a find method.
	 * The default implementation raises the {@link onAfterFind} event.
	 * You may override this method to do postprocessing after each newly found record is instantiated.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 */
	protected function afterFind()
	{
		if($this->hasEventHandler('onAfterFind'))
			$this->onAfterFind(new CEvent($this));
	}

	/**
	 * Calls {@link beforeFind}.
	 * This method is internally used.
	 * @since 1.0.11
	 */
	public function beforeFindInternal()
	{
		$this->beforeFind();
	}

	/**
	 * Calls {@link afterFind}.
	 * This method is internally used.
	 * @since 1.0.3
	 */
	public function afterFindInternal()
	{
		$this->afterFind();
	}

	/**
	 * Sets the solr query response.
	 * @param ASolrQueryResponse $solrResponse the response from solr that this model belongs to
	 */
	public function setSolrResponse($solrResponse)
	{
		$this->_solrResponse = $solrResponse;
	}

	/**
	 * Gets the response from solr that this model belongs to
	 * @return ASolrQueryResponse the solr query response
	 */
	public function getSolrResponse()
	{
		return $this->_solrResponse;
	}

	/**
	 * Sets the number of milliseconds to commit inserts and updates within.
	 * @param integer $commitWithin the number of milliseconds to commit within
	 */
	public function setCommitWithin($commitWithin)
	{
		$this->_commitWithin = $commitWithin;
	}

	/**
	 * Gets the number of milliseconds to commit inserts and updates within.
	 * @return integer the number of milliseconds to commit within
	 */
	public function getCommitWithin()
	{
		return $this->_commitWithin;
	}

	/**
	 * Sets the highlights for this record
	 * @param array $highlights the highlights, attribute => highlights
	 */
	public function setHighlights($highlights)
	{
		$this->_highlights = $highlights;
	}

	/**
	 * Gets the highlights if highlighting is enabled
	 * @param string|null $attribute the attribute to get highlights for, if null all attributes will be returned
	 * @return array|boolean the highlighted results
	 */
	public function getHighlights($attribute = null)
	{
		if ($attribute === null)
			return $this->_highlights;
		if (!isset($this->_highlights[$attribute]))
			return false;
		return $this->_highlights[$attribute];
	}
}

