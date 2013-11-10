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

	public $fixtures = array('User', 'Post');

	public function setUp() {
		parent::setUp();

		$this->PredictionIOClient = $this->getMock('\PredictionIO\PredictionIOClient', array('identify', 'getCommand', 'execute'));

		$this->User = ClassRegistry::init('User');
		$this->User->Behaviors->attach('PredictionIO.Predictionable');
		$this->User->setupClient($this->PredictionIOClient);

		$this->Post = ClassRegistry::init('Post');
		$this->Post->Behaviors->attach('PredictionIO.Predictionable');
		$this->Post->setupClient($this->PredictionIOClient);
	}

	public function testDefaultConfig() {
		$this->assertEquals('your-key', Configure::read('predictionIO.appkey'));
		$this->assertEquals('User', Configure::read('predictionIO.userModel'));
		$this->assertEquals('', Configure::read('predictionIO.engine'));

		$this->assertCount(3, Configure::read('predictionIO'));
	}

	public function testPredictionIOLibraryIsLoaded() {
		$this->assertTrue(class_exists('\PredictionIO\PredictionIOClient'));
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testCreateUserWillCreateAUserRecord() {
		$user = array('id' => 45, 'user' => 'jenny', 'password' => 'password');

		$expected = array('create_user', array('pio_uid' => $user['id']));
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
	public function testCreatePostWillCreateAnItemRecord() {
		$post = array('id' => 46, 'author_id' => 25, 'title' => 'My First post');

		$expected = array('create_item', array('pio_iid' => $post['id'], 'pio_itypes' => 'Post'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Post->save(array('Post' => $post));
		$this->assertEquals($post['id'], $r['Post']['id']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testCreateUserWithCustomFields() {
		$this->User->Behaviors->load('PredictionIO.Predictionable', array('fields' => array('user')));
		$this->User->setupClient($this->PredictionIOClient);

		$user = array('id' => 45, 'user' => 'jenny', 'password' => 'password');

		$expected = array('create_user', array('pio_uid' => $user['id'], 'user' => $user['user']));
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
	public function testCreatePostWithCustomFields() {
		$this->Post->Behaviors->load('PredictionIO.Predictionable', array('fields' => array('author_id', 'title')));
		$this->Post->setupClient($this->PredictionIOClient);

		$post = array('id' => 46, 'author_id' => 25, 'title' => 'My First post', 'body' => 'body?');

		$expected = array('create_item', array('pio_iid' => $post['id'], 'author_id' => $post['author_id'], 'title' => $post['title'], 'pio_itypes' => 'Post'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Post->save(array('Post' => $post));
		$this->assertEquals($post['id'], $r['Post']['id']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testCreatePostWithMultipleTypes() {
		$this->Post->Behaviors->load('PredictionIO.Predictionable', array('types' => array('Movie', 'Entertainment')));
		$this->Post->setupClient($this->PredictionIOClient);

		$post = array('id' => 46, 'author_id' => 25, 'title' => 'My First post', 'body' => 'body?');

		$expected = array('create_item', array('pio_iid' => $post['id'], 'pio_itypes' => 'Movie,Entertainment'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Post->save(array('Post' => $post));
		$this->assertEquals($post['id'], $r['Post']['id']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testUsePostModelAsUserModelSetFromModel() {
		$this->Post->Behaviors->load('PredictionIO.Predictionable', array('userModel' => 'Post'));
		$this->Post->setupClient($this->PredictionIOClient);

		$post = array('id' => 46, 'author_id' => 25, 'title' => 'My First post', 'body' => 'body?');

		$expected = array('create_user', array('pio_uid' => $post['id']));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Post->save(array('Post' => $post));
		$this->assertEquals($post['id'], $r['Post']['id']);
	}

/**
 * @covers PredictionableBehavior::afterSave
 */
	public function testUsePostModelAsUserModelSetFromBootstrap() {
		Configure::write('predictionIO.userModel', 'Post');
		$this->Post->Behaviors->unload('PredictionIO.Predictionable');
		$this->Post->Behaviors->load('PredictionIO.Predictionable');
		$this->Post->setupClient($this->PredictionIOClient);

		$post = array('id' => 46, 'author_id' => 25, 'title' => 'My First post', 'body' => 'body?');

		$expected = array('create_user', array('pio_uid' => $post['id']));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Post->save(array('Post' => $post));
		$this->assertEquals($post['id'], $r['Post']['id']);
	}

/**
 * @covers PredictionableBehavior::afterDelete
 */
	public function testDeleteUserWillDeleteAUserRecord() {
		$expected = array('delete_user', array('pio_uid' => 1));
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
	public function testDeletePostWillDeleteAnItemRecord() {
		$expected = array('delete_item', array('pio_iid' => 1));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->assertTrue($this->Post->delete(1));
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
	public function testUpdatePostWithNoCustomFields() {
		// Asserting that the post really exists before updating
		$this->assertEquals(1, $this->Post->find('count', array('conditions' => array('Post.id' => 1))));

		$this->PredictionIOClient->expects($this->never())->method('getCommand');
		$this->PredictionIOClient->expects($this->never())->method('execute');

		$post = array('id' => 1, 'title' => 'my new title');
		$r = $this->Post->save(array('Post' => $post));
		$this->assertEquals($post['id'], $r['Post']['id']);
		$this->assertEquals($post['title'], $r['Post']['title']);
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

		$expected = array('create_user', array('pio_uid' => $user['id'], 'user' => $user['user']));
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
	public function testUpdatePostWithCustomFields() {
		$this->Post->Behaviors->load('PredictionIO.Predictionable', array('fields' => array('title')));
		$this->Post->setupClient($this->PredictionIOClient);

		// Asserting that the user really exists before updating
		$this->assertEquals(1, $this->Post->find('count', array('conditions' => array('Post.id' => 1))));

		$post = array('id' => 1, 'title' => 'my new title');

		$expected = array('create_item', array('pio_iid' => $post['id'], 'title' => $post['title'], 'pio_itypes' => 'Post'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$r = $this->Post->save(array('Post' => $post));
		$this->assertEquals($post['id'], $r['Post']['id']);
		$this->assertEquals($post['title'], $r['Post']['title']);
	}

/**
 * @covers PredictionableBehavior::recordAction
 */
	public function testRecordAction() {
		$this->User->id = 1;

		$expected = array('record_action_on_item', array('pio_action' => 'like', 'pio_iid' => 53));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo(1));
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->assertTrue($this->User->recordAction($expected[1]['pio_action'], $expected[1]['pio_iid']));
	}

/**
 * Test that triggering a recordAction on model other that the User Model
 * will throw an exception
 *
 * @covers PredictionableBehavior::recordAction
 * @expectedException InvalidActionOnModelException
 * @expectedExceptionMessage You can not record an action on the Post model
 */
	public function testRecordActionOnNonUserModel() {
		$this->Post->id = 1;

		$this->Post->recordAction('like', 53);
	}

/**
 * Test that triggering a recordAction on a User model with no primary key
 * will throw an exception
 *
 * @covers PredictionableBehavior::recordAction
 * @expectedException InvalidUserException
 * @expectedExceptionMessage The current user does not have a primary key
 */
	public function testRecordActionOnUserWithNoPrimaryKey() {
		$this->User->recordAction('like', 53);
	}

/**
 * Test saving custom fields along the recordAction
 *
 * @covers PredictionableBehavior::recordAction
 */
	public function testRecordActionWithCustomFields() {
		$this->User->id = 1;

		$expected = array('record_action_on_item', array('pio_action' => 'like', 'pio_iid' => 53, 'rating' => 6, 'level' => 'medium'));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo(1));
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->User->recordAction($expected[1]['pio_action'], $expected[1]['pio_iid'], array('rating' => 6, 'level' => 'medium'));
	}

/**
 * @covers PredictionableBehavior::getRecommendation
 */
	public function testGetRecommendation() {
		$this->User->id = 1;

		$expected = array('itemrec_get_top_n', array('pio_engine' => '', 'pio_n' => 10));

		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo(1));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->User->getRecommendation();
	}

/**
 * @covers PredictionableBehavior::getRecommendation
 */
	public function testGetRecommendationWithIdSetFromArguments() {
		$this->User->id = 1;

		$expected = array('itemrec_get_top_n', array('pio_engine' => '', 'pio_n' => 10));

		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo(52));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->User->getRecommendation(array('id' => 52));
	}

/**
 * Test getRecommendation() with optional parameters,
 * set when calling getRecommendation()
 *
 * @covers PredictionableBehavior::getRecommendation
 */
	public function testGetRecommendationWithOptionalParameters() {
		$this->User->id = 1;

		$expected = array('itemrec_get_top_n', array('pio_engine' => 'engine1', 'pio_n' => 12, 'pio_attributes' => 'a,b', 'pio_itypes' => 'Post,User'));

		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo(1));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->User->getRecommendation(array(
			'engine' => $expected[1]['pio_engine'],
			'count' => $expected[1]['pio_n'],
			'attributes' => array('a', 'b'),
			'invalid' => 'no-value',
			'itypes' => array($this->Post, $this->User)

		));
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

		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo(1));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->User->getRecommendation();
	}

/**
 * Test that getRecommendation() can be only called from a User Model
 *
 * @covers PredictionableBehavior::getRecommendation
 * @expectedException InvalidActionOnModelException
 * @expectedExceptionMessage You can get recommendations only on the User model
 */
	public function testGetRecommendationThrownAnExceptionWhenCalledOnANonUserModel() {
		$this->Post->getRecommendation();
	}

/**
 * Test that getRecommendation() will throw an Exception when the current User is not initialized
 *
 * @covers PredictionableBehavior::getRecommendation
 * @expectedException InvalidUserException
 * @expectedExceptionMessage You have to specify the ID of the user to get the recommendations for
 */
	public function testGetRecommendationThrownAnExceptionOnUserWithNoPrimaryKey() {
		$this->User->getRecommendation();
	}

/**
 * @covers PredictionableBehavior::getSimilar
 */
	public function testGetSimilar() {
		$this->Post->id = 1;

		$expected = array('itemsin_get_top_n', array('pio_engine' => '', 'pio_n' => 10, 'pio_iid' => 1));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->Post->getSimilar();
	}

/**
 * @covers PredictionableBehavior::getSimilar
 */
	public function testGetSimilarWithTargetIdSetFromArguments() {
		$this->Post->id = 1;

		$expected = array('itemsin_get_top_n', array('pio_engine' => '', 'pio_n' => 10, 'pio_iid' => 52));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->Post->getSimilar(array('id' => 52));
	}

/**
 * @covers PredictionableBehavior::getSimilar
 */
	public function testGetSimilarWithOptionalParameters() {
		$this->Post->id = 1;

		$expected = array('itemsin_get_top_n', array('pio_engine' => '', 'pio_n' => 10, 'pio_iid' => 1, 'pio_attributes' => 'a,b'));

		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');

		$this->Post->getSimilar(array('engine' => $expected[1]['pio_engine'], 'attributes' => array('a,b'), 'invalid' => 'hi'));
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
		$this->Post->getSimilar();
	}

/**
 * @covers PredictionableBehavior::disablePrediction
 */
	public function testDisablePredictionOnUser() {
		$this->User->id = 1;

		$expected = array('create_user', array('pio_inactive' => 'true', 'pio_uid' => 1));
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

		$expected = array('create_user', array('pio_inactive' => 'false', 'pio_uid' => 1));
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
		$this->Post->id = 1;

		$expected = array('create_item', array('pio_inactive' => 'true', 'pio_iid' => 1));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');
		$this->Post->disablePrediction();
	}

/**
 * @covers PredictionableBehavior::enablePrediction
 */
	public function testEnablePredictionOnItem() {
		$this->Post->id = 1;

		$expected = array('create_item', array('pio_inactive' => 'false', 'pio_iid' => 1));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute');
		$this->Post->enablePrediction();
	}

/**
 * @covers PredictionableBehavior::findRecommended
 */
	public function testFindRecommended() {
		$this->User->id = 1;

		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue(array(2, 4)));
		$results = $this->User->findRecommended('all', array());

		$this->assertCount(2, $results);
		$this->assertEquals(2, $results[0]['User']['id']);
		$this->assertEquals(4, $results[1]['User']['id']);
	}

/**
 * @covers PredictionableBehavior::findRecommended
 */
	public function testFindRecommendedWithPredictionArguments() {
		$expected = array('itemrec_get_top_n', array('pio_n' => 1, 'pio_engine' => 'engine5'));
		$this->PredictionIOClient->expects($this->once())->method('identify')->with($this->equalTo(1));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue(array(2)));
		$results = $this->User->findRecommended('all', array(
			'prediction' => array('id' => 1, 'engine' => 'engine5'),
			'limit' => 1,
			'fields' => array('id', 'user')
		));

		$this->assertCount(1, $results);
		$this->assertCount(2, $results[0]['User']);
		$this->assertEquals(2, $results[0]['User']['id']);
		$this->assertEquals('nate', $results[0]['User']['user']);
	}

/**
 * Test that findRecommended() will throw an Exception when called on a non-User Model
 *
 * @covers PredictionableBehavior::findRecommended
 * @expectedException InvalidActionOnModelException
 * @expectedExceptionMessage You can get recommendations only on the User model
 */
	public function testfindRecommendedThrownAnExceptionWhenCalledOnANonUserModel() {
		$this->Post->findRecommended('all', array());
	}

/**
 * @covers PredictionableBehavior::findSimilar
 */
	public function testFindSimilar() {
		$this->Post->id = 1;

		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue(array(2, 3)));
		$results = $this->Post->findSimilar('all', array());

		$this->assertCount(2, $results);
		$this->assertEquals(2, $results[0]['Post']['id']);
		$this->assertEquals(3, $results[1]['Post']['id']);
	}

/**
 * @covers PredictionableBehavior::findSimilar
 */
	public function testFindSimilarWithPredictionArguments() {
		$expected = array('itemsin_get_top_n', array('pio_n' => 1, 'pio_iid' => 6, 'pio_engine' => 'engine5'));
		$this->PredictionIOClient->expects($this->once())->method('getCommand')->with(
			$this->equalTo($expected[0]),
			$this->equalTo($expected[1])
		);
		$this->PredictionIOClient->expects($this->once())->method('execute')->will($this->returnValue(array(2)));
		$results = $this->Post->findSimilar('all', array(
			'prediction' => array('id' => 6, 'engine' => 'engine5'),
			'limit' => 1,
			'fields' => array('id', 'title')
		));

		$this->assertCount(1, $results);
		$this->assertCount(2, $results[0]['Post']);
		$this->assertEquals(2, $results[0]['Post']['id']);
		$this->assertEquals('Second Post', $results[0]['Post']['title']);
	}
/**
 * Test that findSimilar() will throw an Exception when called on a non-User Model
 *
 * @covers PredictionableBehavior::findSimilar
 * @expectedException InvalidActionOnModelException
 * @expectedExceptionMessage You can not get similar items on the User model
 */
	public function testfindSimilarThrownAnExceptionWhenCalledOnANonUserModel() {
		$this->User->findSimilar('all', array());
	}

}

class User extends AppModel {

	public $useDbConfig = 'test';
}

class Post extends AppModel {

	public $useDbConfig = 'test';
}
