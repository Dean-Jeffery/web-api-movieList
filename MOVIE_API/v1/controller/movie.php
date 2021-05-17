<?php

require_once('db.php');
require_once('../model/Movie.php');
require_once('../model/Response.php');

try {
  $writeDB = DB::connectWriteDB();
  $readDB = DB::connectReadDB();
}
catch(PDOException $exception) {
  error_log("Data Connection Error - ".$exception, 0);
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage("Database Connection Failed");
  $response->send();
  exit;
}

if(array_key_exists("movieid", $_GET)) {
  $movieid = $_GET['movieid'];

  if($movieid == '' || !is_numeric($movieid)) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Movie ID: Cannot be null and must be numeric");
    $response->send();
    exit;
  }

  if($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
      $query = $readDB->prepare('select id, name, description, rating from tbl_movies where id = :movieid');
      $query->bindParam(':movieid', $movieid, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();
      $movieArray = array();

      if($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Movie ID Not Found");
        $response->send();
        exit;
      }
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $movie = new Movie($row['id'], $row['name'], $row['description'], $row['rating']);
        $movieArray[] = $movie->getMovieAsArray();
      }

      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['movies'] = $movieArray;

      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->toCache(true);
      $response->setData($returnData);
      $response->send();
      exit;
    }
    catch(MovieException $exception) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage($exception->getMessage());
      $response->send();
      exit;
    }
    catch(PDOException $exception) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to Retrieve Movie");
      $response->send();
      exit;
    }
  }
// DELETE RECORDS
  elseif($_SERVER['REQUEST_METHOD'] === 'DELETE')  {
    try {
      $query = $writeDB->prepare('delete from tbl_movies where id = :movieid');
      $query->bindParam(':movieid', $movieid, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();

      if($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Error: Movie not found!");
        $response->send();
        exit();
      }

      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->addMessage("Movie Deleted Successfully");
      $response->send();
      exit();
    }
    catch(PDOException $exception) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to Delete Movie");
      $response->send();
      exit();
    }
  }
// UPDATE RECORDS
  elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    
    try {
      if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("ERROR: invalid content type header");
        $response->send();
        exit();
      }

      $rawPATCHData = file_get_contents('php://input');

      if(!$jsonData = json_decode($rawPATCHData)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("ERROR: Request body is not valid json");
        $response->send();
        exit();
      }

      $nameUpdated = false;
      $descriptionUpdated = false;
      $ratingUpdated = false;

      $queryFields = "";

      if(isset($jsonData->name)) {
        $nameUpdated = true;
        $queryFields .= "Name = :name, ";
      }
      if(isset($jsonData->description)) {
        $descriptionUpdated = true;
        $queryFields .= "Description = :description, ";
      }
      if(isset($jsonData->rating)) {
        $ratingUpdated = true;
        $queryFields .= "Rating = :rating, ";
      }

      $queryFields = rtrim($queryFields, ", ");

      if($queryFields === "") {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("ERROR: No Data Provided");
        $response->send();
        exit();
      }

      $movieid = $_GET["movieid"];
      $query = $readDB->prepare('select id, name, description, rating from tbl_movies where id = :movieid');
      $query->bindParam(':movieid', $movieid, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();

      if($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("ERROR: Missing rows");
        $response->send();
        exit();
      }

      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $movie = new Movie($row['id'], $row['name'], $row['description'], $row['rating']);
      }

      $updateQueryString = "update tbl_movies set ".$queryFields." where id = :movieid";
      $updateQuery = $writeDB->prepare($updateQueryString);

      if($nameUpdated === true) {
        $movie->setName($jsonData->name);
        $updatedName = $movie->getName();
        $updateQuery->bindParam(':name', $updatedName, PDO::PARAM_STR);
      }
      if($descriptionUpdated === true) {
        $movie->setDescription($jsonData->description);
        $updatedDescription = $movie->getDescription();
        $updateQuery->bindParam(':description', $updatedDescription, PDO::PARAM_STR);
      }
      if($ratingUpdated === true) {
        $movie->setRating($jsonData->rating);
        $updatedRating = $movie->getRating();
        $updateQuery->bindParam(':rating', $updatedRating, PDO::PARAM_STR);
      }

      $updateQuery->bindParam(':movieid', $movieid, PDO::PARAM_STR);
      $updateQuery->execute();

      $rowCount = $updateQuery->rowCount();
      $movieArray = array();

      if($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("ERROR: Movie was unable to update");
        $response->send();
        exit();
      }

      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $movie = new Movie($row['id'], $row['name'], $row['description'], $row['rating']);
        $movieArray[] = $movie->getMovieAsArray();
      }

      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['movies'] = $movieArray;

      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->toCache(true);
      $response->addMessage($returnData);
      $response->send();
      exit();


    }
    
    catch(MovieException $exception) {
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      $response->addMessage($exception->getMessage());
      $response->send();
      exit();
    }
    catch(PDOException $exception) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("failed to update the movie");
      $response->send();
      exit();      
    }



  }
  else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Invalid Request Method");
    $response->send();
    exit();
  }
}

elseif(array_key_exists("rating", $_GET)) {
  $rating = $_GET['rating'];
  if($rating == '' || !is_numeric($rating)) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Error: rating must be numeric");
    $response->send();
    exit();
  }
//retrieve db entry
  if($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
      $query = $readDB->prepare('select id, name, description, rating from tbl_movies where rating = :rating');
      $query->bindParam(':rating', $rating, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();
      $movieArray = array();

      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $movie = new Movie($row['id'], $row['name'], $row['description'], $row['rating']);
        $movieArray[] = $movie->getMovieAsArray();
      }

      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['movies'] = $movieArray;

      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->toCache(true);
      $response->setData($returnData);
      $response->send();
      exit();
    }
    catch(MovieException $exception) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage($exception->getMessage());
      $response->send();
      exit();
    }
    catch(PDOException $exception) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Error: failed to get movies");
      $response->send();
      exit();
    }
  }
  else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit();
  }
}
elseif(empty($_GET)) {
// insert entry into database
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
      if($_SERVER['CONTENT_TYPE'] !== 'application/json'){
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Error: invalid content type error");
        $response->send();
        exit();
      }
      $rawPOSTData = file_get_contents('php://input');

      if(!$jsonData = json_decode($rawPOSTData)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Error: request body is not valid json");
        $response->send();
        exit();
      }

      if(!isset($jsonData->name)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (!isset($jsonData->name) ? $response->addMessage("Error: name is required") : false);
        $response->send();
        exit();
      }

      $newMovie = new Movie(null, (isset($jsonData->name) ? $jsonData->name : null), (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->rating) ? $jsonData->rating : null));
                                  
      $name = $newMovie->getName();
      $description = $newMovie->getDescription();
      $rating = $newMovie->getRating();

      $query = $writeDB->prepare('insert into tbl_movies (name, description, rating) values (:name, :description, :rating)');

      $query->bindParam(':name', $name, PDO::PARAM_STR);
      $query->bindParam(':description', $description, PDO::PARAM_STR);
      $query->bindParam(':rating', $rating, PDO::PARAM_STR);


      $query->execute();

      $rowCount = $query->rowCount();

      if($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("failed to insert movie into the database");
        $response->send();
        exit();
      }

      $lastMovieID = $writeDB->lastInsertId();

      $query = $readDB->prepare('select id, name, description, rating from tbl_movies where id = :movieid');
      $query->bindParam(':movieid', $lastMovieID, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();
      $movieArray = array();

      if($rowCount === 0) {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Movie ID Not Found");
        $response->send();
        exit;
      }
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $movie = new Movie($row['id'], $row['name'], $row['description'], $row['rating']);
        $movieArray[] = $movie->getMovieAsArray();
      }

      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['movies'] = $movieArray;

      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->toCache(true);
      $response->setData($returnData);
      $response->send();
      exit;
    }

    catch(MovieException $exception) {
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      $response->addMessage($exception->getMessage());
      $response->send();
      exit();
    }
    catch(PDOException $exception) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Error: failed to insert movie into database ");
      $response->send();
      exit();
    }
  }

  else{
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Error: invalid endpoint");
    $response->send();
    exit();
  }
}
?>
