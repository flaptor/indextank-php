IndexTank API Client for PHP by Gilles
======================================

Version 2.x:
-------

__WARNING: NOT AUTOMATICALLY BACKWARDS COMPATIBLE.__
This version renames almost all the classes in the library. 

Probably, you should just apply the following changes:

  * Include indextank.php file instead of indextank_client.php
  * Rename class ApiClient to Indextank_Api

But here is the list of classes that changed their names:
  
  * ApiClient   --> Indextank_Api
  * IndexClient --> Indextank_Index
  * ApiResponse --> Indextank_Response

  * All exception classes extends Indextank_Exception
  * InvalidResponseFromServer --> Indextank_Exception_InvalidResponseFromServer
  * TooManyIndexes --> Indextank_Exception_TooManyIndexes
  * IndexAlreadyExists --> Indextank_Exception_IndexAlreadyExists
  * IndexDoesNotExist --> Indextank_Exception_IndexDoesNotExist
  * InvalidQuery --> Indextank_Exception_InvalidQuery
  * InvalidDefinition --> Indextank_Exception_InvalidDefinition
  * Unauthorized --> Indextank_Exception_Unauthorized
  * InvalidUrl --> Indextank_Exception_InvalidUrl
  * HttpException --> Indextank_Exception_HttpException

Documentation:
-------
[http://indextank.com/documentation/php-client](See the updated documentation on this client)
