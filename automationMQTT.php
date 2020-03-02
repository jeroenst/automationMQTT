#!/usr/bin/php
<?php

require(realpath(dirname(__FILE__))."/../phpMQTT/phpMQTT.php");

$presence = 0;

$water_alerttime = 0;
$water_timeout = 35;

$starttime = time();

$server = "127.0.0.1";     // change if necessary
$port = 1883;                     // change if necessary
$username = "";                   // set your username
$password = "";                   // set your password
$client_id = uniqid("automationMQTT"); // make sure this is unique for connecting to sever - you could use uniqid()

$iniarray = parse_ini_file("automationMQTT.ini",true);
if (($tmp = $iniarray["automation"]["mqttserver"]) != "") $server = $tmp;
if (($tmp = $iniarray["automation"]["mqttport"]) != "") $port = $tmp;
if (($tmp = $iniarray["automation"]["mqttusername"]) != "") $username = $tmp;
if (($tmp = $iniarray["automation"]["mqttpassword"]) != "") $password = $tmp;
if (($tmp = $iniarray["automation"]["telegramchatid"]) != "") $telegram_chatid = $tmp;
if (($tmp = $iniarray["automation"]["telegramjeroenuserid"]) != "") $telegram_jeroen_userid = $tmp;
if (($tmp = $iniarray["automation"]["telegramtoken"]) != "") $telegram_token = $tmp;


$mqttdata = array();

$statustopic = "home/automation/status";
$will = array();
$will["topic"] = $statustopic;
$will["content"] = "offline";
$will["qos"] = 1;
$will["retain"] = 1;



$mqtt = new phpMQTT($server, $port, $client_id);

$lastgasdatetime = "";

notify_jeroen("AutomationMQTT started...");

$mqttoldstate = "none";

while (1)
{
	while(!$mqtt->connect(true, $will, $username, $password))
	{
		if ($mqttoldstate != "disconnected")
		{
			$mqttoldstate = "disconnected";
			notify_jeroen("Disconnected from MQTT broker...");
		}
		sleep(2);
	}
	if ($mqttoldstate != "connected")
	{
		$mqttoldstate = "connected";
		notify_jeroen("Connected to MQTT broker...");
		$mqtt->publish($statustopic, "online", 1, 1);
	}

echo "Connected to mqtt server...\n";
$topics = array();
$topics['home/ESP_WATERMETER/water/liter'] = array("qos" => 0, "function" => "mqtt_watermeter");
$topics['home/ESP_WATERMETER/water/lmin'] = array("qos" => 0, "function" => "mqtt_watermeter_lmin");
$topics['home/ESP_BATHROOM/dht22/humidity'] = array("qos" => 0, "function" => "mqtt_humidity");
$topics['home/ESP_BATHROOM/dht22/temperature'] = array("qos" => 0, "function" => "mqtt_heating");
$topics['home/ESP_BEDROOM2/dht22/humidity'] = array("qos" => 0, "function" => "mqtt_humidity");
$topics['home/ESP_BEDROOM2/dht22/temperature'] = array("qos" => 0, "function" => "mqtt_cooldown");
$topics['home/ESP_BEDROOM2/mhz19/co2'] = array("qos" => 0, "function" => "mqtt_co2");
$topics['home/ESP_WEATHER/temperature'] = array("qos" => 0, "function" => "mqtt_cooldown");
$topics['home/ESP_OPENTHERM/thermostat/temperature'] = array("qos" => 0, "function" => "mqtt_heating");
$topics['home/ESP_OPENTHERM/thermostat/setpoint'] = array("qos" => 0, "function" => "mqtt_heating");
$topics['home/SONOFF_WASHINGMACHINE/power'] = array("qos" => 0, "function" => "mqtt_washingmachine");
$topics['home/SONOFF_DISHWASHER/power'] = array("qos" => 0, "function" => "mqtt_dishwasher");
$topics['home/ESP_PELLETSTOVE/power/value'] = array("qos" => 0, "function" => "mqtt_heating");
$topics['home/ESP_PELLETSTOVE/exhaust/temperature'] = array("qos" => 0, "function" => "mqtt_heating");
$topics['home/automationMQTT/pelletstove/setpoint/set']  = array("qos" => 0, "function" => "mqtt_heating");
$topics['home/automationMQTT/bathroom/setpoint/set']  = array("qos" => 0, "function" => "mqtt_heating");
$topics['home/QSWIFIDIMMER03/switchstate/1']  = array("qos" => 0, "function" => "scenes");
$topics['home/automationmqtt/setscene']  = array("qos" => 0, "function" => "scenes");

$mqtt->subscribe($topics, 0);
$mqtt->publish("home/SONOFF_POND/setrelay/0", "1", 1, 1);
$mqtt->publish("home/SONOFF_POND/setrelay/1", "0", 1, 1);
$mqtt->publish("home/SONOFF_POND/setrelay/2", "0", 1, 1);
$mqtt->publish("home/SONOFF_POND/setrelay/3", "0", 1, 1);
$mqtt->publish("home/SONOFF_DISHWASHER/setrelay/0", "1", 1, 1);
$mqtt->publish("home/SONOFF_WASHINGMACHINE/setrelay/0", "1", 1, 1);
$mqtt->publish("home/SONOFF_SERVER/setrelay/0", "1", 1, 1);

$firstrun = true;
while($mqtt->proc()){
	usleep(10000);
	if ((date('n') > 3) && (date('n') < 9))
	{
		// UV lamp is on from april till september from 9:00 till 12:00, pump is always on
		if ((date('H') >= 9) && (date('H') < 14))
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
		$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/0", "1", 1, 1);
		$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/1", "0", 1, 1);
		$mqtt->publishwhenchanged("home/ESP_TUIN/setrelay/2", "0", 1, 1);
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
	
	if (($water_alerttime > 0) && ($water_alerttime < time()))
	{
		$water_alerttime = time() + ($water_timeout * 60);
		mail("jeroensteenhuis80@gmail.com", "Lang water gebruik", "Water gebruik langer dan ".($water_timeout)." minuten");
		notify_jeroen("Water gebruik langer dan ".($water_timeout)." minuten");
	}

	check_presence();
	
	$uptime = time() - $starttime;
	if (($uptime % 60 == 0) || $firstrun)
	{
		$s = time()-$starttime;
		$uptimestr = sprintf('%d:%02d:%02d:%02d', $s/86400, $s/3600%24, $s/60%60, $s%60);
		$mqtt->publishwhenchanged("home/automation/system/uptime", $uptimestr, 1, 1);
	}
	$firstrun = false;
}
}

$mqtt->close();

function check_presence()
{
	global $mqtt;
	global $presence;
	static $firstrun = true;
	static $timechanged = 0;
	static $presence_jeroen_time = 0;
	static $presence_jeroen = 0;
	static $old_presence_jeroen = 0;
	static $presence_mieke_time = 0;
	static $presence_mieke = 0;
	static $old_presence_mieke = 0;
	static $old_presence = -1;
	
	$leasefilecontents = file_get_contents("/etc/pihole/dhcp.leases");

	if(strpos($leasefilecontents,'6c:c7:ec:43:52:d6') !== false) $presence_jeroen_time = time();	
	if(strpos($leasefilecontents,'f4:f5:24:88:be:21') !== false) $presence_mieke_time = time();

	// Wait 20 seconds after lease has dissapeared before changing presence...
	$presence_jeroen = ($presence_jeroen_time + 20 > time())?1:0;
	$presence_mieke = ($presence_mieke_time + 20 > time())?1:0;
	
	if (($old_presence_jeroen != $presence_jeroen) && !$firstrun)
	{
		echo ("old_presence_jeroen=".$old_presence_jeroen." presence_jeroen=".$presence_jeroen." firstrun=".$firstrun."\n");
		$old_presence_jeroen = $presence_jeroen;
		notify_jeroen('Jeroen is '.($presence_jeroen?"present":"away"));
	}

	if (($old_presence_mieke != $presence_mieke) && !$firstrun)
	{
		echo ("old_presence_mieke=".$old_presence_mieke." presence_mieke=".$presence_mieke." firstrun=".$firstrun."\n");
		$old_presence_mieke = $presence_mieke;
		notify_jeroen('Mieke is '.($presence_mieke?"present":"away"));
	}
	
	

	$presence = $presence_jeroen + $presence_mieke;
	if ($old_presence !== $presence)
	{
		echo ("presence=".$presence."\n");
		mqtt_heating();
	}
	$old_presence = $presence;

	if ((date('H') < 22) && (date('H') >= 8) && $presence) 
	{
  	$mqtt->publishwhenchanged("home/SONOFF_IRRIGATION/setrelay/3", "0", 1, 1);
	}
	else
	{
		$mqtt->publishwhenchanged("home/SONOFF_IRRIGATION/setrelay/3", "1", 1, 1);
	}
	
	$firstrun = false;
}

function mqtt_heating($topic = "", $msg = "")
{
	global $mqtt;
	global $presence;
	static $bathroomsetpoint = 20.5;
	static $pelletstovesetpoint = 21.5;
	static $bathroomwatertemp = "-";
	static $livingroomwatertemp = "-";
	static $watertemp = "-";
	static $outsidetemp = "-";
	static $bathroomtemp = "-";
	static $livingroomtemp = "-";
	static $livingroomsetpoint = "-";
	static $pelletstovepower = "-";
	static $pelletstoveexhausttemp = "-";

	if ($topic == 'home/ESP_WEATHER/temperature') $outsidetemp = $msg;
	if ($topic == 'home/ESP_BATHROOM/dht22/temperature') $bathroomtemp = $msg;
	if ($topic == 'home/ESP_OPENTHERM/thermostat/temperature') $livingroomtemp = $msg;
	if ($topic == 'home/ESP_OPENTHERM/thermostat/setpoint') $livingroomsetpoint = $msg;
	if ($topic == "home/ESP_PELLETSTOVE/power/value") $pelletstovepower = $msg;
	if ($topic == "home/ESP_PELLETSTOVE/exhaust/temperature") $pelletstoveexhausttemp = $msg;

	if ($topic == "home/automationMQTT/pelletstove/setpoint/set") 
	{
		if (is_numeric($msg) && ($msg >= 15) && ($msg <= 25)) $pelletstovesetpoint = $msg;
	}

	if ($topic == "home/automationMQTT/bathroom/setpoint/set") 
	{
		if (is_numeric($msg) && ($msg >= 15) && ($msg <= 25)) $bathroomsetpoint = $msg;
	}

	$mqtt->publishwhenchanged("home/automationMQTT/pelletstove/setpoint", $pelletstovesetpoint, 1, 1);
	$mqtt->publishwhenchanged("home/automationMQTT/bathroom/setpoint", $bathroomsetpoint, 1, 1);



	if (($outsidetemp != "-") && ($bathroomtemp != "-"))
	{
//		if ((date('H') >= 17) && (date('H') <= 21))
//		{
			if ($bathroomtemp < $bathroomsetpoint) $bathroomwatertemp = (((-$outsidetemp)/1) + 40);
			if ($bathroomtemp > $bathroomsetpoint) $bathroomwatertemp = 0;
			if ($outsidetemp > 18) $bathroomwatertemp = 0;
/*		}
		else
		{
			if ($bathroomtemp < 19) $bathroomwatertemp = (((-$outsidetemp)/1) + 35);
			if ($bathroomtemp > 19) $bathroomwatertemp = 0;
			if ($outsidetemp > 18) $bathroomwatertemp = 0;
		}*/
	}

	if (($livingroomtemp != "-") && ($livingroomsetpoint != "-"))
	{
		if ($livingroomtemp < $livingroomsetpoint)
		{
			$livingroomwatertemp = (((-$outsidetemp)/1) + 35);
			$mqtt->publishwhenchanged("home/SONOFF_FLOORHEATING/setvalve", "1", 1, 1);
		}
		if ($livingroomtemp > $livingroomsetpoint)
		{
			$livingroomwatertemp = 0;
			$mqtt->publishwhenchanged("home/SONOFF_FLOORHEATING/setvalve", "0", 1, 1);
		}
	}
	
	$watertemp = max($bathroomwatertemp, $livingroomwatertemp);
	if (($watertemp > 0) && ($watertemp < 35)) $watertemp = 35;
	if ($watertemp > 50) $watertemp = 50; // Maximize watertemp because floorheating has no limit
	echo (__FUNCTION__." OUTSIDE TEMP=".$outsidetemp." CH WATERTEMP=".$watertemp."\n");
	$mqtt->publishwhenchanged("home/ESP_OPENTHERM/setchwatertemperature", round($watertemp), 1, 1);

	
	if (($livingroomtemp != "-") && ($pelletstovepower != "-"))
	{
		static $tempreached = 0;
		if ($pelletstovepower > 0)
		{
				if ($livingroomtemp < ($pelletstovesetpoint - 0.5))
				{
					if ($presence == 0 ) $mqtt->publishwhenchanged("home/ESP_PELLETSTOVE/setpower", 5, 1, 1);
					else $mqtt->publishwhenchanged("home/ESP_PELLETSTOVE/setpower", 4, 1, 1);
				}
				else if ($livingroomtemp < ($pelletstovesetpoint - 0.1))
				{
					if ($pelletstovepower < 3) $mqtt->publishwhenchanged("home/ESP_PELLETSTOVE/setpower", 3, 1, 1);
				}
				else if ($livingroomtemp < $pelletstovesetpoint)
				{
					if ($pelletstovepower < 2) $mqtt->publishwhenchanged("home/ESP_PELLETSTOVE/setpower", 2, 1, 1);
				}
				else $mqtt->publishwhenchanged("home/ESP_PELLETSTOVE/setpower", 1, 1, 1);
		}
		else $tempreached = 0;
	}
	
	if (($pelletstovepower != "-") && ($pelletstoveexhausttemp != "-"))
	{
		static $exhausttempreached = 0;
		if ($pelletstovepower > 0)
		{
			if (($exhausttempreached == 0) && ($pelletstoveexhausttemp > 100)) $exhausttempreached = 1;
			if (($exhausttempreached == 1) && ($pelletstoveexhausttemp < 100))
			{
				notify_clients("Pelletkachel moet worden bijgevuld.");
				$exhausttempreached = 0;
			}
		}
		else $exhausttempreached = 0;
	}
	echo (__FUNCTION__." bathroom temperature=".$bathroomtemp." setpoint=".$bathroomsetpoint."\n");
	echo (__FUNCTION__." livingroom temperature=".$livingroomtemp." setpoint=".$livingroomsetpoint."\n");
	echo (__FUNCTION__." peletstove power=".$pelletstovepower." setpoint=".$pelletstovesetpoint."\n");
}


function mqtt_watermeter($topic, $msg)
{
	static $liter_begin = 0;
	static $liter_begin_time = 0;
	static $liter_max = 1000;
	static $liter_used = 0;
	global $mqttdata;
	$mqttdata[$topic] = $msg;

	if (((time() - 3600) > $liter_begin_time) || ($liter_begin == 0))
	{
		$liter_begin = $msg;
		$liter_begin_time = time();
	}

	$liter_used = $msg - $liter_begin;
	
	echo ("Watermeter=".$msg." Watermeterbegin=".$liter_begin." Watermetertime=".$liter_begin_time." literused=".$liter_used." litermax=".$liter_max."\n");
	
	if ($liter_begin != $msg)
	{
		if ($liter_used > $liter_max)
		{
			echo ("Sending water alert\n");
			mail("jeroensteenhuis80@gmail.com", "Hoog water gebruik", "Water gebruik meer dan ".($liter_max)." liter binnen een uur, huidige stand is ".$msg);
			notify_clients("Water gebruik meer dan ".($liter_max)." liter binnen een uur, huidige stand is ".$msg);
			$liter_begin_time = time();
			$liter_begin = $msg;
		}
	}
}

function mqtt_watermeter_lmin($topic, $msg)
{
	global $water_alerttime;
	global $water_timeout;
	global $mqttdata;
	$mqttdata[$topic] = $msg;

	echo ("Watermeter lmin=".$msg." water_alerttime=".$water_alerttime." current_time=".time()." water_timeout=".$water_timeout."(*60)\n");
	
	if ($msg > 0)
	{
		if ($water_alerttime == 0) $water_alerttime = time() + ($water_timeout * 60);
	}
	else $water_alerttime = 0;
}

function mqtt_cooldown($topic, $msg){
	if ($topic == "home/ESP_WEATHER/temperature") mqtt_heating($topic, $msg);
	global $mqtt;
	global $mqttdata;
	static $cooldown = -1;
	static $firstnotify = 1;
	static $fanspeed = 0;
	$mqttdata[$topic] = $msg;

	echo "$topic = $msg\n";
	if ((isset($mqttdata['home/ESP_BEDROOM2/dht22/temperature'])) && (isset($mqttdata['home/ESP_WEATHER/temperature'])))
	{
		if ($mqttdata['home/ESP_BEDROOM2/dht22/temperature'] > 21.8)
		{
		  if ($mqttdata['home/ESP_WEATHER/temperature'] < $mqttdata['home/ESP_BEDROOM2/dht22/temperature'] - 0.5) 
		  {
		  	if ($cooldown != 1)
		  	{
		  		$cooldown = 1;
			  	if ($firstnotify==0) notify_clients("De ramen kunnen open om het huis af te koelen");
			  	else $firstnotify = 0;
			  	echo ("slaapkamer te warm, start afkoelen...\n");
				}
//				if ($mqttdata['home/ESP_BEDROOM2/dht22/temperature'] <= 22) $fanspeed = 1;
				$fanspeed = 2;
		  }
		  else if ($mqttdata['home/ESP_WEATHER/temperature'] >= $mqttdata['home/ESP_BEDROOM2/dht22/temperature'])
		  {
		  	if ($cooldown != 0)
		  	{
		  		$cooldown = 0;
			  	if ($firstnotify==0) notify_clients("De ramen moeten dicht om het huis koel te houden");
			  	else $firstnotify = 0;
		  		echo ("buiten warmer dan binnen, stop afkoelen...\n");
		  		$fanspeed = 0;
				}
		  }
		}
		
		if ($mqttdata['home/ESP_BEDROOM2/dht22/temperature'] < 21.8)
		{
			$cooldown = 0;
		  echo ("slaapkamer koel, stop afkoelen...\n");
			$fanspeed = 0;
		}
	}
	setfanspeed("cooldown", $fanspeed);
}

function mqtt_humidity($topic, $msg){
	global $mqtt;
	global $mqttdata;
	$mqttdata[$topic] = $msg;

	echo "$topic = $msg\n";
	if ((isset($mqttdata['home/ESP_BATHROOM/dht22/humidity'])) && (isset($mqttdata['home/ESP_BEDROOM2/dht22/humidity'])))
	{
		bathroom_fanspeed($mqttdata['home/ESP_BATHROOM/dht22/humidity'] ,$mqttdata['home/ESP_BEDROOM2/dht22/humidity']);
	}
}

function mqtt_washingmachine($topic, $msg){
	global $mqtt;
	global $mqttdata;
	$mqttdata[$topic] = $msg;

	static $washing = 0;
	static $highwattcounter = 0;
	static $washingtimeout = 0;

	echo "$topic = $msg\n";
	
	switch ($washing)
	{
		case 0:
			if ($msg > 40)
			{
				$highwattcounter++;
				if ($highwattcounter == 3)
				{
					$washing = 1;
					notify_clients("Wasmachine is gestart");
					notify_jeroen('(Case0) Washmachine > 100');
					$highwattcounter = 0;
				}
			}
			if ($msg < 10)
			{
				$highwattcounter = 0;
			}
		break;
		case 1:
			if ($msg < 1)
			{
				$washingtimeout = time() + 30;
				$washing = 2;
				notify_jeroen('(Case1) Washmachine < 1');
			}
		break;
		case 2:
			if ($msg > 10)
			{
				$washing = 1;
				notify_jeroen('(Case2) Washmachine > 10');
			}
			else if ($washingtimeout <= time())
			{
				$washing = 0;
				notify_clients("Wasmachine is klaar");
				$washingtimeout = 0;
			}
		break;
	}
}

function mqtt_dishwasher($topic, $msg)
{
	global $mqtt;
	global $mqttdata;
	$mqttdata[$topic] = $msg;

	static $washing = 0;
	static $highwattcounter = 0;
	static $washingtimeout = 0;
	
	echo "$topic = $msg\n";
	
	switch ($washing)
	{
		case 0:
                        if ($msg > 40)
                        {
                                $highwattcounter++;
                                if ($highwattcounter == 3)
                                {
                                        $washing = 1;
                                        notify_jeroen('(Case0) Dishwashmachine > 40');
					notify_clients("Vaatwasser is gestart");
                                }
                        }
                        if ($msg < 10)
                        {
                                $highwattcounter = 0;
                        }
		break;
		case 1:
			if ($msg < 3.5)
			{
				$washingtimeout = time() + 900;
				$washing = 2;
				notify_jeroen('(Case1) Dishwashmachine < 3.5');
			}
		break;
		case 2:
			if ($msg > 10)
			{
				$washing = 1;
				notify_jeroen('(Case2) Dishwashmachine > 10');
			}
			else if ($washingtimeout <= time() )
			{
				$washing = 0;
				notify_jeroen('(Case2) Dishwashmachine has finished.');
				notify_clients("Vaatwasser is klaar");
				$washingtimeout = 0;
			}
		break;
	}

}

function mqtt_co2($topic, $msg){
	global $mqtt;
	global $mqttdata;
	$mqttdata[$topic] = $msg;
	static $fanspeed = 0;


	echo "$topic = $msg\n";
	if (isset($mqttdata['home/ESP_BEDROOM2/mhz19/co2']))
	{
		$co2=$mqttdata['home/ESP_BEDROOM2/mhz19/co2'];
		// If a counter has changed recalculate values
		if ($co2 < 1000)
	  {
    	$fanspeed = 0;
		}
		else if ($co2 > 1200)
		{
			$fanspeed = 2;
	  }
	  else if (($co2 < 1100 && $fanspeed == 2) || ($co2 > 1100 && $fanspeed == 0))
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
	if ($bathroomhumidity < $sleepingroomhumidity)	
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
	
		echo "Sending to SONOFF_DUCOBOX setfan=".$fanspeed."\n";
		
		$mqtt->publishwhenchanged("home/SONOFF_DUCOBOX/setfan", $fanspeed, 0, 1);
	//}
}


function notify_clients($message)
{
global $telegram_chatid;
global $telegram_token;

/*$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://hooks.slack.com/services/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' );
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"text":"'.$message.'"}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false );
$response = curl_exec($ch);
curl_close($ch);
*/

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot'.$telegram_token.'/sendMessage');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"chat_id":"'.$telegram_chatid.'", "text":"'.$message.'"}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false );
curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
    'Content-Type: application/json')
);      
$response = curl_exec($ch);
curl_close($ch);

}

function notify_jeroen($message)
{
global $telegram_jeroen_userid;
global $telegram_token;

/*$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://hooks.slack.com/services/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' );
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"text":"'.$message.'"}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false );
$response = curl_exec($ch);
curl_close($ch);
*/

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot'.$telegram_token.'/sendMessage');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"chat_id":"'.$telegram_jeroen_userid.'", "text":"'.$message.'"}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false );
curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
    'Content-Type: application/json')
);      
$response = curl_exec($ch);
curl_close($ch);

}



function scenes($topic, $msg)
{
global $mqtt;
static $scene = "off";
static $oldscene = "off";
$dimvalue = -1;
$tv = -1;
$dimcolor = -1;
$setscene = 0;
	$oldscene = $scene;
	if (($topic == "home/QSWIFIDIMMER03/switchstate/1") &&($msg == 1))
	{
		if ($scene != "diner") $scene = "diner";
		else $scene = "lampsoff";
		$setscene = 1;
	}

	if ($topic == "home/automationmqtt/setscene")
	{
		static $initialized = false;
		$scene = $msg;
		$setscene = 1;
		$initialized = 1;
	}

	if ($setscene)
	{
		echo "seting scene to ".$scene." (topic=".$topic.")\n";
		switch ($scene)
		{
			case "bright":
				$dimcolor = "FFFFFFFFFF";
				$dimvalue = 100;
				$tv = 1;
			break;
			case "evening":
				$dimcolor = "FF00008800";
				$tv = 1;
				$dimvalue = 25;
			break;
			case "movie":
				$dimcolor = "3300001100";
				$dimvalue = 1;
				$tv = 1;
			break;
			case "diner":
				$dimcolor = "FF00008800";
				$dimvalue = 40;
				$tv = 1;
			break;

			case "off":
				$tv = 0;
				$mqtt->publish("home/ESP_PELLETSTOVE/setpower", 0, 1, 1);
			case "lampsoff":
				$dimcolor = "0000000000";
				$dimvalue = 0;
			break;
		}

		if ($dimvalue != -1)
		{
			$mqtt->publish("home/QSWIFIDIMMER01/setdimvalue/1", $dimvalue, 1, 1);
			usleep (10000);
			$mqtt->publish("home/QSWIFIDIMMER02/setdimvalue/0", $dimvalue, 1, 1);
			usleep (10000);
			if ($dimvalue == 0)
			{
				$mqtt->publish("home/QSWIFIDIMMER02/setdimvalue/1", $dimvalue, 1, 1);
				usleep (10000);
			}
			$mqtt->publish("home/QSWIFIDIMMER03/setdimvalue/0", $dimvalue, 1, 1);
			usleep (10000);
			$mqtt->publish("home/QSWIFIDIMMER04/setdimvalue", $dimvalue, 1, 1);
			usleep (10000);
			$mqtt->publish("home/SONOFFS20_001/setrelay/0", $dimvalue ? 1 : 0, 1, 1);
			usleep (10000);
			$mqtt->publish("home/BLITZWOLF_001/setrelay/0", $dimvalue ? 1 : 0, 1, 1);
			usleep (10000);
			$mqtt->publish("home/SONOFF_COFFEELAMP/setrelay/0", $dimvalue ? 1 : 0, 1, 1);
			usleep (10000);
		}
		
		if ($dimcolor != -1)
		{
			$mqtt->publish("home/SONOFF_BULB/setcolor", $dimcolor, 1, 1);
		}
		usleep (10000);
		if ($tv != -1) 
		{
			$mqtt->publish("home/SONOFF_TV/setrelay/0", $tv, 1, 1);
		}
	}
}
