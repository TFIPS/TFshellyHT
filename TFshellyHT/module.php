<?php
class TFshellyHT extends IPSModule
{
    public function Create()
	{
        parent::Create();
		$this->RegisterPropertyString("server", "");
		$this->RegisterPropertyString("deviceId", "");
		$this->RegisterPropertyString("token", "");

		$this->RegisterPropertyInteger("wfId", 0);
		$this->RegisterPropertyInteger("targetId", 0);

		$this->RegisterTimer("UpdateData", 0, 'TFSHT_GetData($_IPS[\'TARGET\']);');

		if (!IPS_VariableProfileExists('TFSHT.rssi')) 
		{
            IPS_CreateVariableProfile('TFSHT.rssi', 1);
			IPS_SetVariableProfileIcon ('TFSHT.rssi', 'Intensity');
			IPS_SetVariableProfileText('TFSHT.rssi', '', ' dB');
			IPS_SetVariableProfileValues('TFSHT.rssi', -100, 0, 1);
			IPS_SetVariableProfileAssociation('TFSHT.rssi', 0, 'Unbekannt', '', -1);
			IPS_SetVariableProfileAssociation('TFSHT.rssi', -56, 'sehr gut %d', '', 0x00FF00);
			IPS_SetVariableProfileAssociation('TFSHT.rssi', -75, 'mittelmäßig %d', '', 0xFFFF00);
			IPS_SetVariableProfileAssociation('TFSHT.rssi', -85, 'schlecht %d', '', 0xFF0000);
		}

		if (!IPS_VariableProfileExists('TFSHT.battery')) 
		{
            IPS_CreateVariableProfile('TFSHT.battery', 1);
			IPS_SetVariableProfileIcon ('TFSHT.battery', 'Battery');
			IPS_SetVariableProfileText('TFSHT.battery', '', ' %');
			IPS_SetVariableProfileValues('TFSHT.battery', 0, 100, 1);
		}

		if (!IPS_VariableProfileExists('TFSHT.volt')) 
		{
            IPS_CreateVariableProfile('TFSHT.volt', 2);
			IPS_SetVariableProfileIcon ('TFSHT.volt', 'Electricity');
			IPS_SetVariableProfileDigits('TFSHT.volt', 2); 
			IPS_SetVariableProfileText('TFSHT.volt', '', ' Volt');
			IPS_SetVariableProfileValues('TFSHT.volt', 0, 6, 0.01);
		}

		if (!IPS_VariableProfileExists('TFSHT.humidity')) 
		{
            IPS_CreateVariableProfile('TFSHT.humidity', 2);
			IPS_SetVariableProfileIcon ('TFSHT.humidity', 'Gauge');
			IPS_SetVariableProfileDigits('TFSHT.humidity', 2);
			IPS_SetVariableProfileText('TFSHT.humidity', '', ' %');
			IPS_SetVariableProfileValues('TFSHT.humidity', 0, 100, 0.1);
		}

		if (!IPS_VariableProfileExists('TFSHT.temperature')) 
		{
            IPS_CreateVariableProfile('TFSHT.temperature', 2);
			IPS_SetVariableProfileIcon ('TFSHT.temperature', 'Temperature');
			IPS_SetVariableProfileDigits('TFSHT.temperature', 2);
			IPS_SetVariableProfileText('TFSHT.temperature', '', ' °C');
			IPS_SetVariableProfileValues('TFSHT.temperature', -45, 135, 0.1);
		}

		$online_ID		= $this->RegisterVariableBoolean("online", "Online", "~Switch", 1);
		$rssi_ID		= $this->RegisterVariableInteger("rssi", "WLAN Qualität", "TFSHT.rssi", 2);
		$batteryP_ID	= $this->RegisterVariableInteger("batteryP", "Batterie", "TFSHT.battery", 3);
		$batteryV_ID	= $this->RegisterVariableFloat("batteryV", "Batterie", "TFSHT.volt", 4);
		$exPower_ID		= $this->RegisterVariableBoolean("exPower", "Externe Stromversorgung", "~Switch", 5);
		$humidity_ID	= $this->RegisterVariableFloat("humidity", "Luftfeuchtigkeit", "TFSHT.humidity", 6);
		$temp_ID		= $this->RegisterVariableFloat("temperature", "Temperatur", "TFSHT.temperature", 7);
		$alarmTemp_ID	= $this->RegisterVariableBoolean("alarmTemp", "Temperaturalarm", "~Switch", 8);
		$alarmTempMax_ID= $this->RegisterVariableFloat("alarmTempMax", "Alarm-Temperatur Max", "TFSHT.temperature", 9);
		$alarmTempMin_ID= $this->RegisterVariableFloat("alarmTempMin", "Alarm-Temperatur Min", "TFSHT.temperature", 10);

		$this->EnableAction('alarmTemp');
		$this->EnableAction('alarmTempMax');
		$this->EnableAction('alarmTempMin');

		$this->RegisterMessage(0, IPS_KERNELMESSAGE);
		$this->RegisterMessage($temp_ID, VM_UPDATE);
    }
    
    public function ApplyChanges()
	{
        parent::ApplyChanges();

		$server		= $this->ReadPropertyString("server");
		$deviceId	= $this->ReadPropertyString("deviceId");
		$token		= $this->ReadPropertyString("token");
		if($server != "" && $deviceId != "" && $token != "")
		{
			$this->SetTimerInterval("UpdateData", 120000);
		}
		else
		{
			$this->SetTimerInterval("UpdateData", 0);
		}
    }

	public function MessageSink($time, $senderId, $messageId, $data)
	{
		switch ($messageId) {
            case VM_UPDATE:
				if($data[0] != $data[2] && $this->GetValue('alarmTemp'))
				{
					$wfId		= $this->ReadPropertyInteger("wfId");
					$targetId	= $this->ReadPropertyInteger("targetId");
					
					if($wfId != "" && $targetId != "")
					{
						if($data[0] >= $this->GetValue('alarmTempMax'))
						{
							WFC_PushNotification($wfId, "Temperaturalarm", "Die Maximaltemperatur von ".IPS_GetName($this->InstanceID)." wurde überschritten!", "Alarm", $targetId);
						}
						if($data[0] <= $this->GetValue('alarmTempMin'))
						{
							WFC_PushNotification($wfId, "Temperaturalarm", "Die Minimaltemperatur von ".IPS_GetName($this->InstanceID)." wurde unterschritten!", "Alarm", $targetId);
						}
					}
				}
            break;

            case KR_READY:
                $server		= $this->ReadPropertyString("server");
				$deviceId	= $this->ReadPropertyString("deviceId");
				$token		= $this->ReadPropertyString("token");
				if($server != "" && $deviceId != "" && $token != "")
				{
					$this->SetTimerInterval("UpdateData", 5000);
				}
				else
				{
					$this->SetTimerInterval("UpdateData", 0);
				}
            break;
        }
	}

	public function GetData()
	{
		$server		= $this->ReadPropertyString("server");
		$deviceId	= $this->ReadPropertyString("deviceId");
		$token		= $this->ReadPropertyString("token");
		if($server != "" && $deviceId != "" && $token != "")
		{
			$post = [
				'id' => $deviceId,
				'auth_key' => $token
			];
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, "https://".$server.".shelly.cloud/device/status");
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
			$resp = curl_exec($curl);
			curl_close($curl);
			$valueData = json_decode($resp, true);
			if($valueData["isok"] == 1)
			{
				if(array_key_exists("data", $valueData))
				{
					$this->SetValue("online", $valueData["data"]["online"]);

					if(array_key_exists("device_status", $valueData["data"]))
					{
						if(array_key_exists("devicepower:0", $valueData["data"]["device_status"]))
						{
							$this->SetValue("batteryV", $valueData["data"]["device_status"]["devicepower:0"]["battery"]["V"]);
							$this->SetValue("batteryP", $valueData["data"]["device_status"]["devicepower:0"]["battery"]["percent"]);
							$this->SetValue("exPower", $valueData["data"]["device_status"]["devicepower:0"]["external"]["present"]);
						}
						if(array_key_exists("temperature:0", $valueData["data"]["device_status"]))
						{
							$this->SetValue("temperature", $valueData["data"]["device_status"]["temperature:0"]["tC"]);
						}
						if(array_key_exists("wifi", $valueData["data"]["device_status"]))
						{
							$this->SetValue("rssi", $valueData["data"]["device_status"]["wifi"]["rssi"]);
						}
						if(array_key_exists("humidity:0", $valueData["data"]["device_status"]))
						{
							$this->SetValue("humidity", $valueData["data"]["device_status"]["humidity:0"]["rh"]);
						}
					}
				}
			}
		}
		else
		{
			echo "Konfiguration unvollständig!";
		}
	}
		
	public function RequestAction($ident, $value) 
	{
		$this->SetValue($ident, $value);
	}
}