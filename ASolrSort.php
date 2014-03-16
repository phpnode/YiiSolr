<?php
/**
 * This class is an extended version of CSort.
 *
 * It enables you to use Solr parameters in your sort attributes like this:
 *
 *	array(
 *		// String and array attributes work as usual:
 *		'title',
 *		'latest'=>array(
 *			'asc'=>'created_at asc',
 *			'desc'=>'created_at desc',
 *		),
 *
 *		// Solr parameters can be defined like this:
 *		'keyword'=>array(
 *			'asc'=>array(
 *				// Each of these parameters is set through setParam() in Solr.
 *				// Make sure you also have a 'sort' parameter here.
 *				'sortingQ'	=>'{!edismax qf="title^8.0"} '.$keyword,
 *				'sort'		=>'product(query($sortingQ),scale(stars,1,5)) asc',
 *			),
 *			'desc'=>array(
 *				'sort'		=>'product(query($sortingQ),scale(stars,1,5)) desc',
 *				'sortingQ'	=>'{!edismax qf="title^8.0"} '.$keyword,
 *			),
 *		),
 *	)
 * Represents information relevant to solr sorting
 * @author Charles Pick / PeoplePerHour.com
 * @package packages.solr
 */
class ASolrSort extends CSort
{
	/**
	 * @var mixed default order as string or array of solr params
	 */
	public $defaultOrder;

	public function applyOrder($criteria)
	{
		$directions=$this->getDirections();
		if(empty($directions))
		{
			if(!empty($this->defaultOrder))
				$criteria->order=$this->defaultOrder;
		}
		elseif(isset($directions['sort']))
		{
			// Special case: getDirections returned defaultOrder and it was an array of Solr params
			foreach($directions as $name=>$value)
				$criteria->setParam($name,$value);
		}
		else
		{
			$orders=array();
			foreach($directions as $attribute=>$descending)
			{
				$definition=$this->resolveAttribute($attribute);
				if(is_array($definition))
				{
					$dir=$descending ? 'desc' : 'asc';
					if(isset($definition[$dir]) && is_array($definition[$dir]))
						foreach($definition[$dir] as $name=>$value)
						{
							$criteria->setParam($name,$value);
						}
					else
						$orders[]=isset($definition[$dir]) ? $definition[$dir] : $attribute.($descending ? ' DESC':'');
				}
				else if($definition!==false)
				{
					$attribute=$definition;
					$orders[]=$descending?$attribute.' DESC':$attribute;
				}
			}
			if($orders!==array())
				$criteria->order=implode(', ',$orders);
		}
	}

	/**
	 * Solr does not support ORDER BY
	 */
	public function getOrderBy($criteria = NULL)
	{
		throw new CException('Solr sorting does not support ORDER BY');
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
