<?php

/**
 * Represents information relevant to solr sorting
 * @author Charles Pick / PeoplePerHour.com
 * @package packages.solr
 */
class ASolrSort extends CSort
{
	/**
	 * @return string the order-by columns represented by this sort object.
	 * This can be put in the ORDER BY clause of a SQL statement.
	 */
	public function getOrderBy()
	{
		$directions=$this->getDirections();
		if(empty($directions))
			return is_string($this->defaultOrder) ? $this->defaultOrder : '';
		else
		{
			$orders=array();
			foreach($directions as $attribute=>$descending)
			{
				$definition=$this->resolveAttribute($attribute);
				if(is_array($definition))
				{
					if($descending)
						$orders[]=isset($definition['desc']) ? $definition['desc'] : $attribute.' DESC';
					else
						$orders[]=isset($definition['asc']) ? $definition['asc'] : $attribute;
				}
				else if($definition!==false)
				{
					$attribute=$definition;
					$orders[]=$descending?$attribute.' DESC':$attribute;
				}
			}
			return implode(', ',$orders);
		}
	}

	/**
	 * Returns the real definition of an attribute given its name.
	 *
	 * The resolution is based on {@link attributes} and {@link CActiveRecord::attributeNames}.
	 * <ul>
	 * <li>When {@link attributes} is an empty array, if the name refers to an attribute of {@link modelClass},
	 * then the name is returned back.</li>
	 * <li>When {@link attributes} is not empty, if the name refers to an attribute declared in {@link attributes},
	 * then the corresponding virtual attribute definition is returned. Starting from version 1.1.3, if {@link attributes}
	 * contains a star ('*') element, the name will also be used to match against all model attributes.</li>
	 * <li>In all other cases, false is returned, meaning the name does not refer to a valid attribute.</li>
	 * </ul>
	 * @param string $attribute the attribute name that the user requests to sort on
	 * @return mixed the attribute name or the virtual attribute definition. False if the attribute cannot be sorted.
	 */
	public function resolveAttribute($attribute)
	{
		if($this->attributes!==array())
			$attributes=$this->attributes;
		else if($this->modelClass!==null)
			$attributes=ASolrDocument::model($this->modelClass)->attributeNames();
		else
			return false;
		foreach($attributes as $name=>$definition)
		{
			if(is_string($name))
			{
				if($name===$attribute)
					return $definition;
			}
			else if($definition==='*')
			{
				if($this->modelClass!==null && ASolrDocument::model($this->modelClass)->hasAttribute($attribute))
					return $attribute;
			}
			else if($definition===$attribute)
				return $attribute;
		}
		return false;
	}
}