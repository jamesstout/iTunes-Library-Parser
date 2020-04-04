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
          if ($tmpKey == "Track_ID") {
            $tracks[$tmpValue]["Name"] = null;
            $tracks[$tmpValue]["Location"] = null;
            $tracks[$tmpValue]["Artist"] = null;
            $tracks[$tmpValue]["Album"] = null;
            $tracks[$tmpValue]["Total_Time"] = null;
          }
          else {
            $playlist->{ $tmpKey } = $tmpValue;
          }
        }
        
        $playlist->{ "tracks" } = $tracks;
        
        $this->addPlaylist($playlist);
      }
      
      // add Name and Location, plus Artist, Album, time (for matching tracks with the same name) to playlist tracks
      foreach ($this->_playlists as $playlist) {
        
        
        // skip empty and large playlists (large count could be a variable I guess)
        if (count($playlist->tracks)> 0 && count($playlist->tracks) < $maxPlaylistTrackCount) {

          $msg = "[" . $playlist->Name . "] - Track count: " . count($playlist->tracks) . "\n";
          printf("\033[32m%s\033[0m", $msg);

          foreach ($playlist->tracks as $trackID => $trackValues) {
            list($name, $location, $artist, $albumn, $totalTime) = $this->getNameAndLocationEtc($trackID);
            $playlist->tracks[$trackID]["Name"] = $name;
            $playlist->tracks[$trackID]["Location"] = $location;
            $playlist->tracks[$trackID]["Artist"] = $artist;
            $playlist->tracks[$trackID]["Album"] = $albumn;
            $playlist->tracks[$trackID]["Total_Time"] = $totalTime;
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
  private function getNameAndLocationEtc( $trackID ){ 
    foreach ($this->_tracks as $track ) {
      if($track->Track_ID == $trackID){
        // in tests Name, Location and Total_Time were always present
        return array(isset($track->Name)        ? $track->Name :      null, 
                      isset($track->Location)   ? $track->Location :  null, 
                      isset($track->Artist)     ? $track->Artist :    null,
                      isset($track->Album)      ? $track->Album :     null, 
                      isset($track->Total_Time) ? $track->Total_Time :null
                    );
      }    
    }
    // could exit or log error here...
  } 
  
  /**
  * Get the track ID and location for a specific track name
  * Public as expected to be called to find the new ID 
  * in a new library, using the name from an old library
  *
  * @param string $trackID
  * @param string $trackName
  * @param string $totalTime
  * @param string $artist can be null
  * @param string $album can be null
  *
  * @return array
  */ 
  public function getIDAndLocation($trackID, $trackName, $totalTime, $artist, $album){ 

    // error checks
    if($trackName == null){
      echo "track name null\n";
      return array(null, null);
    }

    $match=0;
    $potentialMatch= array();

    foreach ( $this->_tracks as $track ) {
      // echo "looking for: [$trackName], [$totalTime], [$artist], [$album]\n";

      // match on as much as possible
      if( $track->Name == $trackName && 
        (is_null($totalTime) == false && $track->Total_Time == $totalTime) && 
        (is_null($artist) == false && $track->Artist == $artist) && 
        (is_null($album) == false && $track->Album == $album)
        ){
          return array($track->Track_ID, $track->Location);
      }

      // if no match, construct some potential matches
      $potentialMatch[$trackName]["TotalTime"] = $totalTime;
      $potentialMatch[$trackName]["Artist"] = (is_null($artist) == false ? $artist : "NO ARTIST");
      $potentialMatch[$trackName]["Album"] = (is_null($album) == false ? $album : "NO ALBUM");
      
      // tmp vars
      $tmpArtist = (isset($track->Artist) == true ? $track->Artist : "NO ARTIST");
      $tmpAlbum = (isset($track->Album) == true ? $track->Album : "NO ALBUM");
      
      // if track name, total time and artist match, grab the details
      if ($track->Total_Time == $totalTime && $track->Name == $trackName && is_null($artist) == false && $tmpArtist == $artist) {
 
          $match++;

          $potentialMatch[$trackName]['Match'][$match]["Type"] = "Name_TotalTime";
          $potentialMatch[$trackName]['Match'][$match]["Name"] = $track->Name;
          $potentialMatch[$trackName]['Match'][$match]["Track_ID"] = $track->Track_ID;
          $potentialMatch[$trackName]['Match'][$match]["TotalTime"] = $track->Total_Time;
          $potentialMatch[$trackName]['Match'][$match]["Artist"] = $tmpArtist;
          $potentialMatch[$trackName]['Match'][$match]["Album"] = $tmpAlbum;
          $potentialMatch[$trackName]['Match'][$match]["Location"] = $track->Location;
      }
    }

    if ($match>0) {
      echo "for: [$trackID], [$trackName], [$totalTime], [$artist], [$album]\n";

      $tmpTrackID="";
      $tmpLocation="";
      $tmpAlbum="";
      
      // choose best
      // we know name, time and artist match
      // choose first with an album?
      // then if there is another match without an album
      // use that
      foreach ($potentialMatch as $key => $value) {
        foreach ($value['Match'] as $k => $v) {

          if($v['Album'] != 'NO ALBUM'){
            $tmpTrackID=$v["Track_ID"];
            $tmpLocation=$v["Location"];
            $tmpAlbum=$v["Album"];
            break;
          }

          if($v['Album'] == 'NO ALBUM'){
            $tmpTrackID=$v["Track_ID"];
            $tmpLocation=$v["Location"];
            $tmpAlbum=$v["Album"];
          }
        }
      }

      echo "match is now: [$tmpTrackID], [$tmpAlbum]\n";
      return array($tmpTrackID, $tmpLocation);
    }

    // could exit or log error here...
    echo "could not match: [$trackName], [$totalTime], [$artist], [$album]\n";    
    return array(null, null);
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
