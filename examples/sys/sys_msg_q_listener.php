<?php
/*
	Copyright 2011 John Mullee

	This file is part of PhpIPCMessageQueue.

	PhpIPCMessageQueue is free software: you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation, either version 3 of
	the License, or (at your option) any later version.

	PhpIPCMessageQueue is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
	without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
	See the GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with PhpIPCMessageQueue.
	If not, see http://www.gnu.org/licenses/.
*/
require_once('sys_q_listener.php');

// constructor creates Q if needed
$q = new sys_q_listener();

$options = getopt('skc',array('message:'));

if(isset($options['s']))
	{
	// run in server mode
	become_daemon($_ENV['PHP_PIDFILE'],MSGQ_UID,MSGQ_GID);
	$q->process_messages();
	}
else
	if(isset($options['k']))
		{
		// kill Queue (stops server)
		$q->deletequeue();
		}
	else
		if(isset($options['message']))
			{
			// client; send message
			$q->send_message($options['message']);
			}
		else
			{
			print("options:\n\t-s\t\t\tStart listener\n\t\t\t\t\t- should be run as root\n\t\t\t\t\t- can be run many times to start many listeners\n");
			print("\t-k\t\t\tKill Queue\n");
			print("\t--message \"whatever\"\tPush Message message onto end of Queue FIFO\n");
			}

