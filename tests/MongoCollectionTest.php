<?php
require_once 'PHPUnit/Framework.php';

require_once 'Mongo.php';

/**
 * Test class for Mongo.
 * Generated by PHPUnit on 2009-04-10 at 13:30:28.
 */
class MongoCollectionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var    Mongo
     * @access protected
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        $db = new MongoDB($this->sharedFixture, "phpunit");
        $this->object = $db->selectCollection('c');
        $this->object->drop();
    }

    public function test__toString() {
      $this->assertEquals((string)$this->object, 'phpunit.c');
    }

    public function testDrop() {
      $ns = $this->object->db->selectCollection('system.namespaces');

      $this->object->insert(array('x' => 1));
      $this->object->ensureIndex('x');

      $c = $ns->findOne(array('name' => 'phpunit.c'));
      $this->assertNotNull($c);

      $this->object->drop();

      $c = $ns->findOne(array('name' => 'phpunit.c'));
      $this->assertEquals($c, null);
    }

    public function testValidate() {
      $v = $this->object->validate();
      $this->assertEquals($v['ok'], 0);
      $this->assertEquals($v['errmsg'], 'ns not found');

      $this->object->insert(array('a' => 'foo'));
      $v = $this->object->validate();
      $this->assertEquals($v['ok'], 1);
      $this->assertEquals($v['ns'], 'phpunit.c');
      $this->assertNotNull($v['result']);
    }

    public function testInsert() {
      $a = array("n" => NULL,
                 "l" => 234234124,
                 "d" => 23.23451452,
                 "b" => true,
                 "a" => array("foo"=>"bar",
                              "n" => NULL,
                              "x" => new MongoId("49b6d9fb17330414a0c63102")),
                 "d2" => new MongoDate(1271079861),
                 "regex" => new MongoRegex("/xtz/g"),
                 "_id" => new MongoId("49b6d9fb17330414a0c63101"),
                 "string" => "string");
      
      $this->assertTrue($this->object->insert($a));
      $obj = $this->object->findOne();

      $this->assertEquals($obj['n'], null);
      $this->assertEquals($obj['l'], 234234124);
      $this->assertEquals($obj['d'], 23.23451452);
      $this->assertEquals($obj['b'], true);
      $this->assertEquals($obj['a']['foo'], 'bar');
      $this->assertEquals($obj['a']['n'], null);
      $this->assertNotNull($obj['a']['x']);
      $this->assertEquals($obj['d2']->sec, 1271079);
      $this->assertEquals($obj['d2']->usec, 861000);
      $this->assertEquals($obj['regex']->regex, 'xtz');
      $this->assertEquals($obj['regex']->flags, 'g');
      $this->assertNotNull($obj['_id']);
      $this->assertEquals($obj['string'], 'string');

      $this->assertFalse($this->object->insert(null));
      $this->assertFalse($this->object->insert(array()));
      $this->assertFalse($this->object->insert(1345));
      $this->assertFalse($this->object->insert(true));
      $this->assertTrue($this->object->insert(array(1,2,3,4,5)));
    }

    public function testBatchInsert() {
      $this->assertFalse($this->object->batchInsert(null));
      $this->assertFalse($this->object->batchInsert(array()));
      $this->assertFalse($this->object->batchInsert(array(1,2,3)));
      $this->assertTrue($this->object->batchInsert(array('z'=>array('foo'=>'bar'))));

      $a = array( array( "x" => "y"), array( "x"=> "z"), array("x"=>"foo"));
      $this->object->batchInsert($a);
      $this->assertEquals(4, $this->object->count());

      $cursor = $this->object->find()->sort(array("x" => -1));
      $x = $cursor->getNext();
      $this->assertEquals('bar', $x['foo']);
      $x = $cursor->getNext();
      $this->assertEquals('z', $x['x']);
      $x = $cursor->getNext();
      $this->assertEquals('y', $x['x']);
      $x = $cursor->getNext();
      $this->assertEquals('foo', $x['x']);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFindException1() {
      $c = $this->object->find(null);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFindException2() {
      $c = $this->object->find(3);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFindException3() {
      $c = $this->object->find(true);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFindException4() {
      $c = $this->object->find(array(), null);
    }

    public function testFind() {
      for ($i=0;$i<50;$i++) {
        $this->object->insert(array('x' => $i));
      }

      $c = $this->object->find();
      $this->assertEquals($c->count(), 50);
      $c = $this->object->find(array());
      $this->assertEquals($c->count(), 50);

      $this->object->insert(array("foo" => "bar",
                                  "a" => "b",
                                  "b" => "c"));

      $c = $this->object->find(array('foo' => 'bar'), array('a'=>1, 'b'=>1));

      $this->assertTrue($c instanceof MongoCursor);
      $obj = $c->getNext();
      $this->assertEquals('b', $obj['a']);
      $this->assertEquals('c', $obj['b']);
      $this->assertEquals(null, $obj['foo']);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFindOneException1() {
      $this->object->findOne(true);
    }

    public function testFindOne() {
      $this->assertEquals(null, $this->object->findOne());
      $this->assertEquals(null, $this->object->findOne(array()));

      for ($i=0;$i<3;$i++) {
        $this->object->insert(array('x' => $i));
      }

      $obj = $this->object->findOne();
      $this->assertNotNull($obj);
      $this->assertEquals($obj['x'], 0);

      $obj = $this->object->findOne(array('x' => 1));
      $this->assertNotNull($obj);
      $this->assertEquals(1, $obj['x']);
    }

    public function testUpdate() {
      $old = array("foo"=>"bar", "x"=>"y");
      $new = array("foo"=>"baz");
      
      $this->object->update(array("foo"=>"bar"), $old, true);
      $obj = $this->object->findOne();
      $this->assertEquals($obj['foo'], 'bar');      
      $this->assertEquals($obj['x'], 'y');      

      $this->object->update($old, $new);
      $obj = $this->object->findOne();
      $this->assertEquals($obj['foo'], 'baz');      
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRemoveException1() {
      $this->object->remove(0);
    }

    public function testRemove() {
      for($i=0;$i<15;$i++) {
        $this->object->insert(array("i"=>$i));
      }
      
      $this->assertEquals($this->object->count(), 15);
      $this->object->remove(array(), true);
      $this->assertEquals($this->object->count(), 14);

      $this->object->remove();
      
      $this->assertEquals($this->object->count(), 0);
    }

    public function testEnsureIndex() {
      $this->object->ensureIndex('foo');

      $idx = $this->object->db->selectCollection('system.indexes');
      $index = $idx->findOne(array('ns' => 'phpunit.c'));

      $this->assertNotNull($index);
      $this->assertEquals($index['key']['foo'], 1);
      $this->assertEquals($index['name'], 'foo_1');

      $this->object->ensureIndex("");
      $index = $idx->findOne(array('name' => '_1'));
      $this->assertNotNull($index);
      $this->assertEquals($index['key'][''], 1);
      $this->assertEquals($index['ns'], 'phpunit.c');

      // get rid of indexes
      $this->object->drop();

      $this->object->ensureIndex(null);
      $index = $idx->findOne(array('name' => '_1'));
      $this->assertNotNull($index);
      $this->assertEquals($index['key'][''], 1);
      $this->assertEquals($index['ns'], 'phpunit.c');

      $this->object->ensureIndex(6);
      $index = $idx->findOne(array('name' => '6_1'));
      $this->assertNotNull($index);
      $this->assertEquals($index['key']['6'], 1);
      $this->assertEquals($index['ns'], 'phpunit.c');

      $this->object->ensureIndex(array('bar' => -1));
      $index = $idx->findOne(array('name' => 'bar_-1'));
      $this->assertNotNull($index);
      $this->assertEquals($index['key']['bar'], -1);
      $this->assertEquals($index['ns'], 'phpunit.c');
    }

    public function testDeleteIndex() {
      $idx = $this->object->db->selectCollection('system.indexes');

      $this->object->ensureIndex('foo');
      $this->object->ensureIndex(array('foo' => -1));

      $num = $idx->find(array('ns' => 'phpunit.c'))->count();
      $this->assertEquals($num, 2);

      $this->object->deleteIndex(null);
      $num = $idx->find(array('ns' => 'phpunit.c'))->count();
      $this->assertEquals($num, 2);
    }

    /**
     * @todo Implement testResetError().
     */
    public function testDeleteIndexes() {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @todo Implement testForceError().
     */
    public function testGetIndexInfo() {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @todo Implement testMasterInfo().
     */
    public function testCount() {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @todo Implement testClose().
     */
    public function testSave() {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @todo Implement testClose().
     */
    public function testGetDBRef() {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }

    /**
     * @todo Implement testClose().
     */
    public function testCreateDBRef() {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }
}
?>
