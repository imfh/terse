<?php
require_once('TwitterAPIExchange.php');

/** Set access tokens here - see: https://dev.twitter.com/apps/ **/
$settings = array(
    'oauth_access_token' => "YOUR OAUTH TOKEN",
    'oauth_access_token_secret' => "YOUR SECRET OAUTH TOKEN",
    'consumer_key' => "YOUR CONSUMER KEY",
    'consumer_secret' => "YOUR SECRET CONSUMER KEY"
);

$url = "https://api.twitter.com/1.1/search/tweets.json";
 
$requestMethod = "GET";
 
/** Find the recent tweet in Berkeley, CA within a two mile range (exclude links) **/
$getfield = '?q=-filter%3Alinks&result_type=recent&count=1&geocode=37.870108,-122.295304,2mi';

/**  Find the last tweet about Berkeley, CA while reducing mentions of UC Berkeley (exclude links)
$getfield = '?q=berkeley%20OR%20%23berkeley%20-filter%3Alinks%20-uc%20-bear%20-ucla%20-ucsd%20-davis%20-%22berkeley%20county%22&result_type=recent&count=1';
**/

$twitter = new TwitterAPIExchange($settings);

$string = json_decode($twitter->setGetfield($getfield)
	->buildOauth($url, $requestMethod)
	->performRequest(),$assoc = TRUE);
	

/** Print out results from JSON feed
echo "<pre>";
print_r($tweetLocation);
echo "</pre>";
**/

?>

<!DOCTYPE html>
<html>
  <head>
	<title>terse@berkeley</title>  
	<meta charset="utf-8">
	<meta http-equiv="refresh" content="60">
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<style>
		html { margin:0; padding:0; border:0; font:normal 1em/1.5em 'Armata', 'Helvetica Neue', Helvetica, Arial, sans-serif; color:#333; }
		body { background:#555; }
		html, body, #map-canvas { height: 100%;  margin: 0px; padding: 0px }
		h1 { margin:0 0 10px 0; font:normal 1.5em/1.2em 'Armata', 'Helvetica Neue', Helvetica, Arial, sans-serif; }
		p { font-size:20px; }
		.alert-box { display:block; left:10%; top:20%; width:80%; position:absolute; }
		.tweet { clear:all; position:relative; }
		.user { margin-top:10px; float:left; font-size:1.1em; }
		.user img { height:32px; width:32px; margin:0 0 0 42px; vertical-align:middle; }
		.location { margin-top:10px; float:right; font-size:1.1em; }
		.divider { position: relative; width: 100%; height: 0; padding: 0; border: 2px solid #999; margin-bottom:15px; }
		.divider:before { content: ''; position: absolute; border-style: solid; border-width: 15px 15px 0; border-color: #999 transparent; display: block; width: 0; z-index: 1; bottom: -15px; left: 31px; }
		.divider:after { content: ''; position: absolute; border-style: solid; border-width: 12px 12px 0; border-color: #555 transparent; display: block; width: 0; z-index: 1; bottom: -9px; left: 34px; }
		@media screen and (max-width:980px) { .alert-box { top:15%; } h1 { font-size:2.2em;} }
		@media screen and (max-width:520px) { .alert-box { top:20%; } h1 { font-size:1.3em;} .user { font-size:.9em;} }
		.gm-style-iw {width:300px!important;}
		.gm-style-cc {display:none;}
		img[src$="mapcnt3.png"] {display:none!important;}
	</style>
	<link rel="stylesheet" href="//fonts.googleapis.com/css?family=Armata" type="text/css">
    <script src="https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false"></script>
    <script>

var map;
var TILE_SIZE = 256;

var tweet_lat = <?php foreach($string as $items) { echo $items[0]['geo']['coordinates'][0]; } ?>;
var tweet_lon = <?php foreach($string as $items) { echo $items[0]['geo']['coordinates'][1]; } ?>;

var berkeley = new google.maps.LatLng(tweet_lat,tweet_lon);
var MY_MAPTYPE_ID = 'custom_style';

function bound(value, opt_min, opt_max) {
  if (opt_min != null) value = Math.max(value, opt_min);
  if (opt_max != null) value = Math.min(value, opt_max);
  return value;
}

function degreesToRadians(deg) {
  return deg * (Math.PI / 180);
}

function radiansToDegrees(rad) {
  return rad / (Math.PI / 180);
}

/** @constructor */
function MercatorProjection() {
  this.pixelOrigin_ = new google.maps.Point(TILE_SIZE / 2,
      TILE_SIZE / 2);
  this.pixelsPerLonDegree_ = TILE_SIZE / 360;
  this.pixelsPerLonRadian_ = TILE_SIZE / (2 * Math.PI);
}

MercatorProjection.prototype.fromLatLngToPoint = function(latLng,
    opt_point) {
  var me = this;
  var point = opt_point || new google.maps.Point(0, 0);
  var origin = me.pixelOrigin_;

  point.x = origin.x + latLng.lng() * me.pixelsPerLonDegree_;

  // Truncating to 0.9999 effectively limits latitude to 89.189. This is
  // about a third of a tile past the edge of the world tile.
  var siny = bound(Math.sin(degreesToRadians(latLng.lat())), -0.9999,
      0.9999);
  point.y = origin.y + 0.5 * Math.log((1 + siny) / (1 - siny)) *
      -me.pixelsPerLonRadian_;
  return point;
};

MercatorProjection.prototype.fromPointToLatLng = function(point) {
  var me = this;
  var origin = me.pixelOrigin_;
  var lng = (point.x - origin.x) / me.pixelsPerLonDegree_;
  var latRadians = (point.y - origin.y) / -me.pixelsPerLonRadian_;
  var lat = radiansToDegrees(2 * Math.atan(Math.exp(latRadians)) -
      Math.PI / 2);
  return new google.maps.LatLng(lat, lng);
};

function createInfoWindowContent() {
  var numTiles = 1 << map.getZoom();
  var projection = new MercatorProjection();
  var worldCoordinate = projection.fromLatLngToPoint(berkeley);
  var pixelCoordinate = new google.maps.Point(
      worldCoordinate.x * numTiles,
      worldCoordinate.y * numTiles);
  var tileCoordinate = new google.maps.Point(
      Math.floor(pixelCoordinate.x / TILE_SIZE),
      Math.floor(pixelCoordinate.y / TILE_SIZE));

  return [
    '<div class="tweet"><h1><?php foreach($string as $items) { echo addslashes($items[0]["text"]); } ?></h1><span style="font-style:italic;"><?php foreach($string as $items) { echo addslashes($items[0]['user']['name']); } ?></span></div>'
  ].join('<br>');
}

function initialize() {

  var featureOpts = [
  {
    "stylers": [
      { "hue": "#00ccff" },
      { "lightness": 1 },
      { "gamma": 0.59 },
      { "saturation": -57 },
      { "visibility": "on" },
      { "weight": 1 }
    ]
 },{
    "elementType": "labels.text",
    "stylers": [
      { "visibility": "on" },
      { "gamma": 1 },
      { "weight": 0.5 },
      { "hue": "#00ffaa" },
      { "saturation": -44 },
      { "lightness": 30 }
    ]
  }
  ];

  var mapOptions = {
    zoom: 17,
    center: berkeley,
    mapTypeControlOptions: {
      mapTypeIds: [google.maps.MapTypeId.ROADMAP, MY_MAPTYPE_ID]
    },
    mapTypeId: MY_MAPTYPE_ID,
    disableDefaultUI: true
  };

  map = new google.maps.Map(document.getElementById('map-canvas'),
      mapOptions);

  var styledMapOptions = {
    name: 'Custom Style'
  };

  var customMapType = new google.maps.StyledMapType(featureOpts, styledMapOptions);

  var coordInfoWindow = new google.maps.InfoWindow();
  coordInfoWindow.setContent(createInfoWindowContent());
  coordInfoWindow.setPosition(berkeley);
  coordInfoWindow.open(map);

  google.maps.event.addListener(map, 'zoom_changed', function() {
    coordInfoWindow.setContent(createInfoWindowContent());
    coordInfoWindow.open(map);
  });

  map.mapTypes.set(MY_MAPTYPE_ID, customMapType);
}

google.maps.event.addDomListener(window, 'load', initialize);

    </script>
  </head>
  <body>
    <div id="map-canvas"></div>
  </body>
</html>
