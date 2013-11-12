# PredictionIO for CakePHP

[![Build Status](https://travis-ci.org/kamisama/cakephp-predictionio.png?branch=master)](https://travis-ci.org/kamisama/cakephp-predictionio) [![Coverage Status](https://coveralls.io/repos/kamisama/cakephp-predictionio/badge.png)](https://coveralls.io/r/kamisama/cakephp-predictionio) [![Latest Stable Version](https://poser.pugx.org/kamisama/cakephp-predictionio/v/stable.png)](https://packagist.org/packages/kamisama/cakephp-predictionio)


CakePHP Plugin for the PredictionIO machine learning server

## Installation

### Install the plugin

```
cd your-application/app/Plugin

# Classic install
git clone git://github.com/kamisama/cakephp-predictionio.git PredictionIO

# OR install as a submodule
git submodule add git://github.com/kamisama/cakephp-predictionioe.git PredictionIO
```

### Install dependencies

```
cd PredictioIO
composer install
```

### Load the plugin

In your `app/Config/bootstrap.php`, load the plugin

```php
CakePlugin::load(array('PredictionIO' => array('bootstrap' => true)));
```

## Usage

### Settings

Put your predictionIO api key in `app/Plugin/PredictionIO.Config/bootstrap.php`

You can also define which model will be used as the User model, and the name of the default engine used for your recommendation.

### Using the Predictionable Behavior

The behavior will synchronize your models with the predictionIO server on each save/update/delete operation.
By default, only the model primary key is sent to predictionIO, along with the model name.

Attach the Predictionable behavior to your Model

```php
$actsAs = array(
  'PredictionIO.Predictionable' => array(
    //'fields' => array(),
    //'types' => array(),
    //'engine' => '',
    //'count' => 10
  )
);
```

Optional settings:

* `fields`: An array of additional fields to save along the predictionIO record
* `types`: An array of categories name, assigned to this model on the PredictionIo server (default is the current model name)
* `engine`: The name of the default engine used to fetch recommendation from
* `count`: The default number of records to fetch from predictionIO
 
`engine` and `count` can be overwritten later.

Now, you have to save user-to-item behavior before computing any recommendations. 

Examples: User:1 <like> Post:52, User:4 <view> Page:26, User:85 <rate> Movie:8, etc ...

Those actions are saved from the User Model with:

```php
$User->recordAction($actionName, $targetItem, $optionalParameters);
```

* `$actionName`: Name of the action, eg: save, like, rate, view, etc ...
* `$targetItem`: Target item of the action
* `$optionalParameters`: an array of additional fields to save along the action, eg: `array('note', 52)`

Example:

```php
$User->id = 2;
$Post->id = 25;
$User->recordAction('rate', $Post, array('note' => 10));
```

You can also use the array alternative for referencing the target item:

```php
$User->id = 2;
$User->recordAction('rate', array('model' => 'Post', 'id' => 25), array('note' => 10));
```

## Getting items recommendations for an User

Retrieve items recommended to a specific user

`findRecommended()` can be used on a User model, or on an other model, as long as it's binded to the User model.

```php
$User->id = 5;
$User->findRecommended('all', array());

// Or
$Article->User->id = 5;
$Article->findRecommended('all', array());
```

`findRecommended()` accepts the same arguments as the classic `find()` method, and is always called by the **recommended item**.

Example

```php
// Getting movies and activities recommendations
$User->id = 5;
$User->findRecommended('all', $options);

// Is equivalent to
$Movie->find('all', $options);
$Activity->find('all', $options);

// And will returns the results form these 2 find() actions
// Of course, the right ID will be injected into the $options,
// to fetch only the recommended items from yoru datasource
```

The type of model return depends on the type of models handled by your engine.

`$options` can take an additional `prediction` key:

```php
'prediction' => array(
  'id' => $userId,
  'engine' => 'engine1'
)
```

## Getting similar items recommendations

Retrieve items silimar to another item

Use the `findSimilar()` methods. It accepts the same arguments as the classic `find` method, but will only returns
results from similar items.

It accepts an additional `prediction` argument in the query options

```php
$Post->id = 2;
$Post->findSimilar('all', array(
  'conditions' => array(),
  'fields' => array(),
  'limit' => 15,
  'prediction' => array(
    'engine' => 'engine1',
    'count' => 8
  )
));
```

In the `prediction` key, if:

* no engine is specified, it'll default to the one set when loading the Behavior, then the one set in the config
* no count is specified, it'll default to the `limit` key, then the count set when loading the behavior.

`findSimilar()` will in fact just find the ID of similar items, then put then in the `conditions` key.
If you have something in `conditions.Post.id`, it'll be overwritten.  
All other settings will be used to fetch the data from the original datasource.

To specify the ID of the items to get similars results to, you can either set the primary key of the current Model

`$Post-id = 2;`

or you can specify it in the query

```php
$Post->findSimilar('all', array('id' => 2));
```

