<?php
namespace WebSocket\Application;

/**
 * Shiny WSS Status Application
 * Provides live server infos/messages to client/browser.
 * 
 * @author Simon Samtleben <web@lemmingzshadow.net>
 */
class BeholderApplication extends Application
{
    private $_clients = array();
	private $_serverClients = array();
	private $_serverInfo = array();
	private $_serverClientCount = 0;
	private $_maps = array();

	function __construct() {
		parent::__construct();

		$map_info = array("map_icon_id" => 0, "map_icon" => array());
		// マップ配列
		$this->_maps = array("item_none" => $map_info, "item_balenos" => $map_info, "item_serendia" => $map_info, "item_calpheon" => $map_info, "item_media" => $map_info, "item_valencia" => $map_info);

	}

	public function onConnect($client)
    {
		$id = $client->getClientId();
        $this->_clients[$id]['client'] = $client;
        $this->_clients[$id]['map'] = "item_none";
		$this->_sendServerinfo($client);
    }

    public function onDisconnect($client)
    {
        $id = $client->getClientId();		
		unset($this->_clients[$id]);     
    }

    public function onData($data, $client)
    {
		$client_id = $client->getClientId();
    
		$tag_report_map_icon = "report_map_icon";
		$tag_report_remove_map_icon = "report_remove_map_icon";
		$tag_report_map = "report_map";
		$tag_report_clear_map_icon = "report_clear_map_icon";
		
        $client->send("echo:" . $data);
        $tag = mb_substr($data, 0, strpos($data, ":"));
        $payload = mb_substr($data, strpos($data, ":") + 1);
        print($tag);
        printf("\n");
        print($payload);
        printf("\n");
        if ($tag == $tag_report_map) {
        	$client_map = $this->_clients[$client_id]['map'];

			// 地図の変更
			$this->_notifyRemoveAllMapIcon($client);
			$this->_clients[$client_id]['map'] = $payload;
        	/*// マップアイコンの削除
			foreach ($this->_maps[$client_map]['map_icon'] as $key => $value) {
				$this->_notifyRemoveMapIconAll($this->_maps[$client_map]['map_icon'][$key]['id']);
				unset($this->_maps[$client_map]['map_icon'][$key]);
			}
			$this->_maps[$client_map]['map_icon_id'] = 0*/;
			$this->_notifyMap($client, $this->_clients[$client_id]['map']);
			$this->_notifyInformation($client);
        } else if ($tag == $tag_report_map_icon) {
        	$client_map = $this->_clients[$client_id]['map'];
        	$type = mb_substr($payload, 0, strpos($payload, ","));
        	$buf = mb_substr($payload, strpos($payload, ",") + 1);
        	
        	$x = mb_substr($buf, 0, strpos($buf, ","));
        	$buf = mb_substr($buf, strpos($buf, ",") + 1);
        	
        	$y = $buf;
        	
        	// マップにアイコンを設置
        	$map_icon_item = array();
        	$map_icon_item['id'] = $this->_maps[$client_map]['map_icon_id'];
        	$map_icon_item['type'] = $type;
        	$map_icon_item['x'] = $x;
        	$map_icon_item['y'] = $y;
        	
        	array_push($this->_maps[$client_map]['map_icon'], $map_icon_item);
        	$this->_maps[$client_map]['map_icon_id'] ++;
			$this->_notifyNewMapIconMapMembers($map_icon_item, $client_map);
        } else if ($tag == $tag_report_remove_map_icon) {
        	$client_map = $this->_clients[$client_id]['map'];
        	// マップアイコンの削除
			foreach ($this->_maps[$client_map]['map_icon'] as $key => $value) {
				if ($this->_maps[$client_map]['map_icon'][$key]['id'] == $payload) {
					unset($this->_maps[$client_map]['map_icon'][$key]);
				}
			}
			$this->_notifyRemoveMapIconMapMembers($payload, $client_map);
        } else if ($tag == $tag_report_clear_map_icon) {
        	$client_map = $this->_clients[$client_id]['map'];
        	$this->_notifyRemoveAllMapIconMapMembers($client_map);
        	// マップアイコンの全削除
			foreach ($this->_maps[$client_map]['map_icon'] as $key => $value) {
				unset($this->_maps[$client_map]['map_icon'][$key]);
			}
        }
    }

    private function _notifyInformation($client)
    {
		$client_id = $client->getClientId();
        $client_map = $this->_clients[$client_id]['map'];
		foreach ($this->_maps[$client_map]['map_icon'] as $value) {
			$map_icon_item = $value;
			$client->send("notify_map_icon:" . $map_icon_item['id'] . "," . $map_icon_item['type'] . "," . $map_icon_item['x'] . "," . $map_icon_item['y']);
		}
    }
    
    private function _notifyMap($client, $map)
    {
		$client->send("notify_map:" . $map);
    }
    
    private function _notifyNewMapIconMapMembers($map_icon_item, $map)
    {
		$this->_sendMapMembers("notify_map_icon:" . $map_icon_item['id'] . "," . $map_icon_item['type'] . "," . $map_icon_item['x'] . "," . $map_icon_item['y'], $map);
    }

    private function _notifyRemoveAllMapIcon($client)
    {
		$client_id = $client->getClientId();
        $client_map = $this->_clients[$client_id]['map'];
		foreach ($this->_maps[$client_map]['map_icon'] as $value) {
			$map_icon_item = $value;
			$client->send("notify_remove_map_icon:" . $map_icon_item['id']);
		}
    }
    
    private function _notifyRemoveAllMapIconMapMembers($map)
    {
		foreach ($this->_maps[$map]['map_icon'] as $value) {
			$map_icon_item = $value;
			$this->_sendMapMembers("notify_remove_map_icon:" . $map_icon_item['id'], $map);
		}
    }   

    private function _notifyRemoveMapIconMapMembers($id, $map)
    {
		$this->_sendMapMembers("notify_remove_map_icon:" . $id, $map);
    }    
	
	public function setServerInfo($serverInfo)
	{
		if(is_array($serverInfo))
		{
			$this->_serverInfo = $serverInfo;
			return true;
		}
		return false;
	}


	public function clientConnected($ip, $port)
	{
		$this->_serverClients[$port] = $ip;
		$this->_serverClientCount++;
		$this->statusMsg('Client connected: ' .$ip.':'.$port);
		$data = array(
			'ip' => $ip,
			'port' => $port,
			'clientCount' => $this->_serverClientCount,
		);
		$encodedData = $this->_encodeData('clientConnected', $data);
		$this->_sendAll($encodedData);
	}
	
	public function clientDisconnected($ip, $port)
	{
		if(!isset($this->_serverClients[$port]))
		{
			return false;
		}
		unset($this->_serverClients[$port]);
		$this->_serverClientCount--;
		$this->statusMsg('Client disconnected: ' .$ip.':'.$port);
		$data = array(			
			'port' => $port,
			'clientCount' => $this->_serverClientCount,
		);
		$encodedData = $this->_encodeData('clientDisconnected', $data);
		$this->_sendAll($encodedData);
	}
	
	public function clientActivity($port)
	{
		$encodedData = $this->_encodeData('clientActivity', $port);
		$this->_sendAll($encodedData);
	}

	public function statusMsg($text, $type = 'info')
	{		
		$data = array(
			'type' => $type,
			'text' => '['. strftime('%m-%d %H:%M', time()) . '] ' . $text,
		);
		$encodedData = $this->_encodeData('statusMsg', $data);		
		$this->_sendAll($encodedData);
	}
	
	private function _sendServerinfo($client)
	{
		if(count($this->_clients) < 1)
		{
			return false;
		}
		$currentServerInfo = $this->_serverInfo;
		$currentServerInfo['clientCount'] = count($this->_serverClients);
		$currentServerInfo['clients'] = $this->_serverClients;
		$encodedData = $this->_encodeData('serverInfo', $currentServerInfo);
		$client->send($encodedData);
	}

	private function _sendMapMembers($encodedData, $map)
	{		
		if(count($this->_clients) < 1)
		{
			return false;
		}
		foreach($this->_clients as $sendto)
		{
			$client_id = $sendto['client']->getClientId();
        	$client_map = $this->_clients[$client_id]['map'];
        	
        	if ($client_map == $map) {
            	$sendto['client']->send($encodedData);
            }
        }
	}
	
	private function _sendAll($encodedData)
	{		
		if(count($this->_clients) < 1)
		{
			return false;
		}
		foreach($this->_clients as $sendto)
		{
            $sendto['client']->send($encodedData);
        }
	}
}