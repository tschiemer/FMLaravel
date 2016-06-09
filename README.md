
**Please note**
this is a development fork of FMLaravel and ideally the original repository will implement any or all of these features. For organizational reasons it has not been pulled as of yet.
 So please also check on its status, because maybe it's now more advanced.


# FMLaravel

This package adds an Laravel compatible abstraction of the Filemaker API that gives you the following features to use:
 
 - Encapsulate your Filemaker records as Eloquent models with the basic read/write operations
 - Run Filemaker scripts (on the Filemaker Server)
 - Authenticate user credentials against Filemaker Server users
 - Easy access to raw Filemaker PHP API using laravel supplied configuration settings (if needed)

# Todo

- Related records: allow adding/removing
- write documentation for authentication
- write unit tests


# Installation

## Installing the Laravel framework

You will need the Composer PHP package manager to install Laravel and FMLaravel.  You can install Composer from getcomposer.org

If you do not yet have the Laravel framework installed you will need to install Laravel by running the following command in terminal:

	composer create-project laravel/laravel YourProjectName

Once Composer finishes the instalation you will need to give Laravel write access to the storage directory by running the following command in terminal:

	chmod -R 777 storage

## Installing FMLaravel (this fork)

Add the following lines to your `composer.json`

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/tschiemer/FMLaravel"
        }
    ],

Run the following terminal command to install this fork of FMLaravel

	composer require tschiemer/fm-laravel:0.4.0-beta


Back in your text editor open config/app.php and add the following line to the providers array:

	'FMLaravel\Database\FileMakerServiceProvider',

In config/database.php add the following to the connections array:


        'filemaker' => [
            'driver'   => 'filemaker',
            'host'     => env('DB_HOST'),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'logLevel' => false,                     // possible values: false, 'error', 'info', 'debug'

    //            'properties' => [],               // override/feed any settings to the filemaker engine @see class FileMaker
    //            'cacheStore' => 'file',           // override default filemaker cache store settings
    //            'read'     => [],                 // override settings for read operations
    //            'write'    => [],                 // override settings for write operations
    //            'script'   => []                  // override settings for script operations
        ],

Note that by adding options into the `read`, `write` or `script` arrays you can override the settings for these operations. Any key-value pair as for the default configuration is possible.

In your root directory create a new file named .env and add the following while including your database connection details:

	DB_HOST=YourHost
	DB_DATABASE=YourDatabase
	DB_USERNAME=YourUsername
	DB_PASSWORD=YourPassword

Note that if you are using version control you do not want the .env file to be a part of your repository so it is included in .gitignore by default.

In case you want to use your Filemaker server as the default (or only) model source, change the default connection type to `filemaker` in `config/database.php`:

	'default' => 'filemaker',


You might run into an error message as also mentioned in https://github.com/andrewmile/FMLaravel/issues/1#issuecomment-213443303 where you'll also find a quickfix.



# Usage

## Raw Filemaker PHP API Access

To access the raw Filemaker PHP API (as officially provided by FileMaker Inc) you first have to retrieve a connection abstraction and then get the filemaker instance, as follows:
 
    // Load filemaker connection and retrieve engine for read configuration
    $filemaker = DB::connection('filemaker')->filemaker('read');
    
    // Load default filemaker engine from default connection
    // NOTE _only_ works when you have setup 'filemaker' to be the default connection in `database.php` (see above) 
    $filemaker = DB::filemaker();
    
On the `$filemaker` object you may now perform any operations as provided by the FileMaker class (also see composer package `andrewmile/fm-api`). As an example:

    $filemaker->getLayout('myLayout');

Also basic listings as also provided by the Filemaker API can be shortcutted as follows:

    DB::listDatabases();
    DB::listScripts();
    DB::listLayouts();

    // or by specifying the connection to use
    DB::connection('filemaker')->listDatabases();
    ..

## Executing Filemaker Scripts

It is possible to easily execute filemaker scripts residing on your filemaker server. Use the following example code in your controller:

    // somewhere at the top of your PHP file
    use FMLaravel\Script\Script;
    use FMLaravel\Database\RecordExtractor;
    
    // Create an instance of the record extractor to use. This object postprocesses any Filemaker results to bring them into a nice format.
    // In fact this is optional, if you do not want to use it the script execution step will return the raw filemaker result.
    // RecordExtractor needs the filemaker meta key to use.
    $recordExtractor = new RecordExtractor('myFileMakerMetaKey');
    // In case you want results conforming to a specific FMLaravel Model, please use this way of instantiating the extractor.
    $recordExtractor = RecordExtractor::forModel(Task::class);
    
    // Optionally define a specific parameter formatter.
    // By default parameter lists are joined with a newline character inbetween such as to create a value list for the filemaker script.
    $paramPreprocessor = function($params){
        if (is_array($params)) {
            // do your custom processing here
        }
        return $params;
    };
    
    // Note that both arguments are optional
    $script = new Script($recordExtractor, $paraPreprocessor);
    
    // optionally set the connection to use
    // if not set an instance of the default DB connection will be used (which hopefully is of the correct class)
    $script->setConnection('filemaker');
    $script->setConnection(DB::connection('filemaker'));
    
    // Execute script on filemaker server
    // Will throw an FileMakerException if an error occurs
    $result = $script->execute('myLayout', 'myScript', $myParams);
    
If no error occurs all scripts return raw record result sets (in particular no model instances). How to deal with the data is upto you.

Note to filemaker developers: that if the result set is empty, no exception is thrown, but just an empty result set is returned. 
    
    



## Models

### Basic Usage

Laravel includes a command line tool called artisan that you can use for many helpful tasks, including generating files to avoid typing repetative boilerplate code.

You will want to have one model class per table that you are using in your project.  Laravel uses a convention where it uses singular model names.  To create a model for a tasks table run the following command in terminal:

	php artisan make:model Task

The file that was generated for you is located at app/Task.php.  This class extends Laravel's Eloquent Model class but we need it to extend the FMLaravel Model class instead.  Delete the following line from the newly created Task.php file:

	use Illuminate\Database\Eloquent\Model;

Then add the following line in its place:

	use FMLaravel\Database\Model;

In your Model classes you will need to specify the layout that should be used when querying the tasks table in your FileMaker database.  In order to do this add the following line inside the Task class:

	protected $layoutName = 'YourTaskLayoutName';

By default Laravel will assume the primary key of your table is "id".  If you have a different primary key you will need to add the following inside your class:

	protected $primaryKey = 'YourTaskPrimaryKey';



#### Querying a table

In a file where you will query your FileMaker tasks data add the following at the top of the file:

	use App\Task;

Now that you have imported your Task model you can run the following types of queries against your tasks table:

Find all records

	$tasks = Task::all();

Find a record by its primary key

	$task = Task::find(3); //will find the task record with a primary key of 3

Find a task that matches your find criteria.  You can either pass in two parameters where the first is the field name and the second is the value to match on or you can pass in an array of field names and values.

	//will find tasks where the task_name is 'Go to the store'
	$tasks = Task::where('task_name', 'Go to the store')->get();

	//will find tasks where task_name is 'Go to the store' and priority is 1
	$tasks = Task::where([
		'task_name' => 'Go to the store',
		'priority'  => 1
	])->get();

If you want to limit your query to the first record that matches your criteria you can use first() instead of get()

	$tasks = Task::where('task_name', 'Go to the store')->first();

If you want to specify a number of records to limit your query by you can use the limit() method.

	//will find the first 10 records that match the find criteria
	$tasks = Task::where('task_name', 'Go to the store')->limit(10)->get();

You can also specify a number of records to skip with the skip() method.

	//will find records that match the find criteria after skipping the first 10
	$tasks = Task::where('task_name', 'Go to the store')->skip(10)->get();

These query methods can be chained so you can do something like the following:

	//will find 10 records that match the find criteria after skipping the first 100
	$tasks = Task::where('task_name', 'Go to the store')->skip(100)->limit(10)->get();

If you are using both skip() and limit() in the same query and would rather combine them into one method you can also use the following:

	//will find 10 records that match the find criteria after skipping the first 100
	$tasks = Task::where('task_name', 'Go to the store')->setRange(100, 10)->get();

By default the layout you set on the $layoutName property of your model will be used to query your data.  However, if you need to specify a different layout for a specific query you may use the setLayout() method.

	//will use the PastDue layout to perform the query
	$tasks = Task::where('task_name', 'Go to the store')->setLayout('PastDue')->get();

##### Repitition fields

Repetition fields are returned as numerically indexed arrays if and only if there is more than one field repetition is given on the model's layout. To properly use repetition fields you will have to define all those fields on your model, as follows:

    // list your repetition fields in this array
    protected $repetitionFields = ['categories'];

Values of repetition fields are numerically indexed arrays. Imagine an example where each task has repetition fields for categories:

    $task = Task::first();
    dd($task->categories);

would output:

    array:5 [
      0 => "Urgent"
      1 => "Support"
      2 => ""
      3 => ""
      4 => ""
    ]

And if you want to set any repetitions you have the following options:

    // set a specific repetition
    $task->setAttribute('categories', 'Not urgent anymore', 0);

    // set a specific repetition
    $task->categories = [0 => 'Not urgent anymore'];

Please note that in the second case only the repetitions are overwritten which are specified, any other repetitions remain unchanged.


##### Query operators

The default query operator is the FileMaker field match operator `==`.
To use a different please specify it as follows:

    $tasks = Task::where('priority', '>', 5)->get();

The possible operators are as follows with the semantics as defined by FileMaker:

    '=', '==', '<', '>', '<=', '>=', '<>', '!','~', '""', '*""', 'like'

All search arguments passed to any of these operators - with exception of `like` - are automatically escaped with regard to FileMaker's special search strings (#, @, *, \, \, //, "").
In case you want to supply a custom/raw search using any of those operators you can use the `like` query operator which requires you to escape any search strings yourself. You would be doing this as follows:

    use FMLaravel\Database\Helpers;

    $tasks = Task::where('task_name', 'like', Helpers::escape($search) . '-@#')->get();


##### Related records/models

It is also possible to load any related records as displayed on the model layout used to retrieve the model (ex. through portals). Extend your models as exemplified in the following:

    //
    protected $relatedRecordsInfo = [
        'assignees' => [
            'table' => 'FileMakerTableReference', // as set in your FM database's Relationship view
            'class' => Assignees::class     // model class used to represent the related records
        ],
        'project' => [
            'table' => 'task_projects',
            'class' => Projects::class
         ]
    ];


    public function assignees()
    {
        return $this->relatedRecords('assignees', 'many'); // the to many relationship is the default
    }

    public function project()
    {
        return $this->relatedRecords('project', 'one'); // a to-one relationship
    }

You can retrieve related records as you normally would, also eager loading is possible:

    // eager loading of assignees
    $task = Task::with('assignees')->first();

    // eager loading of assignees and project
    $task = Task::with(['assignees', 'project'])->first();

    // Lazy
    $task = Task::first();

    // Accessing related models
    do($task->assignees);
    do($task->project);

Please be aware that FileMaker result sets automatically contain related record sets which leads to two different behaviours to retrieve related records:
if the related records are loaded eagerly, they are also extracted from the original model query without the need for an additional query to the server; on the other hand, if the related records are loaded lazily, a new request to the server is necessary.

_NOTE_ that any save operations on related records are done using the configuration as given in the model class (ie using its layout and primary key).

_SECOND NOTE_ related record set operations (adding, removing records) is currently *not* supported (FileMaker operations, add/delete portal row). This is a feature for the future.

#### Inserting, updating and deleting models

The basic model creation, update and delete methods are also supported. So you can run any of the following commands as usual:

    $task = new Task();
    $task->myField = "Sheherazade";
    $task->save(); // creates a new record in FileMaker DB

    $task->myField = "I changed my mind";
    $task->save(); // updates existing (in this case previously created) record in FileMaker DB

    $task->delete(); // deletes record from FileMaker DB


### Extended Usage
	
#### FileMaker Meta

For internal processing filemaker keeps certain data (like an internal record id) which is required for write operations. By default any such meta-data is stored in an additional attribute '\__FileMaker__'. Edit on your own risk. In case you do require this attribute for your own processes you can easily rename the name by setting the key in your model:

	protected $fileMakerMetaKey = "__FileMaker_No_Collisions_Expected_Now_HAHAHAHA__";

Available meta data (once a request to the filemaker server has been made) is the record id and the current modification id.
The data can be accessed on your model instances as follows:

    $meta = $task->getFileMakerMetaData();

    $meta->recordId ..
    $meta->modificationId ..

    // or

    $task->getFileMakerMetaData('recordId')
    $task->getFileMakerMetaData('modificationId')

	
#### Timestamps
	
By default the regular timestamp fields 'updated_at' etc as generated by Eloquent are disabled for all FMLaravel models. To turn these on enable them by setting the appropriate option in your model:

	public $timestamps = true;
	
Please note that by enabling timestamp, you must provide the according fields in your filemaker table/layout.

	
#### Container fields

Container fields do not contain data directly, but references which can come in two forms (also see [this FM help article](http://help.filemaker.com/app/answers/detail/a_id/5812/~/about-publishing-the-contents-of-container-fields-on-the-web)).
To access container field data you can either make a call to the FileMaker API or have your model handle this for you, whereas the reference typically contains the filename from which the type could be guessed.
 
**Example using API call:**

    // retrieve your model as you normally would
    $task = Task::find(124);
    
    // make API call on container field
    // NOTE this assumed you've set up filemaker as your default database driver
    $containerData = DB::connection('filemaker')->filemaker('read')->($task->myContainerField);
    
    // example route response
    return response($containerData)->header('Content-Type:','image/png');
    

**Example using implicit model functionality:**

Extend your model as follows:

    /***** required *****/

    // list all your container fields in this array
    protected $containerFields = ['myContainerField'];


    /***** optional *****/

    // When accessing container fields they are automatically mutated and in case you want the container data to be loaded
    // automatically aswell set this to true.
    // NOTE that retrieving container data from the server is a seperate query to the server.
    // Default: false
    protected $containerFieldsAutoload = true;

In your controller:

    // retrieve your model as you normally would
    $task = Task::find(124);
    
    // original field is automatically mutated to an instance of class \FMLaravel\Database\ContainerField
    $myContainerField = $task->myContainerField;

    // checker wether it is empty
    if ($myContainerField->isEmpty()) {
        // ...
    }
    
    // now you can access the following attributes
    $myContainerField->key == 'myContainerField'; // original attribute name
    $myContainerField->filename == 'myImageFile.png';
    $myContainerField->data == you-binary-image-data // NOTE if you have specified to NOT autoload container data a request to the server will be triggered before it is returned.
    $myContainerField->url == '/fmi/xml/cnt/myImageFile.png? etc etc'; // original attribute value as returned from the
    
    // example route response
    return response($myContainerField->data)->header('Content-Type:',$myContainerField->mimeType);




##### Caching of (filemaker server) container field data

Additionally you can enable caching of container field data that is retrieved from any filemaker server. Extend your model with the following options:


    /***** required *****/

    // file cache time in minutes
    protected $containerFieldsCacheTime = 1;


    /***** optional *****/

    // set which laravel cache store is to be used
    // Default: default system cache store
    // Note: can also be configured in your database.php setup (see above)
    protected $containerFieldsCacheStore = 'file';

    // Override the default cache key format to use for container fields
    // Five placeholders are available to be overridden automatically for each container field
    // (each placeholder begins with a colon; for the available placeholders see this example)
    // Default: ':url'
    protected $containerFieldsCacheKeyFormat = ':field :filename :url :recordId :modificationId';

_Note_ that filemaker provided URLs contain the field, filename and record id, but no (record) modification id. Which means, to avoid stale cache any changes to container field data must result in a change of any of these values.



##### Setting and uploading container field data to filemaker servers

If your model is using the automatic ContainerField functionalities, you can also update your container fields.

By default the Filemaker PHP API does not provide a direct way of putting files into container fields - you can write directly into them, yes, but the data will be considered text.

Typical solutions rely on either additional fields, filemaker scripts or bots. So there is not a default solution but FMLaravel provides some tools for approaching this.

Once you try to save a container field with new data you will encounter an exception which notifies you that the model's `updateContainerFields` method has not been implemented. In fact, overriding this method would be your general point of entry, in your model, as follows:

    /** Method called by container field update mechanism
     * called on model saves (inserts & updates)
     * @param array $values key-value list of dirty (ie changed ContainerFields)
     * @throws Exception
     * @see FMLaravel\Database\QueryBuilder
     */
    public function updateContainerFields(array $values)
    {
        // do whatever you want
        foreach ($values as $key => $val) {
            // note that $val will be of type ContainerField
        }
    }


In case your Filemaker Server has version 13.0 or above (or you have a plugin installed that allows writing base64 data into container fields as files) you can use the solution as shown in the following.

First extend your model as follows:

    // at the top of your PHP file
    use FMLaravel\Database\ContainerField\RunBase64UploaderScriptOnSave;

    // in your class model
    use RunBase64UploaderScriptOnSave;


    /***** optional *****/

    // Set the script name
    // Default (if not defined): "PHPAPI_YourModelName_RunBase64UploaderScriptOnSave"
    protected $containerFieldUploaderScriptName = "PHPAPI_Task_RunBase64UploaderScriptOnSave";

    // Override the layout to use
    // Default: the model layout defined previously (see above)
    protected $containerFieldUploaderScriptLayout = "myLayoutToUseForTheScript";

With this each save operation will trigger an automatic script execution of said script on said layout with the following script parameters (as available through `Get(ScriptParameter)`):

    <YourModelName>
    <Primary key of model/record>
    <number of container fields being updated>
    <list of container data>

Where for each updated container field the following data is given in the <list of container data> placeholder:

    <name of field>
    <filename>
    <filedata base64 encoded>

So for our example Task model this might look as follows:

    Task
    3
    1
    myContainerField
    myNewImage.png
    iVBORw0KGgoAAAANSUhEUgAAAHkAAABACAYAAAA+j9gs ... some long long sequence of characters ... AAAElFTkSuQmCC

On the filemaker server side you will naturally need a script to handle the incoming data. A basic sample implementation is shown in the image below.

![Example PHPAPI_Task_RunBase64UploaderScriptOnSave script](https://github.com/tschiemer/FMLaravel/raw/master/doc/PHPAPI_Task_RunBase64UploaderScriptOnSave.png "Example PHPAPI_Task_RunBase64UploaderScriptOnSave script")


Finally in your controller you have several options for setting container data:

    ///// Setting the attribute itself
    // empty the container field
    $task->myContainerField = null;

    // set a file as uploaded from any html form
    $task->myContainerField = Request::file('myFileUploadField');
    // or possibly
    $task->myContainerField = $request->file('myFileUploadField');

    // set a file you have instantiated differently and that fullfills the SplFileInfo interface
    $task->myContainerField = new Symfony\Component\HttpFoundation\File\File('myImageFile.png');


    ///// Calling method on the ContainerField object

    // optional filename (if not given will be deduced from realpath that might be messed up in case of temporary files)
    $task->myContainerField->setFromRealpath($realpath, $filename);

    // in case you are computing any data, use this
    $task->myContainerField->setWithData($filename, $data);

    // you have a file on laravel filesystem disk that you want to use?
    // Passing the disk is optional, by default the default storage will be used.
    $task->myContainerField->setFromStorage($filename, $disk);




## User authentication through Filemaker

_Todo_ Write documentation
