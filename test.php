<?php 

require 'indextank_client.php';


# REMEMBER TO SET YOUR API KEY
$api_key = NULL;

# helper functions
function fail($msg = "") {
    global $ic;
    
    print "\t" . $msg . "\n";

    try { $ic->delete_index(); } catch (Exception $e) { }# safely ignore
    exit(1);
}



# start testing 
if ($api_key == NULL) {
    fail( "you should set your API KEY before starting" );
}

$ac = new ApiClient($api_key);
$ic = $ac->get_index("php_test");


try { 
        # make sure the index does not exist
        print "making sure there's no index\n";
        if ($ic->exists()) {
            # if I call fail, and it cleans the index up, I'll be coward and silly.
            print "\tcowardly refusing to run tests on an existent index\n";
            exit(1);
        }

        # test creation
        print "creating index\n";
        $ic->create_index();

        # waiting for it to be ready
        print "waiting for index to be ready .. will try at most 10 minutes\n";
        $ok = false;
        for ($i = 0; $i < 600; $i++) {
            if ($ic->has_started()) {
                $ok = true;
                break;
            }

            sleep(1);
        }

        if (!$ok) {
            fail("waited for 10 minutes and the index is not ready .. something is wrong .. I WILL NOT continue.");
        }


        # ok, at this point we have an index
        # check functions
        
        print "listing functions\n";
        $functions = $ic->list_functions();
        if (sizeof((array)$functions) != 1) {
            fail("I expected just 1 function!");
        }

        # create functions
        print "creating a function\n";
        $formula = "d[0] * 2";
        $ic->add_function(1, $formula);
        $functions = $ic->list_functions();
        if (sizeof((array)$functions) != 2) {
            fail("I expected 2 functions!");
        }

        if ($functions->{1} != $formula) {
            fail("function 1 is not what I expected");
        }
   

        # test ADD, search, delete
        $doc1fields = array("text" => "doc1 is the first", "title" => "dont need one");
        $doc1vars   = array(0 => 1.2, 1 => 2.3);

        
        # Add a document
        print "adding a document\n";
        $ic->add_document("doc1", $doc1fields, $doc1vars); # TODO categories
   
        # search for it
        print "searching for it\n";
        $res = $ic->search("first");
        if ($res->matches != 1) {
            fail("I expected 1 match");
        }

        # delete it, and verify it went away
        print "deleting it\n";
        $ic->delete_document("doc1");
        print "checking it went away\n";
        $res = $ic->search("first");
        if ($res->matches != 0) {
            fail("I expected 0 matches");
        }

        # TODO
        # batch-add a couple of documents,  with variables and categories
        # search them, with function 0
        # search them, with function 1
        
        # update variables on documents, swapping  doc0 and doc1 var 0 value
        # search usisg function 1, expect the order swapped

        # update categories on 1 document
        # search documents, see category change

        # add document 2
        # promote it for a query
        # search and check it worked



        # finally
        $ic->delete_index();
        print "done\n";

} catch (Exception $e) { 
    fail($e->getMessage());
}

?>
