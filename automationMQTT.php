#!/usr/bin/php
<?php

require(realpath(dirname(__FILE__))."/../phpMQTT/phpMQTT.php");


$server = "127.0.0.1";     // change if necessary
$port = 1883;                     // change if necessary
$username = "";                   // set your username
$password = "";                   // set your password
$client_id = uniqid("automation_"); // make sure this is unique for connecting to sever - you could use uniqid()

$iniarray = parse_ini_file("automationMQTT.ini",true);
if (($tmp = $iniarray["automation"]["mqttserver"]) != "") $server = $tmp;
if (($tmp = $iniarray["automation"]["mqttport"]) != "") $port = $tmp;
if (($tmp = $iniarray["automation"]["mqttusername"]) != "") $username = $tmp;
if (($tmp = $iniarray["automation"]["mqttpassword"]) != "") $password = $tmp;

$mqttdata = array();

$mqtt = new phpMQTT($server, $port, $client_id);

$lastgasdatetime = "";

if(!$mqtt->connect(true, NULL, $username, $password)) {
	exit(1);
}

echo "Connected to mqtt server...\n";
$topics = array();
$topics['home/ESP_WATERMETER/m3'] = array("qos" => 0, "function" => "mqtt_watermeter");
$topics['home/ESP_BADKAMER/dht22/humidity'] = array("qos" => 0, "function" => "mqtt_humidity");
$topics['home/ESP_SLAAPKAMER2/dht22/humidity'] = array("qos" => 0, "function" => "mqtt_humidity");
$topics['home/ESP_SLAAPKAMER2/dht22/temperature'] = array("qos" => 0, "function" => "mqtt_cooldown");
$topics['home/ESP_SLAAPKAMER2/mhz19/co2'] = array("qos" => 0, "function" => "mqtt_co2");
$topics['home/buienradar/actueel_weer/weerstations/weerstation/6370/temperatuurGC'] = array("qos" => 0, "function" => "mqtt_cooldown");
$mqtt->subscribe($topics, 0);

while($mqtt->proc()){
	usleep(10000);
	if ((date('n') > 4) && (date('n') < 10))
	{
		// UV lamp is on from may till september from 16:00 till 17:00, pump is always on
		if ((date('H') >= 9) && (date('H') <= 11))
		{
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/0", "1", 1, 1);
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/1", "0", 1, 1);
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/2", "1", 1, 1);
		}
		else 
		{
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/0", "1", 1, 1);
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/1", "0", 1, 1);
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/2", "0", 1, 1);
		}
	}
	else
	{
/*		// From oktober till april pump is only on from 8:00 till 18:00, uv is always off
		if ((date('H') > 7) && (date('H') < 18))
		{
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/0", "1", 1, 1);
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/1", "0", 1, 1);
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/2", "0", 1, 1);
		}
		else 
		{
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/0", "0", 1, 1);
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/1", "0", 1, 1);
			$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/2", "0", 1, 1);
		}*/
	}

}


$mqtt->close();

function mqtt_watermeter($topic, $msg)
{
	static $m3_begin = 0;
	static $m3_begin_time = 0;
	static $m3_max = 0.1;
	global $mqttdata;
	$mqttdata[$topic] = $msg;
	if ($m3_begin == 0) 
	{
		$m3_begin = $msg;
		$m3_begin_time = time();
	}
	
	echo ("Watermeter=".$msg." Watermeterbegin=".$m3_begin."\n");
	
	if ($m3_begin != $msg)
	{
		if ((time() - 3600) > $m3_begin_time)
		{
			$m3_begin_time = time();
			$m3_begin = $msg;
		}
		if (($m3_begin + $m3_max) <= $msg)
		{
			echo ("Sending water alert\n");
			mail("jeroensteenhuis80@gmail.com", "Hoog water gebruik", "Water gebruik meer dan ".($m3_max*1000)." liter binnen een uur, huidige stand is ".$msg);
			$m3_begin_time = time();
			$m3_begin = $msg;
		}
	}
}
function mqtt_cooldown($topic, $msg){
	global $mqtt;
	global $mqttdata;
	$mqttdata[$topic] = $msg;

	echo "$topic = $msg\n";
	if ((isset($mqttdata['home/ESP_SLAAPKAMER2/dht22/temperature'])) && (isset($mqttdata['home/buienradar/actueel_weer/weerstations/weerstation/6370/temperatuurGC'])))
	{
		if ($mqttdata['home/ESP_SLAAPKAMER2/dht22/temperature'] >= 21.5)
		{
		  if ($mqttdata['home/ESP_SLAAPKAMER2/dht22/temperature'] > $mqttdata['home/buienradar/actueel_weer/weerstations/weerstation/6370/temperatuurGC']+1) 
		  {
		  	echo ("slaapkamer te warm, start afkoelen...\n");
		  	$fanspeed = 2;
			setfanspeed("cooldown", 2);
		  }
		  else if ($mqttdata['home/ESP_SLAAPKAMER2/dht22/temperature'] <= $mqttdata['home/buienradar/actueel_weer/weerstations/weerstation/6370/temperatuurGC'])
		  {
		  	echo ("buiten warmer dan binnen, stop afkoelen...\n");
		  	setfanspeed("cooldown", 0);
		  }
		}
		
		if ($mqttdata['home/ESP_SLAAPKAMER2/dht22/temperature'] <= 21)
		{
		  echo ("slaapkamer koel, stop afkoelen...\n");
		  setfanspeed("cooldown", 0);
		}
	}
}

function mqtt_humidity($topic, $msg){
	global $mqtt;
	global $mqttdata;
	$mqttdata[$topic] = $msg;

	echo "$topic = $msg\n";
	if ((isset($mqttdata['home/ESP_BADKAMER/dht22/humidity'])) && (isset($mqttdata['home/ESP_SLAAPKAMER2/dht22/humidity'])))
	{
		bathroom_fanspeed($mqttdata['home/ESP_BADKAMER/dht22/humidity'] ,$mqttdata['home/ESP_SLAAPKAMER2/dht22/humidity']);
	}
	
}

function mqtt_co2($topic, $msg){
	global $mqtt;
	global $mqttdata;
	$mqttdata[$topic] = $msg;
	static $fanspeed = 0;


	echo "$topic = $msg\n";
	if (isset($mqttdata['home/ESP_SLAAPKAMER2/mhz19/co2']))
	{
		$co2=$mqttdata['home/ESP_SLAAPKAMER2/mhz19/co2'];
		// If a counter has changed recalculate values
		if ($co2 < 800)
	        {
        		$fanspeed = 0;
		}
		else if ($co2 > 1200)
		{
			$fanspeed = 2;
	        }
	        else if (($co2 < 1000 && $fanspeed == 2) || ($co2 > 1000 && $fanspeed == 0))
	        {
        		$fanspeed = 1;
		}
	}
	
	setfanspeed("slaapkamer2co2", $fanspeed);	
}

function bathroom_fanspeed($bathroomhumidity, $sleepingroomhumidity){
	global $mqtt;
	static $fanspeed = 0;

	// If a counter has changed recalculate values
        if ($bathroomhumidity < $sleepingroomhumidity+5)
        {
        	$fanspeed = 0;
	}
	else if ($bathroomhumidity > 95.0)
	{
		$fanspeed = 2;
        }
        else if (($bathroomhumidity < 85.0 && $fanspeed == 2) || ($bathroomhumidity > 70.0 && $fanspeed == 0))
        {
        	$fanspeed = 1;
	}
	setfanspeed("bathroom", $fanspeed);	
}

function setfanspeed($sourceid, $newfanspeed)
{
	echo ("setfanspeed(".$sourceid.",".$newfanspeed.");\n");
	static $sourcefanspeed = array();
	$sourcefanspeed[$sourceid] = $newfanspeed;
	$fanspeed = 0;
	global $mqtt;
	global $mqttdata;
	global $topics;
	
	// Wait for all mqttdata to be received before setting fanspeed preventing unneeded writes to ducobox eprom
	//if (count($mqttdata) == count($topics))
	//{
	
		foreach ($sourcefanspeed as $key => $value)
		{
			if ($value > $fanspeed) $fanspeed = $value;
		}
	
		echo "Sending to ESP_DUCOBOX setfan=".$fanspeed."\n";
		
		$mqtt->publishwhenchanged("home/ESP_DUCOBOX/setfan", $fanspeed, 0, 1);
	//}
}
