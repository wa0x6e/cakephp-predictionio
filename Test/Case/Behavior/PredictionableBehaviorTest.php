<?php
/**
 * PredictionableIO behavior test
 *
 * PHP versions 5
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author        Wan Qi Chen <kami@kamisama.me>
 * @copyright     Copyright 2013, Wan Qi Chen <kami@kamisama.me>
 * @link          http://cakeresque.kamisama.me
 * @package       PredictionIO
 * @subpackage    PredictionIO.Test.Case.Behavior
 * @since         0.1.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 **/

App::uses('AppModel', 'Model');

/**
 * PredictionableBehaviorTest class
 *
 * @package     PredictionIO
 * @subpackage  PredictionIO.Test.Case.Behavior
 */
class PredictionableBehaviorTest extends CakeTestCase {

	public $fixtures = array('User', 'Article', 'Apple', 'Guild');

	public function setUp() {
		parent::setUp();

		$this->PredictionIOClient = $this->getMock('\PredictionIO\PredictionIOClient', array('identify', 'getCommand', 'execute'));

		$this->User = ClassRegistry::init('User');
		$this->User->Behaviors->load('Containable');
		$this->User->Behaviors->attach('PredictionIO.Predictionable');
		$this->User->setupClient($this->PredictionIOClient);

		$this->Article = ClassRegistry::init('Article');
		$this->Article->Behaviors->load('Containable');
		$this->Article->Behaviors->attach('PredictionIO.Predictionable');
		$this->Article->setupClient($this->PredictionIOClient);
	}

	public function testDefaultConfig() {
		$this->assertEquals('your-key', Configure::read('predictionIO.appkey'));
		$this->assertEquals('localhost:8000', Configure::read('predictionIO.host'));
		$this->assertEquals('User', Configure::read('predictionIO.userModel'));
		$this->assertEquals('', Configure::read('predictionIO.engine'));

		$this->assertCount(4, Configure::read('predictionIO'));
	}

	public function testPredictionIOLibraryIsLoaded() {
		$this->assertTrue(class_exists('\PredictionIO\PredictionIOClient'));
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testCreateUserWillCreateAUserRecord() {
		$user = array('id' => 45, 'user' => 'jenny', 'password' => 'password');

		$expected = array('create_user', array('pio_uid' => 'User:' . $user['id']));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->User->save(array('User' => $user));
		$this->assertEquals($user['id'], $r['User']['id']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testCreateArticleWillCreateAnItemRecord() {
		$Article = array('id' => 46, 'author_id' => 25, 'title' => 'My First Article');

		$expected = array('create_item', array('pio_iid' => 'Article:' . $Article['id'], 'pio_itypes' => 'Article'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Article->save(array('Article' => $Article));
		$this->assertEquals($Article['id'], $r['Article']['id']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testCreateUserWithCustomFields() {
		$this->User->Behaviors->load('PredictionIO.Predictionable', array('fields' => array('user')));
		$this->User->setupClient($this->PredictionIOClient);

		$user = array('id' => 45, 'user' => 'jenny', 'password' => 'password');

		$expected = array('create_user', array('pio_uid' => 'User:' . $user['id'], 'user' => $user['user']));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->User->save(array('User' => $user));
		$this->assertEquals($user['id'], $r['User']['id']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testCreateArticleWithCustomFields() {
		$this->Article->Behaviors->load('PredictionIO.Predictionable', array('fields' => array('author_id', 'title')));
		$this->Article->setupClient($this->PredictionIOClient);

		$Article = array('id' => 46, 'author_id' => 25, 'title' => 'My First Article', 'body' => 'body?');

		$expected = array('create_item', array('pio_iid' => 'Article:' . $Article['id'], 'author_id' => $Article['author_id'], 'title' => $Article['title'], 'pio_itypes' => 'Article'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Article->save(array('Article' => $Article));
		$this->assertEquals($Article['id'], $r['Article']['id']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testCreateArticleWithMultipleTypes() {
		$this->Article->Behaviors->load('PredictionIO.Predictionable', array('types' => array('Movie', 'Entertainment')));
		$this->Article->setupClient($this->PredictionIOClient);

		$Article = array('id' => 46, 'author_id' => 25, 'title' => 'My First Article', 'body' => 'body?');

		$expected = array('create_item', array('pio_iid' => 'Article:' . $Article['id'], 'pio_itypes' => 'Movie,Entertainment'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Article->save(array('Article' => $Article));
		$this->assertEquals($Article['id'], $r['Article']['id']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testUseArticleModelAsUserModelSetFromModel() {
		$this->Article->Behaviors->load('PredictionIO.Predictionable', array('userModel' => 'Article'));
		$this->Article->setupClient($this->PredictionIOClient);

		$Article = array('id' => 46, 'author_id' => 25, 'title' => 'My First Article', 'body' => 'body?');

		$expected = array('create_user', array('pio_uid' => 'Article:' . $Article['id']));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Article->save(array('Article' => $Article));
		$this->assertEquals($Article['id'], $r['Article']['id']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testUseArticleModelAsUserModelSetFromBootstrap() {
		Configure::write('predictionIO.userModel', 'Article');
		$this->Article->Behaviors->unload('PredictionIO.Predictionable');
		$this->Article->Behaviors->load('PredictionIO.Predictionable');
		$this->Article->setupClient($this->PredictionIOClient);

		$Article = array('id' => 46, 'author_id' => 25, 'title' => 'My First Article', 'body' => 'body?');

		$expected = array('create_user', array('pio_uid' => 'Article:' . $Article['id']));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Article->save(array('Article' => $Article));
		$this->assertEquals($Article['id'], $r['Article']['id']);
	}

/**
 * @covers PredictionableBehavior::afterDelete
 */
	public function testDeleteUserWillDeleteAUserRecord() {
		$expected = array('delete_user', array('pio_uid' => 'User:1'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->assertTrue($this->User->delete(1));
	}

/**
 * @covers PredictionableBehavior::afterDelete
 */
	public function testDeleteArticleWillDeleteAnItemRecord() {
		$expected = array('delete_item', array('pio_iid' => 'Article:1'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->assertTrue($this->Article->delete(1));
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testUpdateUserWithNoCustomFields() {
		// Asserting that the user really exists before updating
		$this->assertEquals(1, $this->User->find('count', array('conditions' => array('User.id' => 1))));

		$this->PredictionIOClient->expects($this->never())->method('getCommand');
		$this->PredictionIOClient->expects($this->never())->method('execute');

		$user = array('id' => 1, 'user' => 'jenny');
		$r = $this->User->save(array('User' => $user));
		$this->assertEquals($user['id'], $r['User']['id']);
		$this->assertEquals($user['user'], $r['User']['user']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testUpdateArticleWithNoCustomFields() {
		// Asserting that the Article really exists before updating
		$this->assertEquals(1, $this->Article->find('count', array('conditions' => array('Article.id' => 1))));

		$this->PredictionIOClient->expects($this->never())->method('getCommand');
		$this->PredictionIOClient->expects($this->never())->method('execute');

		$Article = array('id' => 1, 'title' => 'my new title');
		$r = $this->Article->save(array('Article' => $Article));
		$this->assertEquals($Article['id'], $r['Article']['id']);
		$this->assertEquals($Article['title'], $r['Article']['title']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testUpdateUserWithCustomFields() {
		$this->User->Behaviors->load('PredictionIO.Predictionable', array('fields' => array('user')));
		$this->User->setupClient($this->PredictionIOClient);

		// Asserting that the user really exists before updating
		$this->assertEquals(1, $this->User->find('count', array('conditions' => array('User.id' => 1))));

		$user = array('id' => 1, 'user' => 'jenny');

		$expected = array('create_user', array('pio_uid' => 'User:' . $user['id'], 'user' => $user['user']));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->User->save(array('User' => $user));
		$this->assertEquals($user['id'], $r['User']['id']);
		$this->assertEquals($user['user'], $r['User']['user']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testUpdateArticleWithCustomFields() {
		$this->Article->Behaviors->load('PredictionIO.Predictionable', array('fields' => array('title')));
		$this->Article->setupClient($this->PredictionIOClient);

		// Asserting that the user really exists before updating
		$this->assertEquals(1, $this->Article->find('count', array('conditions' => array('Article.id' => 1))));

		$Article = array('id' => 1, 'title' => 'my new title');

		$expected = array('create_item', array('pio_iid' => 'Article:' . $Article['id'], 'title' => $Article['title'], 'pio_itypes' => 'Article'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Article->save(array('Article' => $Article));
		$this->assertEquals($Article['id'], $r['Article']['id']);
		$this->assertEquals($Article['title'], $r['Article']['title']);
	}

/**
 * @covers PredictionableBehavior::recordAction
 */
	public function testRecordAction() {
		$this->User->id = 1;

		$expected = array('record_action_on_item', array('pio_action' => 'like', 'pio_iid' => 'Article:52'));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo('User:1'));
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->assertTrue($this->User->recordAction($expected[1]['pio_action'], array('id' => 52, 'model' => 'Article')));
	}

/**
 * @covers PredictionableBehavior::recordAction
 */
	public function testRecordActionWithAModelAsTarget() {
		$this->User->id = 1;

		$this->Article->id = 52;

		$expected = array('record_action_on_item', array('pio_action' => 'like', 'pio_iid' => 'Article:52'));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo('User:1'));
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->assertTrue($this->User->recordAction($expected[1]['pio_action'], $this->Article));
	}

/**
 * Test that triggering a recordAction witn an invalid target throw an exception
 *
 * @covers PredictionableBehavior::recordAction
 * @expectedException InvalidItemException
 * @expectedExceptionMessage The target item is not valid
 */
	public function testRecordActionWithInvalidTargetArgument() {
		$this->User->id = 1;
		$this->Article->id = 50;

		$this->User->recordAction('like', 1);
	}

/**
 * Test that triggering a recordAction witn an invalid target throw an exception
 *
 * @covers PredictionableBehavior::recordAction
 * @expectedException InvalidItemException
 * @expectedExceptionMessage The Article is not valid
 */
	public function testRecordActionWithNotInitializedTarget() {
		$this->User->id = 1;
		$this->Article->create();

		$this->User->recordAction('like', $this->Article);
	}

/**
 * Test that triggering a recordAction on model other that the User Model
 * will throw an exception
 *
 * @covers PredictionableBehavior::recordAction
 * @expectedException InvalidActionOnModelException
 * @expectedExceptionMessage You can not record an action on the Article model
 */
	public function testRecordActionOnNonUserModel() {
		$this->Article->id = 1;

		$this->Article->recordAction('like', $this->Article);
	}

/**
 * Test that triggering a recordAction on a User model with no primary key
 * will throw an exception
 *
 * @covers PredictionableBehavior::recordAction
 * @expectedException InvalidUserException
 * @expectedExceptionMessage The User is not valid
 */
	public function testRecordActionOnUserWithNoPrimaryKey() {
		$this->User->recordAction('like', $this->Article);
	}

/**
 * Test saving custom fields along the recordAction
 *
 * @covers PredictionableBehavior::recordAction
 */
	public function testRecordActionWithCustomFields() {
		$this->User->id = 1;

		$expected = array('record_action_on_item', array('pio_action' => 'like', 'pio_iid' => 'Article:52', 'rating' => 6, 'level' => 'medium'));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo('User:1'));
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->User->recordAction($expected[1]['pio_action'], array('id' => 52, 'model' => 'Article'), array('rating' => 6, 'level' => 'medium'));
	}

/**
 * @covers PredictionableBehavior::getRecommendation
 */
	public function testGetRecommendation() {
		$this->User->id = 1;

		$expected = array('itemrec_get_top_n', array('pio_engine' => '', 'pio_n' => 10));
		$expectedRecommendation = array('piids' => array('Article:1', 'Article:2'));

		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo('User:1'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue($expectedRecommendation));

		$recommendation = array(
			array('id' => '1', 'model' => 'Article'),
			array('id' => '2', 'model' => 'Article'),
		);
		$this->assertEquals($recommendation, $this->User->getRecommendation());
	}

/**
 * @covers PredictionableBehavior::getRecommendation
 */
	public function testGetRecommendationWithIdSetFromArguments() {
		$this->User->id = 1;

		$expected = array('itemrec_get_top_n', array('pio_engine' => '', 'pio_n' => 10));
		$expectedRecommendation = array('piids' => array('Article:1', 'Article:2'));

		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo('User:52'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue($expectedRecommendation));

		$recommendation = array(
			array('id' => '1', 'model' => 'Article'),
			array('id' => '2', 'model' => 'Article'),
		);
		$this->assertEquals($recommendation, $this->User->getRecommendation(array('id' => 52)));
	}

/**
 * Test getRecommendation() with optional parameters,
 * set when calling getRecommendation()
 *
 * @covers PredictionableBehavior::getRecommendation
 */
	public function testGetRecommendationWithOptionalParameters() {
		$this->User->id = 1;

		$expected = array('itemrec_get_top_n', array('pio_engine' => 'engine1', 'pio_n' => 12, 'pio_attributes' => 'title,author', 'pio_itypes' => 'Article,User'));
		$expectedRecommendation = array(
			'piids' => array('Article:1', 'Article:2'),
			'title' => array('first Article', 'second Article'),
			'author' => array('john', 'arthur')
		);

		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo('User:1'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue($expectedRecommendation));

		$recommentation = array(
			array('id' => '1', 'model' => 'Article', 'title' => 'first Article', 'author' => 'john'),
			array('id' => '2', 'model' => 'Article', 'title' => 'second Article', 'author' => 'arthur')
		);

		$this->assertEquals($recommentation, $this->User->getRecommendation(array(
			'engine' => $expected[1]['pio_engine'],
			'count' => $expected[1]['pio_n'],
			'attributes' => array('title', 'author'),
			'invalid' => 'no-value',
			'itypes' => array($this->Article, $this->User)
		)));
	}

/**
 * Test getRecommendation() with custom default parameters,
 * set when loading the behavior
 *
 * @covers PredictionableBehavior::getRecommendation
 */
	public function testGetRecommendationWithCustomDefaultParameters() {
		$this->User->Behaviors->load('PredictionIO.Predictionable', array('engine' => 'engine4', 'count' => 52));
		$this->User->setupClient($this->PredictionIOClient);
		$this->User->id = 1;

		$expected = array('itemrec_get_top_n', array('pio_engine' => 'engine4', 'pio_n' => 52));
		$expectedRecommendation = array(
			'piids' => array('Article:1', 'Article:2'),
			'title' => array('first Article', 'second Article'),
			'author' => array('john', 'arthur')
		);

		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo('User:1'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue($expectedRecommendation));

		$this->User->getRecommendation();
	}

/**
 * Test that getRecommendation() can be only called from a User Model
 *
 * @covers PredictionableBehavior::getRecommendation
 * @expectedException InvalidUserException
 * @expectedExceptionMessage The User is not valid
 */
	public function testGetRecommendationThrownAnExceptionWhenCalledOnANonUserModel() {
		$this->Article->getRecommendation();
	}

/**
 * Test that getRecommendation() will throw an Exception when the current User is not initialized
 *
 * @covers PredictionableBehavior::getRecommendation
 * @expectedException InvalidUserException
 * @expectedExceptionMessage The User is not valid
 */
	public function testGetRecommendationThrownAnExceptionOnUserWithNoPrimaryKey() {
		$this->User->getRecommendation();
	}

/**
 * @covers PredictionableBehavior::getSimilar
 */
	public function testGetSimilar() {
		$this->Article->id = 1;

		$expected = array('itemsin_get_top_n', array('pio_engine' => '', 'pio_n' => 10, 'pio_iid' => 'Article:1'));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->Article->getSimilar();
	}

/**
 * @covers PredictionableBehavior::getSimilar
 */
	public function testGetSimilarWithTargetIdSetFromArguments() {
		$this->Article->id = 1;

		$expected = array('itemsin_get_top_n', array('pio_engine' => '', 'pio_n' => 10, 'pio_iid' => 'Article:52'));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->Article->getSimilar(array('id' => 52));
	}

/**
 * @covers PredictionableBehavior::getSimilar
 */
	public function testGetSimilarWithOptionalParameters() {
		$this->Article->id = 1;

		$expected = array('itemsin_get_top_n', array('pio_engine' => '', 'pio_n' => 10, 'pio_iid' => 'Article:1', 'pio_attributes' => 'a,b'));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->Article->getSimilar(array('engine' => $expected[1]['pio_engine'], 'attributes' => array('a,b'), 'invalid' => 'hi'));
	}

/**
 * Test that getSimilar() can be only called from a non User model
 *
 * @covers PredictionableBehavior::getSimilar
 * @expectedException InvalidActionOnModelException
 * @expectedExceptionMessage You can not get similar items on the User model
 */
	public function testGetSimilarThrownAnExceptionWhenCalledOnAUserModel() {
		$this->User->getSimilar();
	}

/**
 * Test that getSimilar() will throw an Exception when the current Item is not initialized
 *
 * @covers PredictionableBehavior::getSimilar
 * @expectedException InvalidItemException
 * @expectedExceptionMessage You have to specify the targeted item ID
 */
	public function testGetSimilarThrownAnExceptionOnItemWithNoPrimaryKey() {
		$this->Article->getSimilar();
	}

/**
 * @covers PredictionableBehavior::disablePrediction
 */
	public function testDisablePredictionOnUser() {
		$this->User->id = 1;

		$expected = array('create_user', array('pio_inactive' => 'true', 'pio_uid' => 'User:1'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');
		$this->User->disablePrediction();
	}

/**
 * @covers PredictionableBehavior::enablePrediction
 */
	public function testEnablePredictionOnUser() {
		$this->User->id = 1;

		$expected = array('create_user', array('pio_inactive' => 'false', 'pio_uid' => 'User:1'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');
		$this->User->enablePrediction();
	}

/**
 * @covers PredictionableBehavior::disablePrediction
 */
	public function testDisablePredictionOnItem() {
		$this->Article->id = 1;

		$expected = array('create_item', array('pio_inactive' => 'true', 'pio_iid' => 'Article:1'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');
		$this->Article->disablePrediction();
	}

/**
 * @covers PredictionableBehavior::enablePrediction
 */
	public function testEnablePredictionOnItem() {
		$this->Article->id = 1;

		$expected = array('create_item', array('pio_inactive' => 'false', 'pio_iid' => 'Article:1'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');
		$this->Article->enablePrediction();
	}

/**
 * @covers PredictionableBehavior::findRecommended
 */
	public function testFindRecommendedOnAUserModel() {
		$this->User->id = 1;
		$this->User->bindModel(array(
			'hasMany' => array('Article')
		));

		$expectedRecommendation = array(
			'piids' => array('Article:1', 'Article:3')
		);

		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue($expectedRecommendation));
		$results = $this->User->findRecommended('all', array('limit' => 1));

		$this->assertCount(1, $results);
		$this->assertCount(1, $results[0]);
		$this->assertArrayHasKey('Article', $results[0]);
	}

/**
 * @covers PredictionableBehavior::findRecommended
 */
	public function testFindRecommendedOnANonUserModel() {
		$this->User->id = 1;
		$this->User->bindModel(array(
			'hasMany' => array('Article')
		));

		$this->User->Article->bindModel(array(
			'belongsTo' => array('User')
		));

		$expectedRecommendation = array(
			'piids' => array('Article:1', 'Article:3')
		);

		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue($expectedRecommendation));
		$results = $this->User->Article->findRecommended('all', array());

		$this->assertCount(2, $results);
		$this->assertCount(2, $results[0]);
		$this->assertArrayHasKey('Article', $results[0]);
		$this->assertArrayHasKey('User', $results[0]);
		$this->assertCount(2, $results[1]);
		$this->assertArrayHasKey('Article', $results[1]);
		$this->assertArrayHasKey('User', $results[1]);
	}

	public function testFindRecommendedWithNoRecommendation() {
		$this->User->id = 1;

		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue(array()));
		$results = $this->User->findRecommended('all', array('limit' => 1));

		$this->assertEmpty($results);
	}

/**
 * @covers PredictionableBehavior::findRecommended
 */
	public function testFindRecommendedWithClassicArguments() {
		$this->User->id = 1;
		$this->User->bindModel(array(
			'hasMany' => array('Article')
		));

		$this->User->Article->bindModel(array(
			'belongsTo' => array('User')
		));

		$expectedRecommendation = array(
			'piids' => array('Article:1', 'Article:2')
		);

		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue($expectedRecommendation));
		$results = $this->User->findRecommended('all', array(
			'conditions' => array('Article.user_id' => 1),
			'contain' => array('User' => array('user')),
			'fields' => array('Article.title', 'Article.id')
		));

		// Recommendation return 2 articles
		// But find() should returns only 1 article, since we also filter by user_id

		$this->assertCount(1, $results);
		$this->assertArrayHasKey('Article', $results[0]);
		$this->assertArrayHasKey('User', $results[0]);

		$this->assertEquals(array('id' => 1, 'title' => 'First Article'), $results[0]['Article']);
		$this->assertEquals(array('id' => 1, 'user' => 'mariano'), $results[0]['User']);
	}

/**
 * @covers PredictionableBehavior::findRecommended
 */
	public function testFindRecommendedWithPredictionArguments() {
		$this->User->bindModel(array('hasMany' => array('Article')));

		$expected = array('itemrec_get_top_n', array('pio_n' => 2, 'pio_engine' => 'engine5'));
		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo('User:1'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue(array('piids' => array('Article:1', 'Article:2'))));
		$results = $this->User->Article->findRecommended('all', array(
			'prediction' => array('id' => 1, 'engine' => 'engine5'),
			'limit' => 2,
			'fields' => array('id', 'title'),
			'order' => 'id DESC'
		));

		// Recommendations is returning 2 Articles
		// from the limit key
		// Only fetch the id and title fields from the database

		$this->assertCount(2, $results);
		$this->assertCount(1, $results[0]);
		$this->assertEquals(array('id' => 2, 'title' => 'Second Article'), $results[0]['Article']);
		$this->assertCount(1, $results[1]);
		$this->assertEquals(array('id' => 1, 'title' => 'First Article'), $results[1]['Article']);
	}

/**
 * Find recommendation will returns results from different models
 *
 * @covers PredictionableBehavior::findRecommended
 */
	public function testFindRecommendedWithMultipleTargetModel() {
		$expected = array('itemrec_get_top_n', array('pio_n' => 2, 'pio_engine' => 'engine5'));
		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo('User:2'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue(array('piids' => array('Guild:1', 'Apple:1'))));
		$results = $this->User->findRecommended('all', array(
			'prediction' => array('id' => 2, 'engine' => 'engine5'),
			'limit' => 2,
			'fields' => array('id', 'name')
		));

		// Recommendations is returning 1 guild and 1 apple
		// Only fetch the id and name fields from the database

		$this->assertCount(2, $results);
		$this->assertCount(1, $results[0]);
		$this->assertEquals(array('id' => 1, 'name' => 'Warriors'), $results[0]['Guild']);
		$this->assertCount(1, $results[1]);
		$this->assertEquals(array('id' => 1, 'name' => 'Red Apple 1'), $results[1]['Apple']);
	}

/**
 * Test that findRecommended() will throw an Exception when called on a non-User Model
 *
 * @covers PredictionableBehavior::findRecommended
 * @expectedException InvalidUserException
 * @expectedExceptionMessage The User is not valid
 */
	public function testFindRecommendedThrownAnExceptionWhenCalledOnANonUserModel() {
		$this->Article->findRecommended('all', array());
	}

/**
 * @covers PredictionableBehavior::findSimilar
 */
	public function testFindSimilar() {
		$this->Article->id = 1;

		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue(array(2, 3)));
		$results = $this->Article->findSimilar('all', array());

		$this->assertCount(2, $results);
		$this->assertEquals(2, $results[0]['Article']['id']);
		$this->assertEquals(3, $results[1]['Article']['id']);
	}

/**
 * @covers PredictionableBehavior::findSimilar
 */
	public function testFindSimilarWithPredictionArguments() {
		$expected = array('itemsin_get_top_n', array('pio_n' => 1, 'pio_iid' => 'Article:52', 'pio_engine' => 'engine5'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue(array(2)));
		$results = $this->Article->findSimilar('all', array(
			'prediction' => array('id' => 52, 'engine' => 'engine5'),
			'limit' => 1,
			'fields' => array('id', 'title')
		));

		$this->assertCount(1, $results);
		$this->assertCount(2, $results[0]['Article']);
		$this->assertEquals(2, $results[0]['Article']['id']);
		$this->assertEquals('Second Article', $results[0]['Article']['title']);
	}
/**
 * Test that findSimilar() will throw an Exception when called on a non-User Model
 *
 * @covers PredictionableBehavior::findSimilar
 * @expectedException InvalidActionOnModelException
 * @expectedExceptionMessage You can not get similar items on the User model
 */
	public function testFindSimilarThrownAnExceptionWhenCalledOnANonUserModel() {
		$this->User->findSimilar('all', array());
	}

/**
 * Test that failing to connect to the API throws an exception
 *
 * @covers PredictionableBehavior::__execute
 * @expectedException PredictionAPIException
 * @expectedExceptionMessage Unable to connect to the predictionIO server at localhost:8000
 */
	public function testUnableToConnectToAPI() {
		$this->User->Behaviors->attach('PredictionIO.Predictionable');
		$this->User->id = 1;
		$this->User->getRecommendation();
	}
}

class User extends AppModel {

	public $useDbConfig = 'test';
}

class Article extends AppModel {

	public $useDbConfig = 'test';
}
