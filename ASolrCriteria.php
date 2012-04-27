<?php
/**
 * Represents an solr query criteria
 * @package packages.solr
 * @author Charles Pick / PeoplePerHour.com
 */
class ASolrCriteria extends SolrQuery {

	/**
	 * Constructor
	 * @param array|null $data the parameters to initialize the criteria with
	 */
	public function __construct($data = null) {
		parent::__construct();
		if ($data !== null) {
			foreach($data as $key => $value) {
				$this->{$key} = $value;
			}
		}
	}
	/**
	 * Return the scores along with the results
	 * @return ASolrCriteria $this with the score field added
	 */
	public function withScores() {
		$this->addField("score");
		return $this;
	}

	/**
	 * Returns a property value based on its name.
	 * Do not call this method. This is a PHP magic method that we override
	 * to allow using the following syntax to read a property
	 * <pre>
	 * $value=$component->propertyName;
	 * </pre>
	 * @param string $name the property name
	 * @return mixed the property value
	 * @throws CException if the property is not defined
	 * @see __set
	 */
	public function __get($name) {
		$getter='get'.$name;
		if(method_exists($this,$getter)) {
			return $this->$getter();
		}
		throw new CException(Yii::t('yii','Property "{class}.{property}" is not defined.',
			array('{class}'=>get_class($this), '{property}'=>$name)));
	}

	/**
	 * Sets value of a component property.
	 * Do not call this method. This is a PHP magic method that we override
	 * to allow using the following syntax to set a property
	 * <pre>
	 * $this->propertyName=$value;
	 * </pre>
	 * @param string $name the property name
	 * @param mixed $value the property value
	 * @return mixed
	 * @throws CException if the property is not defined or the property is read only.
	 * @see __get
	 */
	public function __set($name,$value)
	{
		$setter='set'.$name;
		if(method_exists($this,$setter)) {
			return $this->$setter($value);
		}
		if(method_exists($this,'get'.$name))
			throw new CException(Yii::t('yii','Property "{class}.{property}" is read only.',
				array('{class}'=>get_class($this), '{property}'=>$name)));
		else
			throw new CException(Yii::t('yii','Property "{class}.{property}" is not defined.',
				array('{class}'=>get_class($this), '{property}'=>$name)));
	}

	/**
	 * Checks if a property value is null.
	 * Do not call this method. This is a PHP magic method that we override
	 * to allow using isset() to detect if a component property is set or not.
	 * @param string $name the property name
	 * @return boolean
	 */
	public function __isset($name)
	{
		$getter='get'.$name;
		return method_exists($this,$getter);
	}

	/**
	 * Sets a component property to be null.
	 * Do not call this method. This is a PHP magic method that we override
	 * to allow using unset() to set a component property to be null.
	 * @param string $name the property name or the event name
	 * @throws CException if the property is read only.
	 * @return mixed
	 * @since 1.0.1
	 */
	public function __unset($name)
	{
		$setter='set'.$name;
		if(method_exists($this,$setter))
			$this->$setter(null);
		else if(method_exists($this,'get'.$name))
			throw new CException(Yii::t('yii','Property "{class}.{property}" is read only.',
				array('{class}'=>get_class($this), '{property}'=>$name)));
	}

	/**
	 * Gets the number of items to return.
	 * This method is required for compatibility with pagination
	 * @return integer the number of items to return
	 */
	public function getLimit() {
		return $this->getRows();
	}
	/**
	 * Sets the number of items to return.
	 * This method is required for compatibility with pagination
	 * @param integer $value the number of items to return
	 */
	public function setLimit($value) {
		$this->setRows($value);
	}

	/**
	 * Gets the starting offset when returning results
	 * This method is required for compatibility with pagination
	 * @return integer the starting offset
	 */
	public function getOffset() {
		return $this->getStart();
	}
	/**
	 * Sets the starting offset when returning results
	 * This method is required for compatibility with pagination
	 * @param integer $value the starting offset
	 */
	public function setOffset($value) {
		return $this->setStart($value);
	}

    /**
     * Gets the sort order
     * @return string the sort order
     */
	public function getOrder() {
		return $this->getParam("sort");
	}

    /**
     * Sets the sort order
     * @param string $value the new sort order
     */
	public function setOrder($value) {
		$this->setParam("sort",$value);
	}

	/**
	 * Appends a condition to the existing {@link query}.
	 * The new condition and the existing condition will be concatenated via the specified operator
	 * which defaults to 'AND'.
	 * The new condition can also be an array. In this case, all elements in the array
	 * will be concatenated together via the operator.
	 * This method handles the case when the existing condition is empty.
	 * After calling this method, the {@link query} property will be modified.
	 * @param mixed $condition the new condition. It can be either a string or an array of strings.
	 * @param string $operator the operator to join different conditions. Defaults to 'AND'.
	 * @return ASolrCriteria the criteria object itself
	 */
	public function addCondition($condition,$operator='AND')
	{
		if(is_array($condition))
		{
			if($condition===array())
				return $this;
			$condition='('.implode(') '.$operator.' (',$condition).')';
		}
		$this->addFilterQuery($condition);
		return $this;
	}

	/**
	 * Adds a between condition to the {@link query}
	 *
	 * The new between condition and the existing condition will be concatenated via
	 * the specified operator which defaults to 'AND'.
	 * If one or both values are empty then the condition is not added to the existing condition.
	 * This method handles the case when the existing condition is empty.
	 * After calling this method, the {@link condition} property will be modified.
	 * @param string $column the name of the column to search between.
	 * @param string $valueStart the beginning value to start the between search.
	 * @param string $valueEnd the ending value to end the between search.
	 * Defaults to 'AND'.
	 * @return ASolrCriteria the criteria object itself
	 */
	public function addBetweenCondition($column,$valueStart,$valueEnd)
	{
		if($valueStart==='' || $valueEnd==='')
			return $this;

		$this->addFilterQuery($column.":[".$valueStart." TO ".$valueEnd."]");
		return $this;
	}

	/**
	 * Appends an IN condition to the existing {@link query}.
	 * The IN condition and the existing condition will be concatenated via the specified operator
	 * which defaults to 'AND'.
	 *
	 * @param string $column the column name
	 * @param array $values list of values that the column value should be in
	 * @param string $operator the operator used to concatenate the new condition with the existing one.
	 * Defaults to 'AND'.
	 * @return ASolrCriteria the criteria object itself
	 */
	public function addInCondition($column,$values,$operator='AND')
	{
		if(($n=count($values))<1)
			return $this;
		if($n===1)
		{
			$value=reset($values);

			$condition=$column.':'.$value;
		}
		else
		{
			$params=array();
			foreach($values as $value)
			{
				$params[]=$value;
			}
			$condition=$column.':('.implode(' ',$params).')';
		}
		return $this->addCondition($condition,$operator);
	}

	/**
	 * Appends an NOT IN condition to the existing {@link query}.
	 * The NOT IN condition and the existing condition will be concatenated via the specified operator
	 * which defaults to 'AND'.
	 * @param string $column the column name (or a valid SQL expression)
	 * @param array $values list of values that the column value should not be in
	 * @param string $operator the operator used to concatenate the new condition with the existing one.
	 * Defaults to 'AND'.
	 * @return ASolrCriteria the criteria object itself
	 */
	public function addNotInCondition($column,$values,$operator='AND')
	{
		if(($n=count($values))<1)
			return $this;
		if($n===1)
		{
			$value=reset($values);

			$condition=$column.':!'.$value;
		}
		else
		{
			$params=array();
			foreach($values as $value)
			{
				$params[]="!".$value;
			}
			$condition=$column.':('.implode(' AND ',$params).')';
		}
		return $this->addCondition($condition,$operator);
	}


	/**
	 * Merges this criteria with another
	 * @param ASolrCriteria $criteria the criteria to merge with
	 * @return ASolrCriteria the merged criteria
	 */
	public function mergeWith(ASolrCriteria $criteria) {
		foreach($criteria->getParams() as $name => $value) {
			if ($value === null) {
				continue;
			}
			if ($name == "q" && (($query = $this->getQuery()) != "")) {

				$value = "(".$query.") AND (".$criteria->getQuery().")";
			}
			if (!is_array($value)) {
				   $this->setParam($name,$value);
			}
			else {
				foreach($value as $key => $val) {
					$this->addParam($name,$val);
				}
			}
		}
		return $this;
	}

	/**
	 * Escape a string and remove solr special characters
	 * @param string $string the string to escape
	 * @return string the escaped string
	 */
	public function escape($string) {
        return SolrUtils::escapeQueryChars($string);
	}
}