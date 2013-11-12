<?php
/**
 * PredictionableIO behavior
 *
 * PHP versions 5
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author        Wan Qi Chen <kami@kamisama.me>
 * @copyright     Copyright 2013, Wan Qi Chen <kami@kamisama.me>
 * @link          http://cakeresque.kamisama.me
 * @package 	  PredictionIO
 * @subpackage 	  PredictionIO.Model.Behavior
 * @since         0.1.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 **/

/**
 * PredictionableBehavior class
 *
 * @package 	PredictionIO
 * @subpackage 	PredictionIO.Model.Behavior
 */
class PredictionableBehavior extends ModelBehavior {

	public $settings = array();

	protected $_client = null;

	public static $itemIDSeparator = ':';

	public function setup(Model $model, $config = array()) {
		$default = array(
			'userModel' => Configure::read('predictionIO.userModel'),
			'fields' => array(),
			'engine' => Configure::read('predictionIO.engine'),
			'count' => 10,
			'prefix' => $model->alias . PredictionableBehavior::$itemIDSeparator
		);

		if (!isset($this->settings[$model->alias])) {
			$this->settings[$model->alias] = $default;
		}
		$this->settings[$model->alias] = array_merge($this->settings[$model->alias], (array)$config);

		if (!isset($this->settings[$model->alias]['types'])) {
			$this->settings[$model->alias]['types'] = array($model->alias);
		}
		$this->settings[$model->alias]['types'] = (array)$this->settings[$model->alias]['types'];

		$this->setupClient($model);
	}

	public function setupClient(Model $model, $client = null) {
		$this->_client = ($client === null ?
			\PredictionIO\PredictionIOClient::factory(array(
				'appkey' => Configure::read('predictionIO.appkey'),
				'apiurl' => Configure::read('predictionIO.apiurl'),
			)) :
			$client
		);
	}

/**
 * Create or update an User/Item
 *
 * @link http://docs.prediction.io/current/apis/user.html#add-a-user
 * @link http://docs.prediction.io/current/apis/item.html#add-an-item
 *
 * @param  Model $model Model using this behavior
 * @param  bool $created True if this save created a new record
 * @param  array $options
 *
 * @return  void
 */
	public function afterSave(Model $model, $created, $options = array()) {
		if ($created || $this->_containsCustomFields($model)) {
			$this->__execute(call_user_func_array(array($this->_client, 'getCommand'), $this->__buildCreateCommand($model)));
		}
	}

/**
 * Delete an User or Item
 *
 * @link http://docs.prediction.io/current/apis/user.html#delete-a-user-record
 * @link http://docs.prediction.io/current/apis/item.html#delete-an-item
 *
 * @param  Model $model Model using this behavior
 *
 * @return  void
 */
	public function afterDelete(Model $model) {
		$this->__execute(call_user_func_array(array($this->_client, 'getCommand'), $this->__buildDeleteCommand($model)));
	}

/**
 * Saving an user to item action
 *
 * @link   http://docs.prediction.io/current/apis/u2i.html
 * @param  Model  $model      user model
 * @param  string $actionName name of the performed action
 * @param  Model|array  $targetItem     Target item model, or an array with the item Model name and primary key
 * @throws InvalidActionOnModelException if trying to record an action on a non-user model
 * @throws InvalidItemException if the target item is invalid
 *
 * @return bool Always true
 */
	public function recordAction(Model $model, $actionName, $targetItem, $optionalParameters = array()) {
		if (!$this->__isUserModel($model)) {
			throw new InvalidActionOnModelException(__d('predictionIO', 'You can not record an action on the ' . $model->alias . ' model'));
		}

		$this->_client->identify($this->_getModelId($model));

		if ($targetItem instanceof Model) {
			$itemId = $this->_getModelId($targetItem);
		} elseif (is_array($targetItem) && isset($targetItem['id']) && isset($targetItem['model'])) {
			$itemId = $this->_getModelId($targetItem['model'], $targetItem['id']);
		} else {
			throw new InvalidItemException(__d('predictionIO', 'The target item is not valid'));
		}

		$this->__execute(call_user_func_array(array($this->_client, 'getCommand'), $this->__buildRecordActionCommand($model, $actionName, $itemId, $optionalParameters)));

		return true;
	}

/**
 * Return list of item's ID recommended to the current user
 *
 * @link   http://docs.prediction.io/current/engines/itemrec/api.html
 * @param  Model  $model user model
 * @param  array  $query query arguments
 * @throws InvalidUserException if trying to get recommendations on a non-initialized user
 *
 * @return array         An array of recommendations
 */
	public function getRecommendation(Model $model, $query = array()) {
		$userClass = $this->settings[$model->alias]['userModel'];

		// Check that we have at least a User 1 somewhere
		if (!$this->__isUserModel($model) && !isset($model->$userClass) && !isset($query['id'])) {
			throw new InvalidUserException(__d('predictionIO', 'The User is not valid'));
		}

		if (isset($query['id'])) {
			$userId = $query['id'];
			unset($query['id']);
		} else {
			$userId = $this->__isUserModel($model) ? $model->{$model->primaryKey} : $model->$userClass->{$model->primaryKey};
		}

		$this->_client->identify($this->_getModelId($userClass, $userId));
		$response = $this->__execute(call_user_func_array(array($this->_client, 'getCommand'), array('itemrec_get_top_n', $this->__processRetrievalQuery($model, $query))));

		if (empty($response)) {
			return $response;
		}

		$return = array_map(function($id) {
			return array_combine(array('model', 'id'), explode(PredictionableBehavior::$itemIDSeparator, $id));
		}, $response['piids']);

		if (isset($query['attributes']) && !empty($query['attributes'])) {
			foreach ($query['attributes'] as $attribute) {
				for ($i = 0, $total = count($return); $i < $total; $i++) {
					$return[$i][$attribute] = $response[$attribute][$i];
				}
			}
		}

		return $return;
	}

/**
 * Return list of items ID similar to the current one
 *
 * @link   http://docs.prediction.io/current/engines/itemsim/index.html
 * @param  Model  $model item model
 * @param  array  $query query arguments
 * @throws InvalidActionOnModelException if trying to get similar items on a user model
 * @throws InvalidItemException if the targeted item ID is not specified
 * @return array         An array of items' ID
 */
	public function getSimilar(Model $model, $query = array()) {
		if ($this->__isUserModel($model)) {
			throw new InvalidActionOnModelException(__d('predictionIO', 'You can not get similar items on the ' . $model->alias . ' model'));
		}

		if (!isset($query['id']) && !empty($model->{$model->primaryKey})) {
			$query['id'] = $model->{$model->primaryKey};
		}

		if (!isset($query['id'])) {
			throw new InvalidItemException(__d('predictionIO', 'You have to specify the targeted item ID'));
		}

		$query['iid'] = $this->_getModelId($model->alias, $query['id']);
		unset($query['id']);

		return $this->__execute(call_user_func_array(array($this->_client, 'getCommand'), array('itemsin_get_top_n', $this->__processRetrievalQuery($model, $query))));
	}

	public function findRecommended(Model $model, $type, $query) {
		if (!isset($query['prediction'])) {
			$query['prediction'] = array();
		}
		if (!isset($query['prediction']['count']) && isset($query['limit'])) {
			$query['prediction']['count'] = $query['limit'];
		}

		$recommendations = $this->getRecommendation($model, $query['prediction']);

		if (empty($recommendations)) {
			return array();
		}

		$recommendationsConditions = array();
		foreach ($recommendations as $entry) {
			$recommendationsConditions[$entry['model']][] = $entry['id'];
		}

		$results = array();
		foreach ($recommendationsConditions as $modelName => $ids) {
			$targetModel = isset($model->$modelName) ? $model->$modelName : ClassRegistry::init($modelName);
			$targetQuery = $query;
			unset($targetQuery['prediction']);
			$targetQuery['conditions'][$targetModel->alias . '.id'] = $ids;

			$results = array_merge($results, $targetModel->find($type, $targetQuery));
		}
		return $results;
	}

	public function findSimilar(Model $model, $type, $query) {
		if (!isset($query['prediction'])) {
			$query['prediction'] = array();
		}
		if (!isset($query['prediction']['count']) && isset($query['limit'])) {
			$query['prediction']['count'] = $query['limit'];
		}

		$query['conditions'][$model->alias . '.' . $model->primaryKey] = $this->getSimilar($model, $query['prediction']);
		unset($query['prediction']);

		return $model->find($type, $query);
	}

	public function disablePrediction(Model $model, $status = true) {
		return $this->__setInactive(($this->__isUserModel($model) ? 'uid' : 'iid'), $this->_getModelId($model), $status);
	}

	public function enablePrediction(Model $model) {
		return $this->disablePrediction($model, false);
	}

/**
 * Check if the current model contains any custom fields
 *
 * @codeCoverageIgnore
 */
	protected function _containsCustomFields(Model $model) {
		if (empty($this->settings[$model->alias]['fields'])) {
			return false;
		}

		$keys = array_intersect_key($model->data[$model->alias], array_flip($this->settings[$model->alias]['fields']));
		return !empty($keys);
	}

/**
 * Return a unique model ID
 *
 * @codeCoverageIgnore
 * @throws InvalidUserException if the user does not have a primary key
 * @throws InvalidItemException if the item does not have a primary key
 *
 * @param  Model $model The model
 * @return string A unique model ID
 */
	protected function _getModelId($model, $id = null) {
		if ($model instanceof Model) {
			$id = $model->{$model->primaryKey};
			$model = $model->alias;
		}

		$message = __d('preditionIO', 'The %s is not valid', $model);

		if (empty($id)) {
			throw $this->__isUserModel($model) ? new InvalidUserException($message) : new InvalidItemException($message);
		}

		return $this->settings[$model]['prefix'] . $id;
	}

/**
 * Send a command to the PredictionIO server
 *
 * @codeCoverageIgnore
 * @throws PredictionAPIException 	If unable to connect to the PredictionIo API server.
 *         						All other connection error are muted, and a blank response will be returned
 *
 * @return array The server response to the command
 */
	private function __execute($command) {
		try {
			return $this->_client->execute($command);
		} catch (Guzzle\Http\Exception\CurlException $e) {
			throw new PredictionAPIException(__d('predictionIO', 'Unable to connect to the predictionIO server at %s', Configure::read('predictionIO.host')));
		} catch(Exception $e) {
			// Mute all other errors
			// Can't throw an exception here, since no recommendation is raising an exception
			return array();
		}
	}

/**
 * @codeCoverageIgnore
 */
	private function __setInactive($type, $id, $status) {
		return $this->__execute(
			call_user_func_array(
				array($this->_client, 'getCommand'),
				array('create_' . ($type == 'iid' ? 'item' : 'user'), array('pio_' . $type => $id, 'pio_inactive' => $status ? 'true' : 'false'))
			)
		);
	}

/**
 * @codeCoverageIgnore
 */
	private function __buildCreateCommand(Model $model) {
		if ($this->__isUserModel($model)) {
			$command = array('create_user', array('uid' => $this->_getModelId($model)));
			$reservedKeys = array('uid', 'latlng', 'inactive');
		} else {
			$command = array('create_item', array('iid' => $this->_getModelId($model), 'itypes' => implode(',', $this->settings[$model->alias]['types'])));
			$reservedKeys = array('iid', 'itypes', 'latlng', 'inactive', 'startT', 'endT', 'price', 'profit');
		}

		foreach ($this->settings[$model->alias]['fields'] as $key) {
			if (isset($model->data[$model->alias][$key])) {
				$command[1][$key] = $model->data[$model->alias][$key];
			}
		}

		// Append a prefix to native keyname
		foreach ($command[1] as $key => $value) {
			if (in_array($key, $reservedKeys)) {
				$command[1]['pio_' . $key] = $value;
				unset($command[1][$key]);
			}
		}

		return $command;
	}

/**
 * @codeCoverageIgnore
 */
	private function __buildDeleteCommand(Model $model) {
		$keyNames = array(
			0 => array('user', 'uid'),
			1 => array('item', 'iid')
		);
		$keyName = $keyNames[$this->__isUserModel($model) ? 0 : 1];

		return array('delete_' . $keyName[0], array('pio_' . $keyName[1] => $this->_getModelId($model)));
	}

/**
 * @codeCoverageIgnore
 */
	private function __buildRecordActionCommand(Model $model, $actionName, $itemId, $optionalParameters) {
		$reservedKeys = array('latlng', 't');
		$params = array('pio_action' => $actionName, 'pio_iid' => $itemId);

		// Append a prefix to native keyname
		foreach ($optionalParameters as $key => $value) {
			if (in_array($key, $reservedKeys)) {
				$optionalParameters['pio_' . $key] = $value;
				unset($optionalParameters[$key]);
			}
		}

		return array('record_action_on_item', array_merge($params, $optionalParameters));
	}

/**
 * Build the query arguments passed to getRecommendation and getSimilar
 *
 * @codeCoverageIgnore
 */
	private function __processRetrievalQuery(Model $model, $query) {
		$default = array('engine' => $this->settings[$model->alias]['engine'], 'count' => $this->settings[$model->alias]['count']);
		$query = array_merge($default, $query);

		$query['n'] = $query['count'];
		unset($query['count']);

		$whiltelist = array('uid', 'iid', 'engine', 'n', 'itypes', 'latlng', 'within', 'unit', 'attributes');
		foreach ($query as $key => $value) {
			if (in_array($key, $whiltelist)) {

				// Flatten types
				if ($key === 'itypes') {
					$value = (array)$value;
					foreach ($value as &$type) {
						if ($type instanceof Model) {
							$type = $type->alias;
						}
					}
				}

				$query['pio_' . $key] = is_array($value) ? implode(',', $value) : $value;
			}
			unset($query[$key]);
		}

		return $query;
	}

/**
 * Check if the specified model is the User Model
 *
 * @codeCoverageIgnore
 *
 * @param  Model  $model Model tp check
 * @return bool True if the model is the User model
 */
	private function __isUserModel($model) {
		if ($model instanceof Model) {
			$model = $model->alias;
		}
		return ($model === $this->settings[$model]['userModel']);
	}

}

class InvalidActionOnModelException extends CakeException {

};

class InvalidUserException extends CakeException {

};

class InvalidItemException extends CakeException {

};

class PredictionAPIException extends CakeException {

};
