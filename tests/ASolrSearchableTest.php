<?php
include_once("common.php"); // include the functionality common to all solr tests
/**
 * Tests for the {@link ASolrSearchable} behavior
 * @author Charles Pick / PeoplePerHour.com
 * @package packages.solr.tests
 */
class ASolrSearchableTest extends CTestCase {
	/**
	 * Tests the basic behavior functionality
	 */
	public function testBasics() {
		$model = ExampleSolrActiveRecord::model()->find();
		$behavior = $model->asa("ASolrSearchable");
		$attributeNames = array("id","name","author","popularity","description");
		$this->assertEquals(array_combine($attributeNames,$attributeNames),$behavior->getAttributes());
		$solrDocument = $behavior->getSolrDocument();
		$this->assertTrue($solrDocument instanceof IASolrDocument);
		foreach($attributeNames as $attribute) {
			$this->assertTrue(isset($solrDocument->{$attribute}));
			$this->assertEquals($model->{$attribute},$solrDocument->{$attribute});
		}
		$this->assertTrue($model->index());
		$this->assertFalse($behavior->getIsModified());
		$model->name = "Test User ".uniqid();
		$this->assertTrue($behavior->getIsModified());
		$this->assertTrue($model->save());
		$this->getConnection()->commit();
		$criteria = new ASolrCriteria();
		$criteria->query = "*:*";
		$fromSolr = $model->findBySolr($criteria);
		$this->assertTrue($fromSolr instanceof ExampleSolrActiveRecord);
	}

	public function testGetIsModified() {
		$model = ExampleSolrActiveRecord::model()->find();
		$behavior = $model->asa("ASolrSearchable");
		$behavior->setAttributes(array("id","name","popularity","description")); // specify the attribute names we're interested in saving to solr
		$this->assertFalse($model->getIsModified());
		$model->author = "an author";
		$this->assertFalse($model->getIsModified()); // a field we don't care about
		$model->name = "hello";
		$this->assertTrue($model->getIsModified());
	}
	/**
	 * Tests the find() and findAll() methods
	 */
	public function testFind() {
		$model = ExampleSolrActiveRecord::model()->find();

		$behavior = $model->asa("ASolrSearchable"); /* @var ASolrSearchable $behavior */
		$model->index();
		$behavior->getSolrDocument()->getSolrConnection()->commit();
		$criteria = new ASolrCriteria();
		$criteria->query = "id:".$model->id;
		$fromSolr = $behavior->findBySolr($criteria);
		$this->assertTrue($fromSolr instanceof ExampleSolrActiveRecord);
		foreach($fromSolr->attributeNames() as $attribute) {
			$this->assertEquals($model->{$attribute}, $fromSolr->{$attribute});
		}

		$criteria = new ASolrCriteria;
		$criteria->setLimit(10);
		$results = $model->findAllBySolr($criteria);
		$this->assertEquals(10, count($results));
		foreach($results as $result) {
			$this->assertTrue($result instanceof ExampleSolrActiveRecord);
		}
	}

	/**
	 * Tests the delete events
	 */
	public function testDelete() {
		$model = ExampleSolrActiveRecord::model()->find();

		$behavior = $model->asa("ASolrSearchable"); /* @var ASolrSearchable $behavior */
		$model->index();
		$connection = $behavior->getSolrDocument()->getSolrConnection() /* @var ASolrConnection $connection */;
		$connection->commit();
		$criteria = new ASolrCriteria();
		$criteria->query = "id:".$model->id;
		$this->assertTrue(is_object($connection->search($criteria)));
		$this->assertEquals(1,$connection->count($criteria));
		$model->delete();
		$connection->commit();

		$this->assertEquals(0,$connection->count($criteria));

	}


	/**
	 * Tests populating active record objects directly from solr
	 */
	public function testPopulateFromSolr() {
		$model = ExampleSolrActiveRecord::model()->find();
		$model->getMetaData()->addRelation("testRelation",array(
															 CActiveRecord::HAS_ONE,
															 "ExampleSolrActiveRecord",
															 "id"
														  ));
		$behavior = $model->asa("ASolrSearchable"); /* @var ASolrSearchable $behavior */
		$behavior->setAttributes(CMap::mergeArray($behavior->getAttributes(),array("testRelation.name")));
		$criteria = new ASolrCriteria();
		$criteria->query = "*:*";
		$document = $behavior->getSolrDocument()->find($criteria);
		$document->testRelation__name = "test relation name";
		$fromSolr = $behavior->populateFromSolr($document, false);
		$this->assertEquals($document->name, $fromSolr->name);
		$this->assertTrue(is_object($fromSolr->testRelation));
		$this->assertEquals("test relation name", $fromSolr->testRelation->name);
	}
	/**
	 * Adds the required data to the test database
	 */
	public function setUp() {
		$this->getConnection();
		foreach($this->fixtureData() as $row) {
			$record = new ExampleSolrActiveRecord();
			foreach($row as $attribute => $value) {
				$record->{$attribute} = $value;
			}
			$this->assertTrue($record->save());
		}
	}
	/**
	 * Deletes the data from the test database
	 */
	public function tearDown() {
		$sql = "DELETE FROM solrexample WHERE 1=1";
		ExampleSolrActiveRecord::model()->getDbConnection()->createCommand($sql)->execute();
	}

	/**
	 * Gets the solr connection
	 * @return ASolrConnection the connection to use for this test
	 */
	protected function getConnection() {
		static $connection;
		if ($connection === null) {
			$connection = new ASolrConnection();
			$connection->clientOptions->hostname = SOLR_HOSTNAME;
			$connection->clientOptions->port = SOLR_PORT;
			$connection->clientOptions->path = SOLR_PATH;
			ASolrDocument::$solr = $connection;
		}
		return $connection;
	}


	/**
	 * Generates 50 arrays of attributes for fixtures
	 * @return array the fixture data
	 */
	protected function fixtureData() {
		$rows = array();
		for($i = 0; $i < 50; $i++) {
			$rows[] = array(
				"name" => "Test Item ".$i,
				"popularity" => $i,
				"author" => "Test Author ".$i,
				"description" => str_repeat("lorem ipsum dolor est ",rand(3,20)),

			);
		}
		return $rows;
	}
}

/**
 * An example active record that can be populated from a database or from solr
 * @author Charles Pick / PeoplePerHour.com
 * @package packages.solr.tests
 *
 * @propert integer $id the id field (pk)
 * @property string $name the name field
 * @property string $author the author field
 * @property integer $popularity the popularity field
 * @property string $description the description field
 */
class ExampleSolrActiveRecord extends CActiveRecord {
	/**
	 * Holds the database connection to use with this model
	 * @var CDbConnection
	 */
	protected $_db;

	public function behaviors() {
		return array(
			"ASolrSearchable" => array(
				"class" => "packages.solr.ASolrSearchable",
			)
		);
	}
	/**
	 * Gets the database connection to use with this model.
	 * We use an sqlite connection for the test data.
	 * @return CDbConnection the database connection
	 */
	public function getDbConnection() {
		if ($this->_db === null) {
			$dsn = 'sqlite2:'.__DIR__.'/test.db';
			$this->_db = new CDbConnection($dsn);
		}
		return $this->_db;
	}
	/**
	 * Gets the table name to use for this model
	 * @return string the table name
	 */
	public function tableName() {
		return "solrexample";
	}
	/**
	 * Gets the static model instance
	 * @param string $className the class to instantiate
	 * @return ExampleSolrActiveRecord the static model instance
	 */
	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}