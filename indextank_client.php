<?php

// sudo apt-get install php5 php5-curl


class InvalidResponseFromServer extends Exception {}
class TooManyIndexes extends Exception {}
class IndexAlreadyExists extends Exception {}
class InvalidQuery extends Exception {}
class InvalidDefinition extends Exception {}
class Unauthorized extends Exception {}
class InvalidUrl extends Exception {}
class HttpException extends Exception {}

function convert_to_map($array_object) {
    $result = new stdClass();
    
    for($i = 0; $i < sizeof($array_object); ++$i) {
        $result->{$i} = $array_object[$i];
    }
    
    return $result;
}

function api_call($method, $url, $params=array()) {
    $splits = parse_url($url);
    if (! empty($splits['scheme'])) { $scheme = $splits['scheme'].'://'; } else { throw new InvalidUrl("[".$url."]"); }
    if (! empty($splits['host'])) { $hostname = $splits['host']; } else { throw new InvalidUrl("[".$url."]"); }
    //if (! empty($splits['user'])) { $username = $splits['user']; } else { throw new Unauthorized("[".$url."]"); }
    //if (! empty($splits['pass'])) { $password = $splits['pass']; } else { throw new Unauthorized("[".$url."]"); }
    if (! empty($splits['path'])) { $path = $splits['path']; } else { $path = ''; }
    if (! empty($splits['query'])) { $query = '?'.$splits['query']; } else { $query = ''; }
    if (! empty($splits['fragment'])) { $fragment = '#'.$splits['fragment']; } else { $fragment = ''; }
    $netloc = $hostname;
    if (! empty($splits["port"])) { $netloc = $netloc . ":" . $splits['port']; }
    // drop the auth from the url
    //$url = $scheme.$netloc.$path.$query.$fragment;
    $args = '';
    $sep = '';
    
    if ($method == "GET") {
        foreach ($params as $key => $val) {
            $args .= $sep.$key.'='.urlencode($val);
            $sep = '&';
        }
        $url .= '?'.$args;
        $args = '';
    } else {
        $args = json_encode($params);
    }

    //print "url: " . $url . ": " . $args . "\n";

    $session = curl_init($url);
    curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method); // Tell curl to use HTTP method of choice
    curl_setopt($session, CURLOPT_POSTFIELDS, $args); // Tell curl that this is the body of the POST
    curl_setopt($session, CURLOPT_HEADER, false); // Tell curl not to return headers
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true); // Tell curl to return the response
    curl_setopt($session, CURLOPT_HTTPHEADER, array('Expect:')); //Fixes the HTTP/1.1 417 Expectation Failed
    $response = curl_exec($session);
    $http_code = curl_getinfo($session,CURLINFO_HTTP_CODE);
    curl_close($session); 

    if (floor($http_code/100) == 2) { 
        return new ApiResponse($http_code,$response);
    }
    throw new HttpException($response, $http_code);
}

class ApiClient {
    /*
     * Basic client for an account.
     * It needs an API url to be constructed.
     * It has methods to manage and access the indexes of the
     * account. The objects returned by these methods implement
     * the IndexClient class.
     */

    private $api_url = NULL;

    function __construct($api_url) {
        $this->api_url = rtrim($api_url,"/");
    }
    
    public function get_index($index_name) {
        return new IndexClient($this->index_url(str_replace('/','',$index_name)));
    }

    public function list_indexes() {
        return json_decode(api_call('GET', $this->indexes_url())->response);
    }

    public function create_index($index_name) {
        $index = $this->get_index($index_name);
        $index->create_index();
        return $index;
    }

    private function indexes_url() {
        return $this->api_url . '/v1/indexes';
    }
    
    

    private function index_url($index_name) {
        return $this->indexes_url() . "/" . urlencode($index_name);
    }

}


class IndexClient {
    /*
     * Client for a specific index.
     * It allows to inspect the status of the index. 
     * It also provides methods for indexing and searching said index.
     */

    private $index_url = NULL;
    private $metadata = NULL;


    function __construct($index_url, $metadata=NULL) {
        $this->index_url = $index_url;
        $this->metadata = $metadata;
    }

    public function exists() {
        /*
         * Returns whether an index for the name of this instance
         * exists, if it doesn't it can be created by calling
         * create_index()
         */
        try {
            $this->refresh_metadata();
            return true;
        } catch (HttpException $e) {
            if ($e->getCode() == 404) {
                return false;
            } else {
                throw $e;
            }
        }
    }
         
    public function has_started() {
        /*
         * Returns whether this index is responsive. Newly created
         * indexes can take a little while to get started. 
         * If this method returns False most methods in this class
         * will raise an HttpException with a status of 503.
         */
        $this->refresh_metadata();
        return $this->metadata->{'started'};
    }

    public function get_code() {
        $this->refresh_metadata();
        return $this->metadata['code'];
    }

    public function get_size() {
        $this->refresh_metadata();
        return $this->metadata['size'];
    }

    public function get_creation_time() {
        $this->refresh_metadata();
        return $this->metadata->{'creation_time'};
    }


    public function create_index() {
        /*
         * Creates this index. 
         * If it already existed a IndexAlreadyExists exception is raised. 
         * If the account has reached the limit a TooManyIndexes exception is raised
         */
        try {
            $res = api_call('PUT', $this->index_url);
            if ($res->status == 204) {
                throw new IndexAlreadyExists('An index for the given name already exists');
            }
        } catch (HttpException $e) {
            if ($e->getCode() == 409) {
                throw new TooManyIndexes($e->getMessage());
            }
            throw $e;
        }
    }

    public function delete_index() {
        api_call('DELETE', $this->index_url);
    }

    public function add_document($docid, $fields, $variables = NULL) {
        /*
         * Indexes a document for the given docid and fields.
         * Arguments:
         *     docid: unique document identifier
         *     field: map with the document fields
         *     variables (optional): map integer -> float with values for variables that can
         *                           later be used in scoring functions during searches.
         */
        $data = array("docid" => $docid, "fields" => $fields);
        if ($variables != NULL) {
            $data["variables"] = convert_to_map($variables);
        }
        api_call('PUT', $this->docs_url(), $data);
    }

    public function delete_document($docid) {
        /*
         * Deletes the given docid from the index if it existed. otherwise, does nothing.
         * Arguments:
         *     docid: unique document identifier
         */
        api_call('DELETE', $this->docs_url(), array("docid" => $docid));
    }

    public function update_variables($docid, $variables) {
        /*
         * Updates the variables of the document for the given docid.
         * Arguments:
         *     docid: unique document identifier
         *     variables: map integer -> float with values for variables that can
         *                later be used in scoring functions during searches.
         */
        api_call('PUT', $this->variables_url(), array("docid" => $docid, "variables" => convert_to_map($variables)));
    }

    public function update_categories($docid, $categories) {
        /*
         * Updates the category values of the document for the given docid.
         * Arguments:
         *     docid: unique document identifier
         *     categories: map string -> string where each key is a category name pointing to its value
         */
        api_call('PUT', $this->categories_url(), array("docid" => $docid, "categories" => convert_to_map($categories)));
    }

    public function promote($docid, $query) {
        /*
         * Makes the given docid the top result of the given query.
         * Arguments:
         *     docid: unique document identifier
         *     query: the query for which to promote the document
         */
        api_call('PUT', $this->promote_url(), array("docid" => $docid, "query" => $query));
    }

    public function add_function($function_index, $definition) {
        try {
            api_call('PUT', $this->function_url($function_index), array("definition" => $definition));
        } catch (HttpException $e) {
            if ($e->getCode() == 400) {
                throw new InvalidDefinition($e->getMessage());
            }
            throw $e;
        }
    }

    public function delete_function($function_index) {
        api_call('DELETE', $this->function_url($function_index));
    }

    public function list_functions() {
        $res = api_call('GET', $this->functions_url());
        return $res->response;
    }

    public function search($query, $start=NULL, $len=NULL, $scoring_function=NULL, $snippet_fields=NULL, $fetch_fields=NULL, $category_filters=NULL) {
        $params = array("q" => $query);
        if ($start != NULL) { $params["start"] = $start; }
        if ($len != NULL) { $params["len"] = $len; }
        if ($scoring_function != NULL) { $params["function"] = (string)$scoring_function; }
        if ($snippet_fields != NULL) { $params["snippet"] = $snippet_fields; }
        if ($fetch_fields != NULL) { $params["fetch"] = $fetch_fields; }
        if ($category_filters != NULL) { $params["category_filters"] = $category_filters; }
        try {
            $res = api_call('GET', $this->search_url(), $params);
            return json_decode($res->response);
        } catch (HttpException $e) {
            if ($e->getCode() == 400) {
                throw new InvalidQuery($e->getMessage());
            }
            throw $e;
        }
    }


    private function get_metadata() {
        if ($this->metadata == NULL) {
            return $this->refresh_metadata();
        }
        return $this->$metadata;
    }

    private function refresh_metadata() {
        $res = api_call('GET', $this->index_url, array());
        $this->metadata = json_decode($res->response);
        return $this->metadata;
    }

    private function docs_url()        { return $this->index_url . "/docs"; }
    private function variables_url()   { return $this->index_url . "/docs/variables"; }
    private function categories_url()   { return $this->index_url . "/docs/categories"; }
    private function promote_url()     { return $this->index_url . "/promote"; }
    private function search_url()      { return $this->index_url . "/search"; }
    private function functions_url()   { return $this->index_url . "/functions"; }
    private function function_url($n)  { return $this->index_url . "/functions/". $n; }



    
}

class ApiResponse {
    public $status = NULL;
    public $response = NULL;
    function __construct($status, $response) {
        $this->status = $status;
        $this->response = $response;
    }
}



