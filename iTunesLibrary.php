<?php

class track {}
class playlist {}
class iTunesLibrary { 
  private $_tracks; 
  private $_playlists; 
  
  /**
  * @param string $path
  * @param bool   $getPlaylists
  * @param int    $maxPlaylistTrackCount
  */
  function __construct( $path, bool $getPlaylists = false, $maxPlaylistTrackCount = null) { 

    if($getPlaylists == true && $maxPlaylistTrackCount == null){
      die("You must set a track limit if you want to get playlists\nE.g.\n\n " . '$oldLibrary = new iTunesLibrary($oldiTunesFile, true, 5000);');
    }
    
    $fileContents = file_get_contents( $path );
    $xml = simplexml_load_string( $fileContents );
    
    foreach ( $xml->dict->dict->dict as $trackObject ) {
      preg_match_all( "/\<key\>(.+)\<\/key\>\<.+\>(.+)\<\/.+\>/", $trackObject->asXML(), $matches );
      $track = new track();
      
      // get track properties
      foreach ( $matches[1] as $key => $value ) {
        $track->{ str_replace( " ", "_", $value ) } = str_replace( "&amp;", "&", str_replace( "\"", "”", strval( $matches[2][$key] ) ) );
      }
      
      // add on Compilation, Purchased, Explicit bools
      preg_match_all( "/\<key\>(.+)\<\/key\>\<(true|false)\/\>/", $trackObject->asXML(), $matches );
      foreach ( $matches[1] as $key => $value ) {
        $track->{ str_replace( " ", "_", $value ) } = $matches[2][$key];
      }
      
      $this->addTrack( $track );
    }
    
    if ($getPlaylists == true) {
      // get playlists
      foreach ($xml->dict->array->dict as $trackObject) {
        
        $playlist = new playlist();
        $tracks = array();
        
        preg_match_all("/\<key\>(.+)\<\/key\>\<.+\>(.+)\<\/.+\>/", $trackObject->asXML(), $matches);
        
        foreach ($matches[1] as $key => $value) {
          $tmpKey = str_replace(" ", "_", $value);
          $tmpValue = str_replace("&amp;", "&", str_replace("\"", "”", strval($matches[2][$key])));
          
          // if the key is a track ID, add to our tracks array 
          // not sure the default values are needed...
          if ($tmpKey == "Track_ID") {
            $tracks[$tmpValue]["Name"] = "";
            $tracks[$tmpValue]["Location"] = "";
          }
          else {
            $playlist->{ $tmpKey } = $tmpValue;
          }
        }
        
        $playlist->{ "tracks" } = $tracks;
        
        $this->addPlaylist($playlist);
      }
      
      // add Name and Location to playlist tracks
      foreach ($this->_playlists as $playlist) {
        $msg = "[" . $playlist->Name . "] - Track count: " . count($playlist->tracks) . "\n";
        printf("\033[32m%s\033[0m", $msg);
        
        // skip empty and large playlists (large count could be a variable I guess)
        if (count($playlist->tracks)> 0 && count($playlist->tracks) < $maxPlaylistTrackCount) {
          foreach ($playlist->tracks as $trackID => $trackValues) {
            list($name, $location) = $this->getNameAndLocation($trackID);
            $playlist->tracks[$trackID]["Name"] = $name;
            $playlist->tracks[$trackID]["Location"] = $location;
          }
        }
      }
    }
  }
  
  /**
  * Get the track name and location for a specific track ID
  *
  * @param string $trackID
  *
  * @return array
  */ 
  private function getNameAndLocation( $trackID ){ 
    foreach ($this->_tracks as $track ) {
      if($track->Track_ID == $trackID){
        return array($track->Name, $track->Location);
      }    
    }
    // could exit or log error here...
  } 
  
  /**
  * Get the track ID and location for a specific track name
  * Public as expected to be called to find the new ID 
  * in a new library, using the name from an old library
  *
  * @param string $trackName
  *
  * @return array
  */ 
  public function getIDAndLocation( $trackName ){ 
    
    foreach ( $this->_tracks as $track ) {
      if($track->Name == $trackName){
        return array($track->Track_ID, $track->Location);
      }
    }
    // could exit or log error here...
  }
  
  public function getTracks() { return $this->_tracks; } 
  private function addTrack( $track ) { $this->_tracks[] = $track; } 
  private function removeTrack( $__k ) { unset( $this->_tracks[$__k] ); }
  public function getCount() { return count( $this->_tracks ); }
  public function getPlaylists() { return $this->_playlists; } 
  public function getPlaylistCount() { return count( $this->_playlists ); }
  private function addPlaylist( $playlist ) { $this->_playlists[] = $playlist; } 
  
  public function getStarRating( $rating ) {
    switch ( $rating ) {
      case 100:
        return "*****";
      case 80:
        return "****";
      case 60:
        return "***";
      case 40:
        return "**";
      case 20:
        return "*";
      default:
        return "";
    }
  }

}
