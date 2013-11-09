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

	public $client = null;

	public function setup(Model $model, $config = array())
	{
		$default = array(
			'userModel' => Configure::read('predictionIO.userModel'),
			'fields' => array(),
			'engine' => Configure::read('predictionIO.engine'),
			'count' => 10
		);

		if (!isset($this->settings[$model->alias])) {
			$this->settings[$model->alias] = $default;
		}
		$this->settings[$model->alias] = array_merge($this->settings[$model->alias], (array) $config);

		if (!isset($this->settings[$model->alias]['types'])) {
			$this->settings[$model->alias]['types'] = array($model->alias);
		} else if (!is_array($this->settings[$model->alias])) {
			$this->settings[$model->alias]['types'] = array($this->settings[$model->alias]['types']);
		}

		$this->setupClient($model);
	}

	public function setupClient(Model $model, $client = null) {
		$this->client = ($client === null ? \PredictionIO\PredictionIOClient::factory(array('appkey' => Configure::read('predictionIO.appkey'))) : $client);
	}

/**
 * Create or update an User/Item
 *
 * @link http://docs.prediction.io/current/apis/user.html#add-a-user
 * @link http://docs.prediction.io/current/apis/item.html#add-an-item
 *
 * @return  bool  Always true
 */
	public function afterSave(Model $model, $created) {
		if ($created || $this->_containsCustomFields($model)) {
			$response = $this->client->execute(call_user_func_array(array($this->client, 'getCommand'), $this->__buildCreateCommand($model)));
		}

		return true;
	}

/**
 * Delete an User or Item
 *
 * @link http://docs.prediction.io/current/apis/user.html#delete-a-user-record
 * @link http://docs.prediction.io/current/apis/item.html#delete-an-item
 */
	public function afterDelete(Model $model) {
		$response = $this->client->execute(call_user_func_array(array($this->client, 'getCommand'), $this->__buildDeleteCommand($model)));
	}

/**
 * Saving an user to item action
 *
 * @link   http://docs.prediction.io/current/apis/u2i.html
 * @param  Model  $model      user model
 * @param  string $actionName name of the performed action
 * @param  mixed  $itemId     item's primary key
 * @throws InvalidActionOnModelException if trying to record an action on a non-user model
 *
 * @return bool Always true
 */
	public function recordAction(Model $model, $actionName, $itemId, $optionalParameters = array()) {
		if ($model->name !== $this->settings[$model->alias]['userModel']) {
			throw new InvalidActionOnModelException(__d('predictionIO', 'You can not record an action on the ' . $model->name . ' model'));
		}

		if (empty($model->{$model->primaryKey})) {
			throw new InvalidUserException(__d('preditionIO', 'The current user does not have a primary key'));
		}

		$this->client->identify($model->{$model->primaryKey});
		$this->client->execute(call_user_func_array(array($this->client, 'getCommand'), $this->__buildRecordActionCommand($model, $actionName, $itemId, $optionalParameters)));

		return true;
	}

/**
 * Return list of item's ID recommended to the current user
 *
 * @link   http://docs.prediction.io/current/engines/itemrec/api.html
 * @param  Model  $model user model
 * @param  array  $query query arguments
 * @throws InvalidActionOnModelException if trying to get recommendations on a non-user model
 * @return array         An array of items' ID
 */
	public function getRecommendation(Model $model, $query = array()) {
		if ($model->name !== $this->settings[$model->alias]['userModel']) {
			throw new InvalidActionOnModelException(__d('predictionIO', 'You can get recommendations only on the ' . $this->settings[$model->alias]['userModel'] . ' model'));
		}

		if (!isset($query['id']) && !empty($model->{$model->primaryKey})) {
			$query['id'] = $model->{$model->primaryKey};
		}

		if (!isset($query['id'])) {
			throw new InvalidUserException(__d('predictionIO', 'You have to specify the ID of the user to get the recommendations for'));
		}

		try {
		    $this->client->identify($query['id']);
		    unset($query['id']);
		    return $this->client->execute(call_user_func_array(array($this->client, 'getCommand'), array('itemrec_get_top_n', $this->__processRetrievalQuery($model, $query))));
		} catch (Exception $e) {
		    echo 'Caught exception: ', $e->getMessage(), "\n";
		}
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
		if ($model->name === $this->settings[$model->alias]['userModel']) {
			throw new InvalidActionOnModelException(__d('predictionIO', 'You can not get similar items on the ' . $model->name . ' model'));
		}

		if (!isset($query['id']) && !empty($model->{$model->primaryKey})) {
			$query['id'] = $model->{$model->primaryKey};
		}

		if (!isset($query['id'])) {
			throw new InvalidItemException(__d('predictionIO', 'You have to specify the targeted item ID'));
		}

		$query['iid'] = $query['id'];
		unset($query['id']);

		try {
		     return $this->client->execute(call_user_func_array(array($this->client, 'getCommand'), array('itemsin_get_top_n', $this->__processRetrievalQuery($model, $query))));
		} catch (Exception $e) {
		    echo 'Caught exception: ', $e->getMessage(), "\n";
		}
	}

	public function findRecommended(Model $model, $type, $query) {
		if (!isset($query['prediction'])) {
			$query['prediction'] = array();
		}
		if (!isset($query['prediction']['count']) && isset($query['limit'])) {
			$query['prediction']['count'] = $query['limit'];
		}

		$query['conditions'][$model->alias . '.' . $model->primaryKey] = $this->getRecommendation($model, $query['prediction']);

		unset($query['prediction']);

		return $model->find($type, $query);
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
		return $this->__setInactive(($model->name === $this->settings[$model->alias]['userModel']? 'uid' : 'iid'), $model->{$model->primaryKey}, $status);
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

		foreach ($this->settings[$model->alias]['fields'] as $key) {
			if (isset($model->data[$model->alias][$key])) {
				return true;
			}
		}

		return false;
	}

/**
 * @codeCoverageIgnore
 */
	private function __setInactive($type, $id, $status) {
		return $this->client->execute(
			call_user_func_array(
				array($this->client, 'getCommand'),
				array('create_' . ($type == 'iid' ? 'item' : 'user'), array('pio_' . $type => $id, 'pio_inactive' => $status ? 'true' : 'false'))
			)
		);
	}

/**
 * @codeCoverageIgnore
 */
	private function __buildCreateCommand(Model $model) {
		if ($model->name === $this->settings[$model->alias]['userModel']) {
			$command = array('create_user', array('uid' => $model->{$model->primaryKey}));
			$reservedKeys = array('uid', 'latlng', 'inactive');
		} else {
			$command = array('create_item', array('iid' => $model->{$model->primaryKey}, 'itypes' => implode(',', $this->settings[$model->alias]['types'])));
			$reservedKeys = array('iid', 'itypes', 'latlng', 'inactive', 'startT', 'endT', 'price', 'profit');
		}

		foreach ($this->settings[$model->alias]['fields'] as $key) {
			if (isset($model->data[$model->alias][$key])) {
				$command[1][$key] = $model->data[$model->alias][$key];
			}
		}

		// Append a prefix to native keyname
		foreach ($command[1] as $key => $value) {
			if (in_array($key , $reservedKeys)) {
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
		if ($model->name === $this->settings[$model->alias]['userModel']) {
			$command = array('delete_user', array('pio_uid' => $model->{$model->primaryKey}));
		} else {
			$command = array('delete_item', array('pio_iid' => $model->{$model->primaryKey}));
		}

		return $command;
	}

/**
 * @codeCoverageIgnore
 */
	private function __buildRecordActionCommand(Model $model, $actionName, $itemId, $optionalParameters) {
		$reservedKeys = array('latlng', 't');
		$params = array('pio_action' => $actionName, 'pio_iid' => $itemId);

		// Append a prefix to native keyname
		foreach ($optionalParameters as $key => $value) {
			if (in_array($key , $reservedKeys)) {
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
					$value = (array) $value;
					foreach($value as &$type) {
						if ($type instanceof Model) {
							$type = $type->name;
						}
					}
				}

				$query['pio_' . $key] = is_array($value) ? implode(',', $value) : $value;
			}
			unset($query[$key]);
		}

		return $query;
	}
}

class InvalidActionOnModelException extends CakeException {};
class InvalidUserException extends CakeException {};
class InvalidItemException extends CakeException {};