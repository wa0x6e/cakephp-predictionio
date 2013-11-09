<?php

/**
 * View Group Test for PredictionIO
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
 * @subpackage	  PredictionIO.Test.Case
 * @since         0.1.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 **/

/**
 * AllPredictionIOTest class
 *
 * @package 	PredictionIO
 * @subpackage 	PredictionIO.Test.Case
 */
class AllPredictionIOTest extends CakeTestSuite {

	public static function suite() {
		$suite = new CakeTestSuite('All model tests');
		$suite->addTestDirectory(__DIR__ . DS . 'Behavior');
		return $suite;
	}
}
