<?php

class MovieException extends Exception { }

class Movie {
  private $_id;
  private $_name;
  private $_description;
  private $_rating;

  public function __construct($id, $name, $description, $rating) {
    $this->setId($id);
    $this->setName($name);
    $this->setDescription($description);
    $this->setRating($rating);
  }

  public function getId() {
    return $this->_id;
  }

  public function getName() {
    return $this->_name;
  }

  public function getDescription() {
    return $this->_description;
  }

  public function getRating() {
    return $this->_rating;
  }

  public function setId($id) {
    if (($id !== null) && (!is_numeric($id) || $this->_id !== null)) {
      throw new MovieException("Error: Movie ID Issue");
    }
    $this->_id = $id;
  }

  public function setName($name) {
    if (strlen($name) <= 0 || strlen($name) >= 255) {
      throw new MovieException("Error: Name Issue");
    }
    $this->_name = $name;
  }

  public function setDescription($description) {
    $this->_description = $description;
  }

  public function setRating($rating) {
      $this->_rating = $rating;
  }

  public function getMovieAsArray() {
    $movie = array();
    $movie['id'] = $this->getId();
    $movie['name'] = $this->getName();
    $movie['description'] = $this->getDescription();
    $movie['rating'] = $this->getRating();
    return $movie;
  }

}

?>
