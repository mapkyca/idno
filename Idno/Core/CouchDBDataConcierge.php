<?php

/**
 * CouchDB Data management class.
 * 
 * 
 * @package idno
 * @subpackage core
 */

namespace Idno\Core {

    class CouchDBDataConcierge extends \Idno\Core\DataConcierge
    {

        public function __construct($dbname = 'idno', $hostURL = 'http://localhost:5984/') {
            
            /* TODO: Make configurable by config */
         
            $this->dbname = trim($dbname, ' /');
            $this->hostURL = trim($hostURL, ' /') . '/';
            
            $this->newDatabase($this->dbname);
            
            $this->initMaps(); 
        }
        
        
        function init()
        {
        }
        
        /**
         * Initialise the various query maps necessary.
         */
        protected function initMaps() {
            $uuid = '_design/entities';
            
            // See if we have a map function - map
            if (!$map = $this->retrieve($uuid)) {
            
                // Now we need to create view
                $view = new \stdClass();
                $view->views = new \stdClass();
                $view->views->in_collection = new \stdClass();
                
                $view->views->in_collection->map = "function (doc) {
                        if (doc.collection) {
                            emit(doc.collection, doc);
                        }
                    }
                ";
                
                $view->views->in_collection->reduce = "_count";
                
                $this->store($uuid, $view);
                
            }
            
        }
        
        
        /**
         * Create a new database, will throw a StorageException if there's an error
         * @param type $db
         * @return boolean
         * @throws StorageException
         */
        protected function newDatabase($db) {
            
            // See if the database exists
            $dbs = $this->query(COUCHDB_METHOD_GET, '', null, '', '_all_dbs'); 
            if (in_array($this->dbname, $dbs))
                    return true;
            
            // Doesn't so create
            $result = $this->query(COUCHDB_METHOD_PUT); // Put a new database
            if (isset($result->error))
                throw new \Exception($result->reason);
            return true;
        }

        /**
         * Delete a UUID
         * @param type $uuid The UUID to get
         * @param array $params Optional parameters
         * @return mixed The revision ID, or false
         */
        protected function delete($uuid, array $params = null) {
            $result = $this->query(COUCHDB_METHOD_PUT, $uuid, $params, $data);
            if (!isset($result->error))
                return $result->rev;
            
            return false;
        }
        
        /**
         * Retrieve a UUID
         * @param type $uuid The UUID to get
         * @param array $params Optional parameters
         * @return mixed The revision ID, or false
         */
        protected function retrieve($uuid, array $params = null) {
            $result = $this->query(COUCHDB_METHOD_GET, $uuid, $params);
     
            if (!$result->error)
                return $result;
            
            return false;
        }

        /**
         * Store some data
         * @param type $uuid
         * @param type $data
         * @return mixed The revision ID, or false
         */
        protected function store($uuid, $data, array $params = null) {
            $result = $this->query(COUCHDB_METHOD_PUT, $uuid, $params, $data);
            
            if (isset($result->ok))
                return $result->rev;
            
            return false;
        }
        
        /**
         * Execute a query against the couchdb backend.
         * @global type $version
         * @param type $method
         * @param type $uuid
         * @param array $parameters
         * @param type $payload
         * @param type $db
         * @return type
         * @throws StorageException
         */
        protected function query($method = COUCHDB_METHOD_GET, $uuid = '', array $parameters = null, $payload = '', $db = '') {
            
            global $version;
            
            // If we're referencing a UUID, make sure the URL will be correct
            if ($uuid)
                $uuid = "/$uuid";
            
            // Allow for DB override
            if (!$db)
                $db = $this->dbname;
            
            //Log::debug("Sending $method query to $db");
            
            // Build Params
            $params = null;
            if ($parameters) {
                $params = array();
                foreach ($parameters as $key => $value)
                    $params[] = urlencode ($key) . '=' . urlencode($value);
                
            }
            
            // Curl installed
            if (!function_exists('curl_init')){
                throw new \Exception('Couch DB connector requires CURL');
            }
            
            // Headers
            $http_headers = array(
                'Content-Type: application/json'
            );
            
            // Initialise connection
            $url = "{$this->hostURL}{$db}{$uuid}";
            if (isset($params))
                $url .= '?' .implode('&', $params);
            $ch = curl_init($url);
            
            // Set basic options
            $options = array(
                CURLOPT_USERAGENT => "Home.API-$version",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => 'utf-8'
            );
            
            // Decide method
            switch ($method) {
                case COUCHDB_METHOD_COPY:
                    $options[CURLOPT_CUSTOMREQUEST] = 'COPY';
                    $http_headers[] = "Destination: " . json_encode($payload);
                    break;
                case COUCHDB_METHOD_DELETE:
                    $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                    break;
                case COUCHDB_METHOD_PUT:
                   // $options[CURLOPT_PUT] = true;
                    $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                    
                    if ($payload) {
                        $payload = json_encode($payload);
                        //Log::debug ("Sending " . strlen ($payload) . " bytes of payload data.");
                        //Log::debug("Payload: " . $payload);
                        $options[CURLOPT_POSTFIELDS] = $payload;
                        
                        $http_headers[] = "Content-Length: " . strlen($payload);
                    }
                    break;
                case COUCHDB_METHOD_POST:
                    $options[CURLOPT_POST] = true;
                    if ($payload) {
                        $payload = json_encode($payload);
                        //Log::debug ("Sending " . strlen ($payload) . " bytes of payload data.");
                        //Log::debug("Payload: " . $payload);
                        $options[CURLOPT_POSTFIELDS] = $payload;
                        
                        $http_headers[] = "Content-Length: " . strlen($payload);
                    }
                    break;
                case COUCHDB_METHOD_GET :
                default:
            }
            
            $options[CURLOPT_HTTPHEADER] = $http_headers;
            
            curl_setopt_array($ch, $options);
            
            $result = curl_exec($ch);
            
            curl_close($ch);
            
            if ($result === false)
                throw new \Exception('CouchDB query returned no results');
                
            $result = json_decode($result);
            
            if ($result === NULL)
                throw new \Exception('CouchDB: Data returned was not JSON');
            
            //Log::debug("Query returned by " . print_r($result, true));
            
            return $result;
        }
        
        
        protected static function getUUID() {
            
            return uniqid(\Idno\Core\site()->config->host.'_',true);
        }
        
        /**
         * Saves a record to the specified database collection
         *
         * @param string $collection
         * @param array $array
         * @return MongoID | false
         */

        function saveRecord($collection, $array)
        { 
            $uuid = CouchDBDataConcierge::getUUID();
            $record = new \stdClass();
            
            $record->collection = $collection;
            $record->payload = $array;
            
            if ($this->store($uuid, $record))
                    return $uuid;
            
            return false;
            
            /*$collection_obj = $this->database->selectCollection($collection);
            if ($result = $collection_obj->save($array,array('w' => 1))) {
                if ($result['ok'] == 1) {
                    return $array['_id'];
                }
            }
            return false;*/
        }

        /**
         * Remove an entity from the database
         * @param string $id
         * @return true|false
         */
        function deleteRecord($id) { return $this->delete($id); }
        
        
        
        
        
        
        
        
        
        
        
        
        
        function rowToEntity($row)
        {
            return parent::rowToEntity((array)$row->value->payload);
        }

        /**
         * Retrieves a record from the database by ID
         *
         * @param string $id
         * @param string $entities The collection name to retrieve from (default: 'entities')
         * @return array
         */

        function getRecord($id, $collection = 'entities') { // Extract id from domain
            
            $obj = $this->retrieve($id); 
            if ($obj)
                return (array)$obj->value->payload;
            return false;
            
        }

        /**
         * Retrieves ANY record from a collection
         *
         * @param string $collection
         * @return mixed
         */
        function getAnyRecord($collection = 'entities') {
            //return $tihs->getRecord($collection); //TODO : Replace with findone equiv
        }

        /**
         * Retrieves a record from the database by its UUID
         *
         * @param string $id
         * @param string $collection The collection to retrieve from (default: entities)
         * @return array
         */

        function getRecordByUUID($uuid, $collection = 'entities') { 
            
            // Get ID from url
        }

        /**
         * Retrieves a set of records from the database with given parameters, in
         * reverse chronological order
         *
         * @param array $parameters Query parameters in MongoDB format
         * @param int $limit Maximum number of records to return
         * @param int $offset Number of records to skip
         * @param string $collection The collection to interrogate (default: 'entities')
         * @return iterator|false Iterator or false, depending on success
         */

        function getRecords($fields, $parameters, $limit, $offset, $collection = 'entities')
        {
            try {
                if ($result = $this->retrieve('_design/entities/_view/in_collection', array('key' => "\"$collection\"", 'limit' => $limit, 'skip' => $offset, 'descending' => $descending ? 'true' : 'false')))
                {
                    return $result->rows;
                }
            } catch (\Exception $e) {
                return false;
            }
            return false;
        }

        /**
         * Count the number of records that match the given parameters
         * @param array $parameters
         * @param string $collection The collection to interrogate (default: 'entities')
         * @return int
         */
        function countRecords($parameters, $collection = 'entities') {
          //  if ($result = $this->database->$collection->count($parameters)) {
           //     return (int) $result;
            //}
            return 0;
        }


        

        /**
         * Retrieve the filesystem associated with the current db, suitable for saving
         * and retrieving files
         * @return bool|\MongoGridFS
         */
        function getFilesystem() {
        //    if ($grid = new \MongoGridFS($this->database)) {
         //       return $grid;
         //   }
            return false;
        }

    }

    define('COUCHDB_METHOD_GET', 'GET');
    define('COUCHDB_METHOD_PUT', 'PUT');
    define('COUCHDB_METHOD_DELETE', 'DELETE');
    define('COUCHDB_METHOD_POST', 'POST');
    define('COUCHDB_METHOD_COPY', 'COPY'); // NOT IMPLEMENTED YET
}