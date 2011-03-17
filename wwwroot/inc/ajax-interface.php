<?php
/*
 *
 *  This file contains HTML-generating funcitons which are needed in ajax handler
 *
 *
*/

// retrieves ports link status from switch and formats results to dynamic-HTML
// returns array which could be packed into json and passed to client's browser
function formatPortLinkHints ($object_id)
{
	$result = array();
	$linkStatus = gwRetrieveDeviceConfig ($object_id, 'getportstatus');
	foreach ($linkStatus as $portname => $link_info)
	{
		$link_info = $linkStatus[$portname];
		switch ($link_info['status'])
		{
			case 'up':
				$img_filename = 'link-up.png';
				break;
			case 'down':
				$img_filename = 'link-down.png';
				break;
			case 'disabled':
				$img_filename = 'link-disabled.png';
				break;
		}

		$hidden_lines = array();
		$hidden_lines[] = $portname . ': ' . $link_info['status'];
		if (isset ($link_info['speed']))
			$hidden_lines[] = 'Speed: ' . $link_info['speed'];
		if (isset ($link_info['duplex']))
			$hidden_lines[] = 'Duplex: ' . $link_info['duplex'];
		if (count ($hidden_lines))
			$result[$portname]['popup'] = implode ('<br>', $hidden_lines);
		$visible_part = "<img width=16 height=16 src='?module=chrome&uri=pix/${img_filename}'>";
		$result[$portname]['inline'] = $visible_part;
	}
	// put empty pictures for not-found ports
	$object = spotEntity ('object', $object_id);
	amplifyCell ($object);
	foreach ($object['ports'] as $port)
		if (! isset ($result[$port['name']]))
			$result[$port['name']]['inline'] = "<img width=16 height=16 src='?module=chrome&uri=pix/1x1t.gif'>";
	return $result;
}

// retrieves MAC address list from switch and formats results to dynamic-HTML
// returns array which could be packed into json and passed to client's browser
function formatPortMacHints ($object_id)
{
	$result = array();
	$macList = gwRetrieveDeviceConfig ($object_id, 'getmaclist');
	foreach ($macList as $portname => $list)
	{
		$list = $macList[$portname];
		$visible_part = count ($list) . ' MACs';
		$result[$portname]['inline'] = $visible_part;
		if (count ($list))
		{
			$hidden_part = '<table width="100%"><tr><th>MAC<th>VID</tr>';
			foreach ($list as $mac)
				$hidden_part .= '<tr><td>' . $mac['mac'] . '<td>' . $mac['vid'] . '</tr>';
			$result[$portname]['popup'] = $hidden_part;
		}
	}
	return $result;
}

// retrieves port configs from switch and formats results to dynamic-HTML
// returns array which could be packed into json and passed to client's browser
function formatPortConfigHints ($object_id, $R = NULL)
{
	$result = array();
	if (! isset ($R))
		$R = getRunning8021QConfig ($object_id);
	foreach ($R['portdata'] as $portname => $portdata)
		if (isset ($portdata['config']))
		{
			$hidden_part = '';
			foreach ($portdata['config'] as $line)
				$hidden_part .= '<span class="'. $line['type'] . '">' . htmlentities ($line['line']) . '</span><br />';
			$result[$portname]['popup'] = $hidden_part;
		}
	return $result;
}

function dispatchAJAXRequest()
{
	genericAssertion ('ac', 'string');
	switch ($_REQUEST['ac'])
	{
	case 'verifyCode':
		global $pageno, $tabno;
		$pageno = 'perms';
		$tabno = 'edit';
		fixContext();
		assertPermission();
		genericAssertion ('code', 'string');
		$result = getRackCode (dos2unix ($_REQUEST['code']));
		if ($result['result'] == 'ACK')
			echo "ACK\n";
		else
			echo "NAK\n" . $result['load'];
		break;
	# returns JSON-encoded text
	case 'get-port-link':
	case 'get-port-mac':
	case 'get-port-conf':
		$funcmap = array
		(
			'get-port-link' => 'formatPortLinkHints',
			'get-port-mac'  => 'formatPortMacHints',
			'get-port-conf' => 'formatPortConfigHints',
		);
		$opmap = array
		(
			'get-port-link' => 'get_link_status',
			'get-port-mac'  => 'get_mac_list',
			'get-port-conf' => 'get_port_conf',
		);
		genericAssertion ('object_id', 'uint');
		fixContext (spotEntity ('object', $_REQUEST['object_id']));
		assertPermission ('object', 'liveports', $opmap[$_REQUEST['ac']]);
		echo json_encode ($funcmap[$_REQUEST['ac']] ($_REQUEST['object_id']));
		break;
	default:
		throw new InvalidRequestArgException ('ac', $_REQUEST['ac']);
	}
}

?>
