#### Introduction

A Yii package that provides a wrapper for the pecl solr library (http://www.php.net/manual/en/book.solr.php) to make fast searching with Yii really easy.
The wrapper allows the use of familiar Yii constructs such as models, data providers etc with a solr index.

#### Installation

First install the pecl solr extension
<pre>
pecl install solr
</pre>
If this command fails, you may find it easier to compile the pecl solr extension from source.


If you do not already have a packages directory and alias set up, first create a directory called "packages" in your application folder.
Then add an alias to your main config, e.g.
<pre>
"aliases" => array(
	"packages" => dirname(__DIR__)."/packages/",
),
...
</pre>

Now extract the files to packages/solr

#### Running unit tests

The unit tests depend on sqlite to provide the test data, please ensure the php PDO sqlite module is installed before continuing.

The unit tests also depend on the example index that ships with solr.
Go to your solr installation directory and in the "example" folder run
<pre>
java -jar start.jar
</pre>
This should start the solr server running on port 8983 by default. If you're using a different port, please configure it in the packages/solr/tests/common.php file.

Now go to your application tests directory, usually protected/tests and run the following command:

<pre>
phpunit --verbose ../packages/solr/tests
</pre>

This will run the unit tests, if all went well they should all pass, otherwise please check your configuration.

#### Configuring your solr connection

Before we can use solr in our application, we must configure a connection to use.
In the application config, add the following
<pre>
"components" => array(
	...
	"solr" => array(
	 	"class" => "packages.solr.ASolrConnection",
	 	"clientOptions" => array(
	 		"hostname" => "localhost",
	 		"port" => 8983,
	 	),
	 ),
),
</pre>

This will configure an application component called "solr".
If you're dealing with more than one index, define a new solr connection for each one, giving each a unique name.


#### Indexing a document with solr


To add a document to solr we use the {@link ASolrDocument} class.
Example:
<pre>
$doc = new ASolrDocument;
$doc->id = 123;
$doc->name = "test document";
$doc->save(); // adds the document to solr
</pre>
Remember - Your changes won't appear in solr until a commit occurs.
If you need your data to appear immediately, use the following syntax:
<pre>
$doc->getSolrConnection()->commit();
</pre>
If you need to deal with multiple solr indexes, it's often best to define a model for
each index you're dealing with. To do this we extend ASolrDocument in the same way that we would extend CActiveRecord when defining a model
For example:
<pre>
class Job extends ASolrDocument {
	/**
	 * Required for all ASolrDocument sub classes
	 * @see ASolrDocument::model()
	 */
	public static function model($className = __CLASS__) {
		return parent::model($className);
	}
	/**
	 * @return ASolrConnection the solr connection to use for this model
	 */
	public function getSolrConnection() {
		return Yii::app()->yourCustomSolrConnection;
	}
}
</pre>

#### Searching solr

To find documents in solr, we use the following methods:
<ul>
	<li>{@link ASolrDocument::find()}</li>
	<li>{@link ASolrDocument::findAll()}</li>
	<li>{@link ASolrDocument::findByAttributes()}</li>
	<li>{@link ASolrDocument::findAllByAttributes()}</li>
	<li>{@link ASolrDocument::findByPk()}</li>
	<li>{@link ASolrDocument::findAllByPk()}</li>
</ul>

The most useful of these methods are find() and findAll(). Both these methods take a criteria parameter, this criteria parameter should be an instance of {@link ASolrCriteria}.
Example: Find all documents with the name "test"
<pre>
$criteria = new ASolrCriteria;
$criteria->query = "name:test"; // lucene query syntax
$docs = ASolrDocument::model()->findAll($criteria);
</pre>
Alternative method:
<pre>
$docs = ASolrDocument::model()->findAllByAttributes(array("name" => "test"));
</pre>


Example: Find a job with the unique id of 123
<pre>
$job = Job::model()->findByPk(123);
</pre>
Example: Find the total number of jobs in the index
<pre>
$criteria = new ASolrCriteria;
$criteria->query = "*"; // match everything
$total = Job::model()->count($criteria); // the total number of jobs in the index
</pre>

#### Using data providers

Often we need to use a data provider to retrieve paginated lists of results.
Example:
<pre>
$dataProvider = new ASolrDataProvider(Job::model());
$dataProvider->criteria->query = "*";
foreach($dataProvider->getData() as $job) {
	echo $job->title."\n";
}
</pre>


#### Removing items from the index
To remove an item from the index, use the following syntax:
<pre>
$job = Job::model()->findByPk(234);
$job->delete();
</pre>